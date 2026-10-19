'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
class Element {
 constructor(){this.children=[];this.textContent='';}
 append(...nodes){this.children.push(...nodes);}
 replaceChildren(...nodes){this.children=[...nodes];}
 setAttribute(){}
 addEventListener(name,fn){this.events??={};this.events[name]=fn;}
}
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
const root=new Element();let controls=[];const powerButtons=[];
const context={document:{createElement:()=>new Element()},text:(el,value)=>{el.textContent=String(value);},card:()=>{const box=new Element();root.append(box);return box;},renderRoomControl:(box,item)=>controls.push(item.command),buttons:(box,command,enabled)=>powerButtons.push({command,enabled})};
context.busy=false;const sent=[];context.send=(...args)=>sent.push(args);
vm.createContext(context);
vm.runInContext(html.slice(html.indexOf('function renderOtherShields('),html.indexOf('function renderLivingMediaStatus(')),context);
vm.runInContext(html.slice(html.indexOf('function renderShield('),html.indexOf('function renderCinemaFields(')),context);
const full={online:true,power:'awake',app:'smarttube',playback:'playing',title:'Video title',error:'',updated:10};
const bedroom=['BedroomAvrPower','BedroomAvrZone','BedroomAvrSource','BedroomAvrVolume','BedroomAvrMute','BedroomSubwoofer'].map(command=>({command,name:command,value:{text:'Status'}}));
context.renderOtherShields({ankleidezimmer:{...full,device:'ankleidezimmer'},schlafzimmer:{...full,device:'schlafzimmer'}},bedroom);
assert.equal(powerButtons[0].command,'ShieldPower_schlafzimmer');
assert.equal(powerButtons[1].command,'ShieldPower_ankleidezimmer');
assert.equal(root.children.length,2);
assert.equal(root.children[0].children[0].textContent,'Medien Schlafzimmer');
assert.equal(root.children[1].children[0].textContent,'Medien Ankleidezimmer');
assert.deepEqual(controls,bedroom.filter(item=>item.command!=='BedroomAvrZone').map(item=>item.command));
const flatten=el=>[el.textContent,...el.children.map(flatten)].join(' ');
assert.match(flatten(root),/Video title/);
root.replaceChildren();controls=[];
context.renderOtherShields({ankleidezimmer:{online:true,error:'OK',compact:true,device:'ankleidezimmer'},schlafzimmer:{online:false,error:'Timeout',compact:true,device:'schlafzimmer'}},bedroom);
assert.equal(powerButtons[2].enabled,false);
assert.equal(powerButtons[3].enabled,true);
assert.doesNotMatch(flatten(root),/Video title|Aktive App|Wiedergabe|Letzte Abfrage/);
assert.match(flatten(root),/Online.*Diagnose/);
assert.deepEqual(controls,bedroom.filter(item=>item.command!=='BedroomAvrZone').map(item=>item.command));
const tvParent=new Element();
context.renderShield(tvParent,{kind:'tv',device:'philips',online:false,power:'unknown',error:'Timeout',stale:false});
assert.equal(powerButtons.at(-1).command,'ShieldPower_philips');
assert.equal(powerButtons.at(-1).enabled,true); // WLAN wake must remain possible when API is asleep.
assert.match(flatten(tvParent),/Philips TV/);
assert.doesNotMatch(flatten(tvParent),/Aktive App|Wiedergabe|Medientitel/);
assert.equal(tvParent.children[0].children.find(n=>n.className==='tvHdmiButton'),undefined);
const onParent=new Element();context.renderShield(onParent,{kind:'tv',device:'philips',online:true,power:'awake',error:'',stale:false});
assert.equal(onParent.children[0].children.find(n=>n.className==='tvHdmiButton'),undefined);
assert.doesNotMatch(flatten(onParent),/HDMI 3/);
console.log('Shield room layout, compact rendering and TV source button: passed');
const zattoo=new Element();
context.renderShield(zattoo,{...full,app:'com.zattoo.player',title:'Bares für Rares',channel:'ZDFneo',subtitle:'Folge 2156'});
assert.match(flatten(zattoo),/Sender ZDFneo.*Sendung Bares für Rares.*Details Folge 2156/);
const compact=new Element();
context.renderShield(compact,{...full,compact:true,app:'com.zattoo.player',channel:'ZDFneo',subtitle:'Folge 2156'});
assert.doesNotMatch(flatten(compact),/ZDFneo|Folge 2156|Sender|Sendung/);
