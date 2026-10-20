'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
class Node {constructor(){this.children=[];this.events={};this.style={};}append(...n){this.children.push(...n);}setAttribute(){}addEventListener(k,f){this.events[k]=f;}}
const sent=[],context={document:{createElement:()=>new Node()},busy:false,text:(n,t)=>n.textContent=t,send:(...args)=>sent.push(args)};
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
vm.createContext(context);vm.runInContext(html.slice(html.indexOf('function renderLightColor('),html.indexOf('function motionTime(')),context);
const parent=new Node();context.renderLightColor(parent,{LightColor:{raw:0xff0000},Controls:{LightColor:true}});
const row=parent.children[0],input=row.children[0].children[0];assert.equal(input.value,'#ff0000');
input.value='#12ab34';input.events.change();assert.deepEqual(sent.pop(),['LightColor',0x12ab34]);
const presets=row.children[1].children;presets[0].events.click();assert.deepEqual(sent.pop(),['LightColor',0xff0000]);
assert.equal(sent.length,0,'Rendering must never write devices');
context.busy=true;presets[0].events.click();assert.equal(sent.length,0,'Busy clicks must not duplicate commands');
context.busy=false;const disabled=new Node();context.renderLightColor(disabled,{Controls:{LightColor:false}});
const blocked=disabled.children[0].children[0].children[0];assert.equal(blocked.disabled,true);blocked.events.change();assert.equal(sent.length,0);
console.log('Color picker, presets and disabled controls: passed');
