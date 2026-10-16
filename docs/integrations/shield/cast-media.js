'use strict';
const tls = require('node:tls');
// Only read the existing Zattoo session. Never launch, stop or load media.
function extract(status) {
  const media = status.media || {};
  const metadata = media.metadata || {};
  const cid = media.customData?.watchInfo?.cid;
  const names = {zdfneo:'ZDFneo',zdf:'ZDF',ard:'Das Erste',das_erste:'Das Erste',
    rtl:'RTL',rtl2:'RTL Zwei',sat1:'SAT.1',prosieben:'ProSieben',vox:'VOX',
    kabel1:'Kabel Eins','3sat':'3sat',arte:'ARTE',phoenix:'phoenix',kika:'KiKA',
    zdfinfo:'ZDFinfo',one:'ONE',tagesschau24:'tagesschau24'};
  const channelId = typeof cid === 'string' && /^[\w-]{1,100}$/.test(cid) ? cid : '';
  const clean = value => typeof value === 'string' ? value.slice(0,1000) : '';
  return {channel: names[channelId] || channelId, title:clean(metadata.title),
    subtitle:clean(metadata.subtitle), playback:({PLAYING:'playing',PAUSED:'paused',IDLE:'stopped'})[status.playerState] || 'unknown'};
}

function readCast(host, timeout = 2000) {
  return new Promise(resolve => {
    // Cast uses self-signed certificates; no credentials are sent.
    const socket = tls.connect({host, port:8009, rejectUnauthorized:false});
    let pending = Buffer.alloc(0), requestId = 0, done = false;
    const joined = new Set();
    const source = 'sender-symcon-status';
    const finish = result => {
      if (done) return;
      done = true; clearTimeout(timer); socket.destroy(); resolve(result);
    };
    const timer = setTimeout(() => finish(null), timeout);
    function send(destination, namespace, payload) {
      const data = Buffer.concat([field(1,0),field(2,source),field(3,destination),
        field(4,namespace),field(5,0),field(6,JSON.stringify(payload))]);
      const size = Buffer.alloc(4); size.writeUInt32BE(data.length);
      socket.write(Buffer.concat([size,data]));
    }
    const base = 'urn:x-cast:com.google.cast.';
    const connect = dest => send(dest,base+'tp.connection',{type:'CONNECT'});
    socket.once('secureConnect', () => {
      connect('receiver-0');
      send('receiver-0',base+'receiver',{type:'GET_STATUS',requestId:++requestId});
    });
    socket.on('data', chunk => {
      try {
        pending = Buffer.concat([pending,chunk]);
        while (!done && pending.length >= 4) {
          const size = pending.readUInt32BE();
          if (size > 1048576) return finish(null);
          if (pending.length < size+4) break;
          const f = decode(pending.subarray(4,size+4)); pending = pending.subarray(size+4);
          if (!f[6]) continue;
          const data = JSON.parse(f[6]);
          if (f[4] === base+'tp.heartbeat' && data.type === 'PING') send(f[2],base+'tp.heartbeat',{type:'PONG'});
          if (f[4] === base+'receiver' && data.type === 'RECEIVER_STATUS') {
            const apps = (data.status?.applications || []).filter(a =>
              /zattoo/i.test(a.displayName || '') || (a.namespaces || []).some(n => n.name === 'urn:x-cast:com.zattoo.cast.media'));
            if (!apps.length) return finish(null);
            for (const app of apps) {
              if (!app.transportId || joined.has(app.transportId)) continue;
              joined.add(app.transportId); connect(app.transportId);
              send(app.transportId,base+'media',{type:'GET_STATUS',requestId:++requestId});
            }
          }
          if (f[4] === base+'media' && joined.has(f[2]) && data.type === 'MEDIA_STATUS') {
            const active = (data.status || []).find(s => ['PLAYING','PAUSED'].includes(s.playerState));
            if (active) return finish(extract(active));
            if (data.status?.length) return finish(null);
          }
        }
      } catch { finish(null); }
    });
    socket.on('error', () => finish(null));
    socket.on('close', () => finish(null));
  });
}
async function enrich(status, host, reader = readCast) {
  if (!status.online || status.power !== 'awake' || status.app !== 'com.zattoo.player') return status;
  try {
    const cast = await reader(host);
    if (!cast) return status;
    return {...status, channel:cast.channel, title:cast.title || status.title, subtitle:cast.subtitle};
  } catch { return status; } // Cast failure must not change ADB power/online/diagnostics.
}
module.exports = {readCast,extract,enrich};
if (require.main === module) readCast(process.argv[2] || '192.168.178.107').then(value => console.log(JSON.stringify(value)));
function varint(value) {
  const bytes = [];
  do { bytes.push((value & 127) | (value > 127 ? 128 : 0)); value >>>= 7; } while (value);
  return Buffer.from(bytes);
}
function field(number, value) {
  if (typeof value === 'number') return Buffer.concat([varint(number << 3), varint(value)]);
  const b = Buffer.from(value);
  return Buffer.concat([varint((number << 3) | 2), varint(b.length), b]);
}
function decode(buffer) {
  let i = 0;
  const fields = {};
  function read() {
    let result = 0, shift = 0;
    for (let n = 0; n < 5; n++) {
      if (i >= buffer.length) throw Error('Truncated Cast frame');
      const b = buffer[i++]; result += (b & 127) * 2 ** shift;
      if (!(b & 128)) return result;
      shift += 7;
    }
    throw Error('Invalid Cast varint');
  }
  while (i < buffer.length) {
    const tag = read(), type = tag & 7, key = tag >>> 3;
    if (type === 0) fields[key] = read();
    else if (type === 2) {
      const size = read();
      if (i + size > buffer.length) throw Error('Invalid Cast field');
      fields[key] = buffer.subarray(i, i + size).toString(); i += size;
    } else throw Error('Unsupported Cast field');
  }
  return fields;
}
