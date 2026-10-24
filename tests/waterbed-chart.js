'use strict';
const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class Element{
 constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.style={};this.events={};this.textContent='';}
 append(...nodes){this.children.push(...nodes);}
 prepend(node){this.children.unshift(node);}
 replaceChildren(...nodes){this.children=nodes;}
 setAttribute(k,v){this.attrs[k]=String(v);}
 addEventListener(k,v){this.events[k]=v;}
}
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
for(const script of html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(script[1]);
const context={document:{createElement:t=>new Element(t),createElementNS:(ns,t)=>new Element(t)},text:(n,t)=>n.textContent=String(t)};
vm.createContext(context);
vm.runInContext(html.slice(html.indexOf('function renderWaterbedYearComparison('),html.indexOf('function deviceItems(')),context);

const store=new Map();context.localStorage={getItem:k=>store.get(k)??null,setItem:(k,v)=>store.set(k,v)};
const all=node=>[node,...node.children.flatMap(all)];
const start=Date.parse('2026-01-01T00:00:00+01:00')/1000;
const data={};for(const range of ['day','week','month','year'])data[range]={start,end:start+86400,series:{w:[{t:start,v:51}],kwh:[{t:start,v:2}],sven:[{t:start,v:28}],susi:[{t:start,v:29}]}};
const annual={currentYear:2026,previousYear:2025,currentTotal:30,previousTotal:21,months:[{name:'Jan',current:10,previous:9},{name:'Feb',current:20,previous:12}]};
const root=context.renderWaterbedChart(data,annual,{raw:51});
const click=label=>{const b=all(root).find(n=>n.tag==='button'&&n.textContent===label);assert(b,label);b.events.click();};
for(const period of ['Tag','Woche','Monat','Jahr'])for(const unit of ['W','kWh']){
 click(period);click(unit);const nodes=all(root);assert.equal(nodes.filter(n=>n.tag==='svg').length,1);assert.equal(nodes.find(n=>n.tag==='svg').attrs.viewBox,'0 0 640 260');
 assert(!nodes.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity/.test(v))));
 assert(nodes.some(n=>n.tag==='button'&&n.textContent==='Sven · °C'));
 if(unit==='W')assert(nodes.some(n=>n.tag==='text'&&n.textContent==='2000'));
 if(unit==='kWh')assert(nodes.some(n=>n.tag==='text'&&n.textContent===({Tag:'10',Woche:'70',Monat:'150',Jahr:'150'}[period])));
}
click('Jahr');click('kWh');
let single=all(root);assert.equal(single.filter(n=>n.tag==='rect').length,2,'Single selected year');click('2025');assert.deepEqual(all(root).filter(n=>n.tag==='rect').map(n=>Number(n.attrs['data-kwh'])),[9,12]);click('2026');click('Jahresvergleich');
let nodes=all(root),bars=nodes.filter(n=>n.tag==='rect');
assert.equal(bars.length,4);
assert.deepEqual(bars.map(n=>Number(n.attrs['data-kwh'])),[9,12,10,20],'Individual monthly values, never accumulated');
assert(nodes.some(n=>n.textContent==='30,00 kWh'));assert(nodes.some(n=>n.textContent==='21,00 kWh'));
assert(nodes.some(n=>n.tag==='text'&&n.textContent==='0'));assert(nodes.some(n=>n.tag==='text'&&n.textContent==='35'));
assert.equal(nodes.filter(n=>n.attrs['data-temperature']==='sven').length,1);
click('Sven · °C');nodes=all(root);assert.equal(nodes.filter(n=>n.attrs['data-temperature']==='sven').length,0);
assert.equal(nodes.filter(n=>n.attrs['data-temperature']==='susi').length,1);
click('Susi · °C');assert.equal(all(root).filter(n=>n.attrs['data-temperature']).length,0);
assert(store.size>0);const saved=JSON.parse([...store.values()][0]);assert.equal(saved.range,'compare');assert.equal(saved.unit,'kwh');assert.deepEqual(saved.visible,{sven:false,susi:false});assert.equal(saved.year,2026);
click('Tag');click('W');assert.equal(all(root).filter(n=>n.tag==='path'&&n.attrs.d).length,1);
click('Sven · °C');assert.equal(all(root).filter(n=>n.tag==='path'&&n.attrs.d).length,2);
assert(all(context.renderWaterbedChart(null)).some(n=>n.textContent.includes('Keine archivierten')));
console.log('waterbed-chart: OK (8 modes, fixed axes, individual monthly bars, totals, independent toggles, saved preferences)');
