'use strict';
// JointSPACE 6, HTTPS Digest authentication. No additional Node packages.
const https=require('node:https'),http=require('node:http'),crypto=require('node:crypto'),fs=require('node:fs'),dgram=require('node:dgram');
const HOST=process.env.PHILIPS_TV_HOST === '192.168.178.190' ? '192.168.178.189' : (process.env.PHILIPS_TV_HOST||'192.168.178.189');
const FILE=process.env.PHILIPS_TV_CONFIG||'/data/shield/philips-tv.credentials.json';
const HDMI_PENDING=FILE+'.hdmi3-pending';
const BACKOFF_FILE=FILE+'.connection-backoff.json';
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

function clearPendingHdmi3(){if(fs.existsSync(HDMI_PENDING))fs.unlinkSync(HDMI_PENDING);}

async function applyPendingHdmi3(){
 // HDMI switching is disabled. Cancel legacy requests without selecting an input.
 clearPendingHdmi3();
}

function reachable(){
 // Public system information only; never send pairing/digest data over HTTP.
 return new Promise(resolve=>{
  let settled=false;
  const finish=value=>{if(!settled){settled=true;clearTimeout(timer);resolve(value);}};
  const request=http.get({host:HOST,port:1925,path:'/6/system',agent:false},response=>{
   let text='';response.on('data',chunk=>{text+=chunk;if(text.length>65536)request.destroy(new Error('Systemantwort zu groß'));});
   response.on('end',()=>{try{const system=JSON.parse(text);finish(response.statusCode===200&&system.api_version?.Major===6);}catch{finish(false);}});
   response.on('error',()=>finish(false));
  });
  const timer=setTimeout(()=>{finish(false);request.destroy();},1000);
  request.on('error',()=>finish(false));
 });
}

function connectionBackoff(now){
 try{
  const state=JSON.parse(fs.readFileSync(BACKOFF_FILE,'utf8'));
  if(Number.isInteger(state.failures)&&state.failures>=0&&state.failures<=12
   &&Number.isFinite(state.nextAttempt)&&state.nextAttempt>=0&&state.nextAttempt<=now+300000)return state;
 }catch{}
 return {failures:0,nextAttempt:0};
}

function resetConnectionBackoff(){
 try{if(fs.existsSync(BACKOFF_FILE))fs.unlinkSync(BACKOFF_FILE);}catch{}
}

function recordConnectionFailure(now){
 const state=connectionBackoff(now);
 state.failures=Math.min(12,state.failures+1);
 const delay=state.failures<3?0:Math.min(300000,120000*2**(state.failures-3));
 state.nextAttempt=delay?now+delay:0;
 try{fs.writeFileSync(BACKOFF_FILE,JSON.stringify(state),{mode:0o600});}catch{}
 return state;
}

async function collect(request=api,health=reachable,now=Date.now){
 clearPendingHdmi3();
 const empty={online:false,power:'unknown',app:'',title:'',playback:'unknown',error:''};
 const unavailable=async state=>{
  let online=false;try{online=await health();}catch{}
  const pause=state.nextAttempt>now()?' HTTPS-Abfrage pausiert; neuer Versuch in '+Math.ceil((state.nextAttempt-now())/1000)+' s.':'';
  return {...empty,online,error:(online?'TV erreichbar, verschlüsselte Steuerung antwortet nicht.':'TV-Netzwerkverbindung unterbrochen; Standby oder WLAN/API prüfen.')+pause};
 };
 try{
  const config=credentials();
  const backoff=connectionBackoff(now());
  if(backoff.nextAttempt>now())return await unavailable(backoff);
  const state=await request('GET','powerstate',undefined,config);
  resetConnectionBackoff();
  let error=['On','Standby'].includes(state.powerstate)?'':'TV-Energiezustand unbekannt.';
  return {...empty,online:true,power:state.powerstate==='On'?'awake':state.powerstate==='Standby'?'standby':'unknown',error};
 }catch(error){
  if(networkFailure(error)){
   return await unavailable(recordConnectionFailure(now()));
  }
  resetConnectionBackoff();
  return {...empty,error:error.message};
 }
}

async function execute(command,request=api,wakeDevice=wake){
 if(!command||!/^[a-f0-9]{24}$/.test(command.id)||!['wake','sleep','hdmi3'].includes(command.action)||!Number.isInteger(command.expires))throw new Error('Ungültiger TV-Schaltbefehl.');
 if(command.expires<Math.floor(Date.now()/1000))return false;
 clearPendingHdmi3();
 // Acknowledge old queued HDMI commands without sending anything to the TV.
 if(command.action==='hdmi3')return true;
 const config=credentials();
 try{await request('POST','powerstate',{powerstate:command.action==='wake'?'On':'Standby'},config);}
 catch(error){
  if(command.action!=='wake'||!networkFailure(error))throw error;
  await wakeDevice(config.mac,config.broadcast);
 }
 resetConnectionBackoff();
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
module.exports={digest,api,wakePacket,collect,execute,applyPendingHdmi3,networkFailure,reachable};
if(require.main===module){
 const action=process.argv[2]||'status';
 (action==='pair'?pair():action==='status'?collect().then(status=>console.log(JSON.stringify(status))):Promise.reject(new Error('Aufruf: philips-tv.js pair oder status'))).catch(error=>{console.error(error.message);process.exitCode=1;});
}
