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
const data={};for(const range of ['day','week','month','year'])data[range]={start:1000,end:10000,series:{w:[{t:1000,v:100},{t:4600,v:null},{t:8200,v:140}],kwh:[{t:1000,v:0.1}],sven:[{t:1000,v:28}],susi:[{t:1000,v:29}]}};
const all=node=>[node,...node.children.flatMap(all)];
const annual={currentYear:2026,previousYear:2025,currentTotal:100,previousTotal:90,months:[{name:'Jan',current:10,previous:9}]};
const root=context.renderWaterbedChart(data,annual);
const click=label=>{const b=all(root).find(n=>n.tag==='button'&&n.textContent===label);assert(b,label);b.events.click();};
for(const period of ['Tag','Woche','Monat','Jahr'])for(const unit of ['W','kWh']){
 click(period);click(unit);const nodes=all(root);
 assert(nodes.find(n=>n.tag==='svg').attrs['aria-label'].includes(unit));
 assert(nodes.some(n=>n.textContent==='Sven · °C'));assert(nodes.some(n=>n.textContent==='Susi · °C'));
 assert(!nodes.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity/.test(v))));
}
const empty=context.renderWaterbedChart(null);assert(all(empty).some(n=>n.textContent.includes('keine Daten')));
click('Jahresvergleich');
assert.equal(all(root).filter(n=>n.tag==='svg').length,1,'Only one chart in comparison mode');
assert(all(root).some(n=>n.textContent==='Jahresverbrauch und Monatsvergleich'));
assert(!all(root).some(n=>n.tag==='button'&&n.textContent==='W'),'Comparison uses kWh');
click('Tag');assert.equal(all(root).filter(n=>n.tag==='svg').length,1);
const energy=context.renderWaterbedEnergy({history:data,annual,values:{},days:[{date:'01.01.2026',kwh:5}]});
assert.equal(all(energy).filter(n=>n.tag==='svg').length,1,'Only one chart in complete waterbed section');
assert(!all(energy).some(n=>n.className==='waterbedHistory'),'Legacy table removed');
assert(!all(energy).some(n=>n.className==='waterbedAnnual'),'No second annual chart below history');
const live=context.renderWaterbedChart({day:{start:1000,end:8200,series:{}}},annual,{raw:51,text:'51 W'});
all(live).find(n=>n.tag==='button'&&n.textContent==='W').events.click();
assert(all(live).some(n=>n.tag==='text'&&n.attrs.x==='46'&&n.textContent==='60'),'Live 51 W gives 0–60 W fallback scale');
assert(!all(live).some(n=>n.tag==='text'&&n.attrs.x==='46'&&n.textContent==='1,1'),'No misleading 1.1 W scale');
assert(all(live).some(n=>n.textContent.includes('Aktuell: 51 W.')),'Live power distinct from historical means');
assert.deepEqual(all(live).filter(n=>n.tag==='text'&&n.attrs.x==='46').map(n=>n.textContent),['0','10','20','30','40','50','60'],'10 W labels from zero');
assert.equal(all(live).filter(n=>n.tag==='line'&&n.attrs['stroke-width']==='0.5').length,6,'5 W minor grid');
for(const peak of [51,260,600,3000,10000]){
 const chart=context.renderWaterbedChart({day:{start:1000,end:8200,series:{w:[{t:1000,v:peak}]}}},annual,{raw:51,text:'51 W'});
 const labels=all(chart).filter(n=>n.tag==='text'&&n.attrs.x==='46').map(n=>Number(n.textContent));
 assert.equal(labels[0],0);assert(labels.at(-1)>=peak);
 assert(labels.length<=7,'Bounded labels at '+peak+' W');
 assert(all(chart).filter(n=>n.tag==='line'&&n.attrs['stroke-width']==='0.5').length<=6,'Bounded minor grid');
 if(peak===260)assert.deepEqual(labels,[0,50,100,150,200,250,300],'50 W steps for screenshot range');
}
const scaleData={...data,month:{start:Date.parse('2026-03-01T00:00:00+01:00')/1000,end:Date.parse('2026-04-01T00:00:00+02:00')/1000,series:{}},year:{start:Date.parse('2026-01-01T00:00:00+01:00')/1000,end:Date.parse('2026-10-24T12:00:00+02:00')/1000,series:{}}};
const scale=context.renderWaterbedChart(scaleData,annual);
all(scale).find(n=>n.tag==='button'&&n.textContent==='Monat').events.click();
all(scale).find(n=>n.tag==='button'&&n.textContent==='kWh').events.click();
assert.equal(all(scale).filter(n=>n.tag==='line'&&n.attrs.x1==='52'&&n.attrs.x2==='584').length,9,'Fine value grid');
assert.equal(all(scale).filter(n=>n.tag==='text'&&n.attrs.y==='232').length,31,'All 31 calendar days');
assert(all(scale).some(n=>n.tag==='text'&&n.textContent==='31'),'Last day visible');
all(scale).find(n=>n.tag==='button'&&n.textContent==='Jahr').events.click();
assert(all(scale).some(n=>n.tag==='text'&&n.textContent==='Okt'),'Monthly marks in yearly view');
assert.equal(all(scale).filter(n=>n.tag==='text'&&n.attrs.y==='232').length,12,'All 12 months');
assert(all(scale).some(n=>n.tag==='text'&&n.textContent==='Dez'),'December visible before year end');
for(const [year,month,days] of [[2026,4,30],[2026,2,28],[2028,2,29]]){
 const date=String(month).padStart(2,'0');
 const calendar=context.renderWaterbedChart({...data,month:{start:Date.parse(`${year}-${date}-01T00:00:00+01:00`)/1000,end:Date.parse(`${year}-${date}-02T00:00:00+01:00`)/1000,series:{}}},annual);
 all(calendar).find(n=>n.tag==='button'&&n.textContent==='Monat').events.click();
 assert.equal(all(calendar).filter(n=>n.tag==='text'&&n.attrs.y==='232').length,days,'Full calendar month');
}
console.log('waterbed-chart: OK (8 combinations, single chart, annual selector, legacy table removed)');
