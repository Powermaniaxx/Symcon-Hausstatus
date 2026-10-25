'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
class Element{
 constructor(){this.children=[];this.textContent='';this.classList={add(){}};}
 append(...nodes){this.children.push(...nodes);}setAttribute(){}
}
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
new vm.Script(html.match(/<script[^>]*>([\s\S]*?)<\/script>/)[1]);
const controls=[];
const context={document:{createElement:()=>new Element()},text:(el,value)=>el.textContent=String(value),buttons:(...args)=>controls.push(args)};
vm.createContext(context);vm.runInContext(html.slice(html.indexOf('function renderShield('),html.indexOf('function renderCinemaFields(')),context);
const flat=n=>[n.textContent,...n.children.map(flat)].join(' ');
const paused=new Element();context.renderShield(paused,{kind:'tv',device:'philips',online:true,power:'standby',error:'Ruhemodus: TV zuletzt Standby, Shield im Standby; TV-Abfrage pausiert.',updated:1});
assert.match(flat(paused),/Verbindung Abfrage pausiert.*Standby \(zuletzt gemeldet\)/);
assert.match(flat(paused),/Letzte Statusmeldung/);assert.doesNotMatch(flat(paused),/Letzte Abfrage|HDMI/);
assert.equal(controls[0][1],'ShieldPower_philips');assert.equal(controls[0][2],true);
const active=new Element();context.renderShield(active,{kind:'tv',device:'philips',online:true,power:'awake',error:'',updated:1});assert.match(flat(active),/Verbindung Online.*Betrieb Ein/);assert.match(flat(active),/Letzte Abfrage/);
console.log('TV standby layout: explicit paused/last-known status, power controls retained, no HDMI passed');
