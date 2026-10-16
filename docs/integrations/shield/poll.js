'use strict';
const {execFile} = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const DEVICES = {wohnzimmer:'192.168.178.107:5555',ankleidezimmer:'192.168.178.106:5555',schlafzimmer:'192.168.178.108:5555',philips:'192.168.178.190'};
const device = process.argv[2] || 'wohnzimmer';
const TARGET = DEVICES[device];
const blank = error => ({online:false,power:'unknown',app:'',playback:'unknown',title:'',error});

function parse(power, activity, media) {
    const result = {...blank(''), online:true};
    const wake = power.match(/\bmWakefulness=(Awake|Asleep|Dozing|Dreaming)\b/);
    if (!wake) { result.error = 'Power-Status nicht erkannt.'; return result; }
    result.power = /Awake|Dreaming/.test(wake[1]) ? 'awake' : 'standby';
    if (result.power === 'standby') return result;
    if (/Permission Denial|Can't find service|Exception occurred while dumping/i.test(activity+'\n'+media)) {
        result.error = 'ADB dumpsys nicht verfügbar oder nicht erlaubt.'; return result;
    }
    const focus = activity.match(/(?:topResumedActivity|mResumedActivity|ResumedActivity)[^\n]*?\s([\w.]+)\/[\w.$]+/);
    result.app = focus ? focus[1] : '';
    // Bound each session at its package line, never mix inactive sessions or metadata.
    const sessions = media.split(/^\s*package=/m).slice(1).map(block => {
        const pkg = block.match(/^([^\s]+)/)?.[1];
        const active = /^\s*active=true\s*$/m.test(block);
        const state = Number(block.match(/PlaybackState\s*\{state=(\d+)/)?.[1] ?? -1);
        const updated = Number(block.match(/\bupdated=(\d+)/)?.[1] ?? 0);
        // Description's first component is Android's displayed title; retain commas in it.
        const description = block.match(/^\s*metadata:.*?description=(.*)$/m)?.[1] ?? '';
        const title = description.replace(/,\s*[^,]*,\s*null\s*$/, '').trim();
        return {pkg, active, state, title:title === 'null' ? '' : title, updated};
    }).filter(s => s.active && s.state >= 0);
    const playing = sessions.filter(s => s.state === 3);
    // Background playback is legitimate. Prefer a playing foreground session, then newest.
    const candidates = playing.length ? playing : sessions.filter(s => s.pkg === result.app);
    candidates.sort((a,b) => Number(b.pkg === result.app)-Number(a.pkg === result.app) || b.updated-a.updated);
    const session = candidates[0];
    result.playback = session ? ({3:'playing',2:'paused',1:'stopped',0:'stopped'}[session.state] || 'unknown') : 'stopped';
    result.title = session && [2,3].includes(session.state) ? session.title.slice(0,1000) : '';
    if (!focus) result.error = 'Aktive App nicht erkannt.';
    return result;
}

function adb(args, timeout, env) {
    return new Promise((resolve,reject) => execFile('adb',args,{timeout,env,maxBuffer:4*1024*1024},(error,stdout,stderr) => {
        if(error) reject(new Error(error.killed ? 'ADB-Timeout' : /unauthorized/i.test(stdout+stderr) ? 'ADB nicht autorisiert' : 'ADB-Befehl fehlgeschlagen'));
        else resolve(stdout);
    }));
}

function adbEnvironment() {
    const home = process.env.SHIELD_ADB_HOME || '/data/shield-adb';
    fs.mkdirSync(path.join(home,'.android'),{recursive:true,mode:0o700});
    return {...process.env, HOME:home, ANDROID_USER_HOME:path.join(home,'.android'), ADB_VENDOR_KEYS:path.join(home,'.android')};
}

async function collect() {
    const env = adbEnvironment();
    try {
        // No kill-server/disconnect: other ADB consumers must keep their connections.
        await adb(['connect',TARGET],2000,env);
        const state = await adb(['-s',TARGET,'get-state'],1500,env);
        if(state.trim() !== 'device') return blank('ADB nicht bereit (Autorisierung/Verbindung prüfen).');
        const command = 'dumpsys power; echo __SHIELD_ACTIVITY__; dumpsys activity activities; echo __SHIELD_MEDIA__; dumpsys media_session';
        const raw = await adb(['-s',TARGET,'shell',command],6500,env);
        const parts = raw.split(/__SHIELD_ACTIVITY__\r?\n|__SHIELD_MEDIA__\r?\n/);
        if(parts.length !== 3) return blank('ADB-Antwort unvollständig.');
        const status = parse(...parts);
        try { return await require('./cast-media.js').enrich(status, TARGET.split(':')[0]); }
        catch { return status; } // An older installation without Cast helper keeps ADB status.
    } catch(error) { return blank(error.message); }
}

async function send(status) {
    const url = new URL(process.env.SYMCON_RPC_URL || 'http://192.168.178.140:3777/api/');
    const id = Number(process.env.SYMCON_INSTANCE_ID || 52627);
    if(url.hostname === 'unconfigured' || !Number.isInteger(id) || id <= 0) throw new Error('SYMCON_RPC_URL und SYMCON_INSTANCE_ID fehlen.');
    if(!['http:','https:'].includes(url.protocol) || url.username || url.password) throw new Error('RPC-URL ungültig. Zugangsdaten separat hinterlegen.');
    const headers = {'Content-Type':'application/json'};
    if(process.env.SYMCON_RPC_USER) headers.Authorization = 'Basic '+Buffer.from(process.env.SYMCON_RPC_USER+':'+(process.env.SYMCON_RPC_PASSWORD||'')).toString('base64');
    const response = await fetch(url,{method:'POST',headers,signal:AbortSignal.timeout(2000),body:JSON.stringify({jsonrpc:'2.0',id:1,method:'SVHS_UpdateShield',params:[id,JSON.stringify(status)]})});
    if(!response.ok) throw new Error('Symcon HTTP '+response.status);
    const result = await response.json();
    if(result.error) {
        let detail = String(result.error.message || 'Unbekannter RPC-Fehler');
        for(const secret of [process.env.SYMCON_RPC_PASSWORD,process.env.SYMCON_RPC_USER]) {
            if(secret) detail = detail.split(secret).join('[ausgeblendet]');
        }
        detail = detail.replace(/[\r\n]+/g,' ').slice(0,1000);
        throw new Error('Symcon RPC fehlgeschlagen ('+String(result.error.code ?? '?')+'): '+detail);
    }
    if(result.id !== 1 || !Object.hasOwn(result,'result')) throw new Error('Ungültige Symcon-RPC-Antwort.');
    if(result.result == null) return null; // Older module: polling remains compatible.
    const reply = typeof result.result === 'string' ? JSON.parse(result.result) : result.result;
    return reply.command ?? null;
}

async function executePower(command, run = adb) {
    if(command == null) return false;
    if(!command || !/^[a-f0-9]{24}$/.test(command.id) || !['wake','sleep'].includes(command.action)
        || !Number.isInteger(command.expires)) throw new Error('Ungültiger Shield-Schaltbefehl.');
    if(command.expires < Math.floor(Date.now()/1000)) return false;
    if(!TARGET) throw new Error('Unbekannte Shield.');
    await run(['-s',TARGET,'shell','input','keyevent',command.action === 'wake' ? '224' : '223'],1500,adbEnvironment());
    return true;
}

async function main() {
    if(!TARGET) throw new Error('Unbekannte Shield: '+device);
    const lock = '/data/shield/poll-'+device+'.lock';
    try { fs.mkdirSync(lock); }
    catch(error) {
        if(error.code !== 'EEXIST') throw error;
        if(Date.now()-fs.statSync(lock).mtimeMs < 30000) return;
        fs.rmdirSync(lock); fs.mkdirSync(lock);
    }
    try {
        const ackFile = '/data/shield/power-ack-'+device;
        const powerAck = fs.existsSync(ackFile) ? fs.readFileSync(ackFile,'utf8').trim() : '';
        const driver = device === 'philips' ? require('./philips-tv.js') : null;
        const status = {...await (driver ? driver.collect() : collect()),device,powerAck};
        const command = await send(status);
        if(command && (driver || status.online) && await (driver ? driver.execute(command) : executePower(command))) fs.writeFileSync(ackFile,command.id,{mode:0o600});
        console.log(JSON.stringify({ok:true,device,online:status.online,power:status.power,error:status.error}));
    } finally { fs.rmdirSync(lock); }
}
module.exports = {parse,blank,collect,send,executePower};
if(require.main === module) main().catch(error => {console.error(error.message);process.exitCode=1;});
