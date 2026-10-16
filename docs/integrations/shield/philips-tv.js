'use strict';
// JointSPACE 6, HTTPS Digest authentication. No additional Node packages.
const https=require('node:https'),crypto=require('node:crypto'),fs=require('node:fs'),dgram=require('node:dgram');
const HOST=process.env.PHILIPS_TV_HOST||'192.168.178.190';
const FILE=process.env.PHILIPS_TV_CONFIG||'/data/shield/philips-tv.credentials.json';
const HDMI_PENDING=FILE+'.hdmi3-pending';
const FINGERPRINT='88CA8280AA7C86E1457AC62C717F3052F5F6B29B8339D223F96011D1566BF3F9';
const SHARED_KEY=Buffer.from('ZmVay1EQVFOaZhwQ4Kv81ypLAZNczV9sG4KkseXWn1NEk6cXmPKO/MCa9sryslvLCFMnNe4Z4CPXzToowvhHvA==','base64');
const md5=value=>crypto.createHash('md5').update(value).digest('hex');

function digest(challenge,method,uri,credentials){
 if(!/^Digest /i.test(challenge||''))throw new Error('Philips: Digest-Anmeldung nicht verfügbar.');
 const fields={};for(const match of challenge.matchAll(/(\w+)=(?:"([^"]*)"|([^,\s]+))/g))fields[match[1]]=match[2]??match[3];
 if((fields.algorithm||'MD5').toUpperCase()!=='MD5'||!fields.qop?.split(',').map(s=>s.trim()).includes('auth'))throw new Error('Philips: Unbekanntes Anmeldeverfahren.');
 const nc='00000001',cnonce=crypto.randomBytes(12).toString('hex');
 const response=md5([md5(credentials.user+':'+fields.realm+':'+credentials.password),fields.nonce,nc,cnonce,'auth',md5(method+':'+uri)].join(':'));
 const quoted=value=>JSON.stringify(String(value));
 return 'Digest username='+quoted(credentials.user)+', realm='+quoted(fields.realm)+', nonce='+quoted(fields.nonce)+', uri='+quoted(uri)+', response='+quoted(response)+', algorithm=MD5, qop=auth, nc='+nc+', cnonce='+quoted(cnonce)+(fields.opaque?', opaque='+quoted(fields.opaque):'');
}

function transport(method,uri,data,authorization){
 return new Promise((resolve,reject)=>{
  const body=data===undefined?'':JSON.stringify(data),headers={'Content-Type':'application/json','Content-Length':Buffer.byteLength(body)};
  if(authorization)headers.Authorization=authorization;
  const request=https.request({host:HOST,port:1926,path:uri,method,headers,agent:false,rejectUnauthorized:false},response=>{
   let text='';response.on('data',chunk=>{text+=chunk;if(text.length>262144)request.destroy(new Error('Philips-Antwort zu groß.'));});
   response.on('end',()=>resolve({status:response.statusCode,challenge:response.headers['www-authenticate'],text}));
   response.on('error',reject);
  });
  const timer=setTimeout(()=>request.destroy(new Error('Philips TV nicht erreichbar (Timeout).')),3000);
  request.on('close',()=>clearTimeout(timer));request.on('error',reject);
  // This TV has a self-signed certificate. Verify the observed fingerprint BEFORE
  // sending any HTTP headers, pairing data or digest credentials.
  request.on('socket',socket=>socket.once('secureConnect',()=>{
   const fingerprint=(socket.getPeerCertificate().fingerprint256||'').replace(/:/g,'').toUpperCase();
   if(fingerprint!==FINGERPRINT){request.destroy(new Error('Philips-Zertifikat geändert. Kopplung prüfen.'));return;}
   request.end(body);
  }));
 });
}

function networkFailure(error){
 return /nicht erreichbar|ECONNREFUSED|EHOSTUNREACH|ETIMEDOUT|ECONNRESET|EPIPE|ENETUNREACH|before secure TLS connection was established|socket hang up/i.test(String(error.code||'')+' '+String(error.message||''));
}

async function api(method,path,data,credentials,request=transport){
 const uri='/6/'+path;
 const started=Date.now();
 const attempt=async()=>{
  let response=await request(method,uri,data);
  if(response.status===401&&credentials)response=await request(method,uri,data,digest(response.challenge,method,uri,credentials));
  return response;
 };
 let response;
 try{response=await attempt();}
 catch(error){
  // Retry only a fast, failed status read. Bound total poll duration, never replay writes.
  if(method!=='GET'||!networkFailure(error)||Date.now()-started>=1500)throw error;
  await new Promise(resolve=>setTimeout(resolve,250));response=await attempt();
 }
 if(response.status===401)throw new Error('Philips TV muss per PIN gekoppelt werden.');
 if(response.status<200||response.status>=300)throw new Error('Philips HTTP '+response.status);
 if(!response.text.trim()||response.text.trim().startsWith('<'))return {};
 try{return JSON.parse(response.text);}catch{throw new Error('Philips: Ungültige API-Antwort.');}
}

function credentials(){
 if(!fs.existsSync(FILE))throw new Error('Philips TV noch nicht gekoppelt. Kopplungsskript mit pair starten.');
 const config=JSON.parse(fs.readFileSync(FILE,'utf8'));
 if(config.host!==HOST||!config.user||!config.password)throw new Error('Philips-Kopplung fehlt oder gehört zu einem anderen TV.');
 return config;
}

function wakePacket(mac){
 const hex=String(mac||'').replace(/[:-]/g,'');
 if(!/^[a-f0-9]{12}$/i.test(hex)||/^0{12}$/.test(hex))throw new Error('Für WLAN-Wecken fehlt eine gültige TV-MAC-Adresse.');
 return Buffer.concat([Buffer.alloc(6,255),...Array.from({length:16},()=>Buffer.from(hex,'hex'))]);
}

async function wake(mac,broadcast){
 const packet=wakePacket(mac);
 await new Promise((resolve,reject)=>{
  const socket=dgram.createSocket('udp4');socket.on('error',error=>{socket.close();reject(error);});
  socket.bind(()=>{socket.setBroadcast(true);socket.send(packet,9,broadcast||'192.168.178.255',error=>{socket.close();if(error)reject(error);else resolve();});});
 });
}

function hdmi3Intent(){
 return {extras:{uri:'content://android.media.tv/passthrough/'+encodeURIComponent('com.mediatek.tvinput/.hdmi.HDMIInputService/HW7')},action:'org.droidtv.playtv.SELECTURI',component:{packageName:'org.droidtv.playtv',className:'org.droidtv.playtv.PlayTvActivity'}};
}

function clearPendingHdmi3(){if(fs.existsSync(HDMI_PENDING))fs.unlinkSync(HDMI_PENDING);}

async function selectHdmi3(config,request=api){
 await request('POST','activities/launch',{intent:hdmi3Intent()},config);
}

async function applyPendingHdmi3(config,isOn,request=api){
 if(!fs.existsSync(HDMI_PENDING))return;
 const pending=JSON.parse(fs.readFileSync(HDMI_PENDING,'utf8'));
 if(!Number.isFinite(pending.until)||pending.until<Date.now()){clearPendingHdmi3();return;}
 if(!isOn)return;
 await selectHdmi3(config,request);clearPendingHdmi3();
}

async function collect(){
 const empty={online:false,power:'unknown',app:'',title:'',playback:'unknown',error:''};
 try{
  const config=credentials();const state=await api('GET','powerstate',undefined,config);
  let error=['On','Standby'].includes(state.powerstate)?'':'TV-Energiezustand unbekannt.';
  try{await applyPendingHdmi3(config,state.powerstate==='On');}catch(e){error='HDMI 3 noch nicht gesetzt: '+e.message;}
  return {...empty,online:true,power:state.powerstate==='On'?'awake':state.powerstate==='Standby'?'standby':'unknown',error};
 }catch(error){return {...empty,error:networkFailure(error)?'TV-Netzwerkverbindung unterbrochen; Standby oder WLAN/API prüfen.':error.message};}
}

async function execute(command,request=api,wakeDevice=wake){
 if(!command||!/^[a-f0-9]{24}$/.test(command.id)||!['wake','sleep','hdmi3'].includes(command.action)||!Number.isInteger(command.expires))throw new Error('Ungültiger TV-Schaltbefehl.');
 if(command.expires<Math.floor(Date.now()/1000))return false;
 const config=credentials();
 if(command.action==='hdmi3'){await selectHdmi3(config,request);clearPendingHdmi3();return true;}
 if(command.action==='sleep')clearPendingHdmi3();
 try{await request('POST','powerstate',{powerstate:command.action==='wake'?'On':'Standby'},config);}
 catch(error){
  if(command.action!=='wake'||!networkFailure(error))throw error;
  await wakeDevice(config.mac,config.broadcast);
 }
 if(command.action==='wake')fs.writeFileSync(HDMI_PENDING,JSON.stringify({until:Date.now()+90000}),{mode:0o600});
 return true;
}

async function pair(){
 const device={id:crypto.randomBytes(16).toString('hex'),device_name:'Symcon Hausstatus',device_os:'Linux',app_id:'symcon.hausstatus',app_name:'Symcon Hausstatus',type:'native'};
 const response=await api('POST','pair/request',{access:{scope:['read','write','control']},device});
 if(response.error_id!=='SUCCESS'||!response.auth_key)throw new Error('TV-Kopplung konnte nicht gestartet werden: '+String(response.error_id||'keine Antwort'));
 const input=require('node:readline/promises').createInterface({input:process.stdin,output:process.stdout});
 let pin;try{pin=(await input.question('PIN vom TV eingeben: ')).trim();}finally{input.close();}
 if(!/^\d{4,8}$/.test(pin))throw new Error('Ungültige PIN.');
 const auth={auth_appId:'1',auth_timestamp:response.timestamp,pin,auth_signature:crypto.createHmac('sha1',SHARED_KEY).update(String(response.timestamp)+pin).digest('base64')};
 const config={host:HOST,user:device.id,password:response.auth_key,mac:process.env.PHILIPS_TV_MAC||'',broadcast:process.env.PHILIPS_TV_BROADCAST||'192.168.178.255'};
 const grant=await api('POST','pair/grant',{auth,device},config);
 if(grant.error_id!=='SUCCESS')throw new Error('TV hat die PIN nicht bestätigt. Kopplung erneut starten.');
 fs.writeFileSync(FILE,JSON.stringify(config,null,2)+'\n',{mode:0o600});fs.chmodSync(FILE,0o600);
 console.log('TV gekoppelt. Zugangsdaten ausschließlich lokal gespeichert.');
}
module.exports={digest,api,wakePacket,collect,execute,hdmi3Intent,applyPendingHdmi3,networkFailure};
if(require.main===module){
 const action=process.argv[2]||'status';
 (action==='pair'?pair():action==='status'?collect().then(status=>console.log(JSON.stringify(status))):Promise.reject(new Error('Aufruf: philips-tv.js pair oder status'))).catch(error=>{console.error(error.message);process.exitCode=1;});
}
