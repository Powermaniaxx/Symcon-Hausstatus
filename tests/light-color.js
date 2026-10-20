'use strict';
const fs=require('node:fs');
const assert=require('node:assert/strict');
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
const start=html.indexOf('function renderLightColorPicker(');
const end=html.indexOf('function renderDining(',start);
assert(start>=0&&end>start,'RGB color picker missing');
class Element{
 constructor(){this.children=[];this.events={};this.attrs={};this.value='';this.disabled=false;}
 append(...x){this.children.push(...x);}
 setAttribute(k,v){this.attrs[k]=v;}
 addEventListener(k,fn){this.events[k]=fn;}
}
let editing=false,rerenders=0,busy=false;
const commands=[];
const fn=new Function('document','text','send','render','getEditing','setEditing','busy',
 html.slice(start,end).replaceAll('editing=true;','setEditing(true);')
  .replaceAll('editing=false;','setEditing(false);')
  .replaceAll('if(editing)','if(getEditing())')
 +'return renderLightColorPicker;');
const picker=fn({createElement:()=>new Element()},
 (el,value)=>{el.textContent=String(value);},
 (...args)=>commands.push(args),()=>{rerenders++;},
 ()=>editing,value=>{editing=value;},busy);
const living=new Element();
picker(living,'Wohnzimmer · Farbe',{value:{raw:0x123456},canChange:true,reason:''},'LivingColor');
assert.equal(living.children.length,1);
const input=living.children[0].children[1];
assert.equal(input.type,'color');
assert.equal(input.value,'#123456');
assert.equal(input.disabled,false);
input.value='#aabbcc';input.events.input();
assert.equal(editing,true);
input.events.change();
assert.equal(editing,false);
assert.deepEqual(commands,[['LivingColor',0xaabbcc]]);
const dining=new Element();
picker(dining,'Essbereich · Farbe',{value:{raw:0},canChange:false,reason:'Keine Aktion'},'DiningColor');
const disabled=dining.children[0].children[1];
assert.equal(disabled.disabled,true);
disabled.value='#ffffff';disabled.events.change();
assert.equal(commands.length,1,'Disabled RGB control must not send any commands');
const missing=new Element();
picker(missing,'Fehlende Farbe',null,'LivingColor');
assert.equal(missing.children.length,0);
assert(html.includes("renderLightColorPicker(light,'Wohnzimmer"),'Living card must show color');
assert(html.includes("renderLightColorPicker(lamp,'Essbereich"),'Dining card must show color');
assert(html.includes("remaining.filter(item=>item.control?.kind==='color'))box.append(roomItemBox(item))"),
 'Room RGB control must not be hidden in technical details');
console.log('RGB light picker: writable, disabled, unconfigured, placement passed');
