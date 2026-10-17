'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class Element {
 constructor(){this.children=[];this.parentNode=null;this.hidden=false;this.textContent='';this.events={};}
 append(...nodes){for(const node of nodes){node.remove();node.parentNode=this;this.children.push(node);}}
 remove(){if(this.parentNode){this.parentNode.children=this.parentNode.children.filter(n=>n!==this);this.parentNode=null;}}
 replaceChildren(...nodes){for(const node of [...this.children])node.remove();this.append(...nodes);}
 insertBefore(node,before){node.remove();node.parentNode=this;this.children.splice(this.children.indexOf(before),0,node);}
 setAttribute(){}
 addEventListener(name,fn){this.events[name]=fn;}
}
const main=new Element();
const find=(node,id)=>node.id===id?node:node.children.map(n=>find(n,id)).find(Boolean);
const el=id=>find(main,id);
const add=(id,parent=main)=>{const node=new Element();node.id=id;parent.append(node);return node;};
const cards=add('cards'),root=add('weatherRoomsRow');
const weather=add('weatherSection',root),rooms=add('roomsSection',root),temperatures=add('rooms',rooms);
add('heatingProfile',add('heatingProfileSection'));
let outdoor=add('card-outdoor',cards),heater=add('card-heater',cards),sent=[];
const context={el,document:{createElement:()=>new Element()},text:(node,value)=>node.textContent=String(value),busy:false,send:(...args)=>sent.push(args),roomItemBox:()=>new Element(),displayDescription:()=>{}};
vm.createContext(context);
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
vm.runInContext(html.slice(html.indexOf('function restoreOverviewPairing('),html.indexOf('function entryGroup(')),context);
context.pairOverviewSections();
assert.deepEqual(el('weatherOutdoorPair').children,[weather,outdoor]);
assert.deepEqual(el('roomsHeaterPair').children,[rooms,heater]);
context.restoreOverviewPairing();
assert.equal(el('weatherSection'),weather);assert.equal(el('rooms'),temperatures);
assert.equal(el('card-outdoor'),undefined); // Old dynamic cards must not survive a rerender.
outdoor=add('card-outdoor',cards);heater=add('card-heater',cards);
weather.hidden=true;context.pairOverviewSections();
assert.equal(el('weatherSection'),weather);assert.deepEqual(el('weatherOutdoorPair').children,[outdoor]);
const item={id:31603,value:{raw:1,text:'Komfort'},control:{kind:'enum',options:[{value:1,name:'Komfort'},{value:2,name:'Absenken'}]}};
context.renderHeatingProfile({View:0,HeatingProfileItem:item});
let panel=el('roomsHeatingProfile');assert.equal(panel.parentNode,rooms);
assert.match(panel.children[0].textContent,/Komfort/);
assert.equal(panel.children[1].children[0].disabled,true);
panel.children[1].children[1].events.click();
assert.equal(sent[0][0],'RoomValue');assert.equal(sent[0][1].id,31603);assert.equal(sent[0][1].value,2);
context.renderHeatingProfile({View:9,HeatingProfileItem:item});
assert.notEqual(el('roomsHeatingProfile'),panel);
assert.equal(el('heatingProfileSection').hidden,true);
context.renderHeatingProfile({View:12,HeatingProfileItem:item});
assert.equal(el('roomsHeatingProfile'),undefined);assert.equal(el('heatingProfileSection').hidden,false);
const centerItem={id:123,heatingCenter:true,command:'HeatingCenterMode',value:{raw:'auto',text:'Automatik · Regelung aktiv · Herbst'},
 center:{mode:'auto',manualSeason:'winter'},note:'Jahreszeit aus Zentrale',control:{kind:'enum',options:[{value:'auto',name:'Automatik'},{value:'manual',name:'Manuell'}]}};
context.renderHeatingProfile({View:0,HeatingProfileItem:centerItem});
panel=el('roomsHeatingProfile');assert.equal(panel.children[0].textContent,'Heizung: Automatik · Regelung aktiv · Herbst');
panel.children[1].children[1].events.click();assert.deepEqual(sent.at(-1),['HeatingCenterMode','manual']);
centerItem.value.raw='manual';centerItem.center.mode='winter';
context.renderHeatingProfile({View:0,HeatingProfileItem:centerItem});panel=el('roomsHeatingProfile');
assert.equal(panel.children[2].children.length,4);assert.equal(panel.children[2].children[3].disabled,true);
panel.children[2].children[2].events.click();assert.deepEqual(sent.at(-1),['HeatingCenterMode','autumn']);
console.log('Weather/outdoor, rooms/heater, legacy profile and heating-center mode controls: passed');

