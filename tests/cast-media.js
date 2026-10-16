'use strict';
const assert = require('node:assert/strict');
const {extract,enrich,readCast} = require('../docs/integrations/shield/cast-media.js');
const tls = require('node:tls');
const {EventEmitter} = require('node:events');
const sample = {playerState:'PLAYING',media:{metadata:{title:'Bares für Rares',subtitle:'Folge 2156'},customData:{watchInfo:{cid:'zdfneo'},licenseUrl:'secret'}}};
const expected = {channel:'ZDFneo',title:'Bares für Rares',subtitle:'Folge 2156',playback:'playing'};
const status = {online:true,power:'awake',app:'com.zattoo.player',title:'',error:'',playback:'playing'};
function message(source,ns,payload) {
  const fields = [Buffer.from([8,0])];
  for (const [key,value] of [[2,source],[3,'sender-symcon-status'],[4,'urn:x-cast:com.google.cast.'+ns],[6,JSON.stringify(payload)]]) {
    const body=Buffer.from(value); assert.ok(body.length<128);
    fields.push(Buffer.from([(key<<3)|2,body.length]),body);
  }
  const body=Buffer.concat(fields), length=Buffer.alloc(4);length.writeUInt32BE(body.length);
  return Buffer.concat([length,body]);
}
(async()=>{
  assert.deepEqual(extract(sample),expected);
  assert.equal(extract({media:{customData:{watchInfo:{cid:'unknown_channel'}}}}).channel,'unknown_channel');
  assert.equal(extract({media:{customData:{watchInfo:{cid:'https://secret'}}}}).channel,'');
  assert.equal(JSON.stringify(extract(sample)).includes('secret'),false);
  assert.deepEqual(await enrich(status,'host',async()=>expected),{...status,channel:'ZDFneo',title:'Bares für Rares',subtitle:'Folge 2156'});
  assert.deepEqual(await enrich(status,'host',async()=>null),status);
  assert.deepEqual(await enrich(status,'host',async()=>{throw Error('timeout');}),status);
  for (const s of [{...status,online:false},{...status,power:'standby'},{...status,app:'youtube'}]) {
    assert.deepEqual(await enrich(s,'host',()=>{throw Error('must not query');}),s);
  }
  const original = tls.connect;
  let writes=[];
  tls.connect=()=>{
    const socket=new EventEmitter();socket.destroy=()=>socket.emit('close');
    socket.write=buffer=>{
      const str=buffer.toString();writes.push(str);
      assert.doesNotMatch(str,/LAUNCH|LOAD|STOP|SET_VOLUME/);
      if(str.includes('GET_STATUS')&&str.includes('cast.receiver')){
        const reply=message('receiver-0','receiver',{type:'RECEIVER_STATUS',status:{applications:[{displayName:'Zattoo',transportId:'web-1'}]}});
        process.nextTick(()=>{socket.emit('data',reply.subarray(0,3));socket.emit('data',reply.subarray(3));});
      } else if(str.includes('GET_STATUS')&&str.includes('cast.media')) {
        const reply=message('web-1','media',{type:'MEDIA_STATUS',status:[{playerState:'PLAYING',media:{customData:{watchInfo:{cid:'zdfneo'}}}}]});
        process.nextTick(()=>socket.emit('data',reply));
      }
    };
    process.nextTick(()=>socket.emit('secureConnect'));return socket;
  };
  try { assert.equal((await readCast('fake')).channel,'ZDFneo'); assert.equal(writes.length,4); }
  finally {tls.connect=original;}
  console.log('Cast framing, session selection, metadata and ADB fallback: passed');
})().catch(error=>{console.error(error);process.exitCode=1;});
