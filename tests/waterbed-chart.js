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
vm.runInContext(html.slice(html.indexOf("let waterbedRange='day'"),html.indexOf('function renderWaterbedEnergy(')),context);
const data={};for(const range of ['day','week','month','year'])data[range]={start:1000,end:10000,series:{w:[{t:1000,v:100},{t:4600,v:null},{t:8200,v:140}],kwh:[{t:1000,v:0.1}],sven:[{t:1000,v:28}],susi:[{t:1000,v:29}]}};
const all=node=>[node,...node.children.flatMap(all)];
const root=context.renderWaterbedChart(data);
const click=label=>{const b=all(root).find(n=>n.tag==='button'&&n.textContent===label);assert(b,label);b.events.click();};
for(const period of ['Tag','Woche','Monat','Jahr'])for(const unit of ['W','kWh']){
 click(period);click(unit);const nodes=all(root);
 assert(nodes.find(n=>n.tag==='svg').attrs['aria-label'].includes(unit));
 assert(nodes.some(n=>n.textContent==='Sven · °C'));assert(nodes.some(n=>n.textContent==='Susi · °C'));
 assert(!nodes.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity/.test(v))));
}
const empty=context.renderWaterbedChart(null);assert(all(empty).some(n=>n.textContent.includes('keine Daten')));
assert(html.includes('section.append(renderWaterbedYearComparison(data.annual))'),'Annual comparison retained');
console.log('waterbed-chart: OK (8 period/unit combinations, temperatures, missing values, script syntax)');
