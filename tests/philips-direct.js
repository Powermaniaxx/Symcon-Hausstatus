'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
new vm.Script(html.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1]);
const extract=name=>{const a=html.indexOf('function '+name+'('),b=html.indexOf('\nfunction ',a+1);assert(a>=0,name);return html.slice(a,b);};
const code=['renderShield','renderDirectPhilips','buttons','safeRange','rememberPanel'].map(extract).join('\n');
class E{constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.events={};this.textContent='';this.classList={add(){}};}append(...nodes){this.children.push(...nodes);}setAttribute(k,v){this.attrs[k]=String(v);}addEventListener(k,f){this.events[k]=f;}}
const sent=[];const ctx={document:{createElement:t=>new E(t)},text:(n,v)=>n.textContent=String(v),busy:false,editing:false,openPanels:new Set(),send:(...args)=>sent.push(args)};
vm.createContext(ctx);vm.runInContext(code,ctx);
const state={kind:'tv',direct:true,device:'philips',enabled:true,online:true,power:'awake',stale:false,paused:false,volume:35,mute:false,ambilight:true,ambilightMode:'FOLLOW_VIDEO',activity:'org.example.player',updated:1793366550,caps:{audio:true,ambilight:true},error:''};
const all=n=>[n,...n.children.flatMap(all)],flat=n=>all(n).map(n=>n.textContent).join(' ');
const root=new E('div');ctx.renderShield(root,state);
assert.match(flat(root),/Philips TV.*Online.*Ein.*org.example.player.*FOLLOW_VIDEO.*Lautstärke 35 %/);
const controls=all(root).filter(n=>n.tag==='button');assert(controls.length>=20);
controls.find(n=>n.attrs['aria-label']==='Philips TV Home').events.click();assert.deepEqual(sent.at(-1),['PhilipsRemoteKey','Home']);
controls.find(n=>n.textContent==='TV-Status aktualisieren').events.click();assert.deepEqual(sent.at(-1),['PhilipsRefresh',true]);
controls.find(n=>n.textContent==='Aus').events.click();assert.deepEqual(sent.at(-1),['ShieldPower_philips',false]);
const range=all(root).find(n=>n.tag==='input');range.value='55';range.events.change();assert.deepEqual(sent.at(-1),['PhilipsVolume',55]);
assert(all(root).some(n=>n.className==='philipsRemote'),'Remote lives in collapsible details');
for(const variant of [{...state,online:false,stale:true,power:'unknown',caps:{}},{...state,online:false,paused:true,power:'standby',caps:{}},{...state,enabled:false,online:false,caps:{},power:'unknown',error:'Instanz fehlt'}]){
 const parent=new E('div');ctx.renderShield(parent,variant);const nodes=all(parent);
 assert(!nodes.some(n=>n.tag==='input'),'No stale audio slider');
 assert(nodes.filter(n=>n.tag==='button'&&n.attrs['aria-label']).every(n=>n.disabled),'Remote disabled while unavailable');
 if(variant.paused){assert.match(flat(parent),/Abfrage pausiert.*Standby \(zuletzt gemeldet\)/);assert.equal(nodes.find(n=>n.tag==='button'&&n.textContent==='Ein').disabled,false,'Wake remains available');}
 if(!variant.enabled)assert(nodes.filter(n=>n.tag==='button').every(n=>n.disabled),'Invalid selection cannot be operated');
}
const xss=new E('div');ctx.renderShield(xss,{...state,activity:'<img src=x onerror=alert(1)>',error:'<script>bad</script>'});assert(!all(xss).some(n=>['img','script'].includes(n.tag)),'TV strings stay text content');
if(process.env.PHILIPS_FIXTURE)fs.writeFileSync(process.env.PHILIPS_FIXTURE,JSON.stringify({code,css:html.match(/<style[^>]*>([\s\S]*?)<\/style>/)[1],state}));
console.log('Philips UI: commands, range, collapsible remote, disabled/stale/standby and text escaping passed');
