'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const vm=require('node:vm');
class Element {
 constructor(){this.children=[];this.textContent='';}
 append(...nodes){this.children.push(...nodes);}
 replaceChildren(...nodes){this.children=[...nodes];}
 setAttribute(){}
}
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
const root=new Element();let controls=[];
const context={document:{createElement:()=>new Element()},text:(el,value)=>{el.textContent=String(value);},card:()=>{const box=new Element();root.append(box);return box;},renderRoomControl:(box,item)=>controls.push(item.command)};
vm.createContext(context);
vm.runInContext(html.slice(html.indexOf('function renderOtherShields('),html.indexOf('function renderMediaLiving(')),context);
vm.runInContext(html.slice(html.indexOf('function renderShield('),html.indexOf('function renderCinemaFields(')),context);
const full={online:true,power:'awake',app:'smarttube',playback:'playing',title:'Video title',error:'',updated:10};
const bedroom=['BedroomAvrPower','BedroomAvrZone','BedroomAvrSource','BedroomAvrVolume','BedroomAvrMute','BedroomSubwoofer'].map(command=>({command,name:command,value:{text:'Status'}}));
context.renderOtherShields({ankleidezimmer:full,schlafzimmer:full},bedroom);
assert.equal(root.children.length,2);
assert.equal(root.children[0].children[0].textContent,'Medien Ankleidezimmer');
assert.equal(root.children[1].children[0].textContent,'Medien Schlafzimmer');
assert.deepEqual(controls,bedroom.map(item=>item.command));
const flatten=el=>[el.textContent,...el.children.map(flatten)].join(' ');
assert.match(flatten(root),/Video title/);
root.replaceChildren();controls=[];
context.renderOtherShields({ankleidezimmer:{online:true,error:'OK',compact:true},schlafzimmer:{online:false,error:'Timeout',compact:true}},bedroom);
assert.doesNotMatch(flatten(root),/Video title|Aktive App|Wiedergabe|Letzte Abfrage/);
assert.match(flatten(root),/Online.*Diagnose/);
assert.deepEqual(controls,bedroom.map(item=>item.command));
console.log('Shield room layout and compact rendering: passed');
