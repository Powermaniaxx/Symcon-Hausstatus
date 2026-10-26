const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class Element{constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.style={};this.events={};this.textContent='';}append(...nodes){this.children.push(...nodes);}replaceChildren(...nodes){this.children=nodes;}setAttribute(k,v){this.attrs[k]=String(v);}addEventListener(k,v){this.events[k]=v;}getBoundingClientRect(){return {left:0,width:760};}}
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
for(const script of html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(script[1]);
const code=html.slice(html.indexOf('function renderWaterbedYearComparison('),html.indexOf('function deviceItems('));
const store=new Map(),ctx={document:{createElement:t=>new Element(t),createElementNS:(ns,t)=>new Element(t)},text:(n,t)=>n.textContent=String(t),localStorage:{getItem:k=>store.get(k)??null,setItem:(k,v)=>store.set(k,v)}};
vm.createContext(ctx);vm.runInContext(code,ctx);
const all=n=>[n,...n.children.flatMap(all)],epoch=s=>Date.parse(s)/1000,data={};
for(const [range,iso] of Object.entries({day:'2026-10-25T00:00:00+02:00',week:'2026-10-19T00:00:00+02:00',month:'2026-10-01T00:00:00+02:00',year:'2026-01-01T00:00:00+01:00',previousYear:'2025-01-01T00:00:00+01:00'})){
 const start=epoch(iso),isYear=/year/i.test(range),cal=ctx.waterbedCalendar({start},isYear?'year':range),end=cal.end,w=[],daily=[];
 for(let t=start;t<end;t=ctx.waterbedCalendar({start:t},'day').end){const mean=45+(daily.length%9)*4;daily.push({t,w:mean,kwh:1.1+(daily.length%5)*.2,partial:false});if(isYear)w.push({t,v:mean});else w.push({t,v:0},{t:t+3600,v:50},{t:t+7200,v:260},{t:t+14400,v:0},{t:t+60000,v:51});}
 if(!isYear)w.push({t:end,v:51});
 data[range]={start,end,periodEnd:end,daily,series:{w,kwh:cal.buckets.map(b=>({t:b.start,v:range==='day'?.12:1.2})),sven:cal.buckets.map(b=>({t:b.start,v:27.5})),susi:cal.buckets.map(b=>({t:b.start,v:28.2}))}};
}
const annual={currentYear:2026,previousYear:2025,currentTotal:900,previousTotal:850,months:Array.from({length:12},(_,i)=>({current:i===0?250:40+i,previous:35+i}))};
const root=ctx.renderWaterbedChart(data,annual,{raw:51}),click=label=>{const b=all(root).find(n=>n.tag==='button'&&n.textContent===label);assert(b,label);b.events.click();};
for(const period of ['Tag','Woche','Monat','Jahr'])for(const unit of ['W','kWh']){click(period);click(unit);const nodes=all(root),svg=nodes.filter(n=>n.tag==='svg');assert.equal(svg.length,1);assert.equal(svg[0].attrs['data-unit'],unit);assert(!all(svg[0]).some(n=>n.textContent.includes('°C')));assert(!nodes.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity/.test(v))));assert.equal(nodes.filter(n=>n.className==='waterbedDayDetails').length,1);const labels=nodes.filter(n=>n.attrs['data-axis']==='value');assert.equal(labels[0].textContent,'0');assert(labels.length<=9);if(unit==='W'){const p=nodes.find(n=>n.attrs['data-series']);assert.equal(p.attrs['data-mode'],period==='Jahr'?'line':'steps');if(period!=='Jahr')assert(p.attrs.d.includes(' H')&&p.attrs.d.includes(' V'));}}
click('Monat');click('W');let nodes=all(root);assert.equal(nodes.filter(n=>n.attrs['data-axis']==='time').length,31);assert.equal(nodes.filter(n=>n.attrs['data-day']).length,31);assert(nodes.some(n=>n.textContent==='01.10.2026'));
const select=nodes.find(n=>n.tag==='select');select.value=String(data.month.daily[24].t);select.events.change();assert(all(root).some(n=>n.textContent==='Heizphasen · 25.10.2026'));assert(all(root).some(n=>n.textContent==='24:00'));
click('4×');assert.equal(all(root).find(n=>n.tag==='svg').attrs.viewBox,'0 0 3040 300');click('1×');
click('Jahr');click('W');nodes=all(root);assert.equal(nodes.filter(n=>n.attrs['data-axis']==='time').map(n=>n.textContent).join(','),'Jan,Feb,Mär,Apr,Mai,Jun,Jul,Aug,Sep,Okt,Nov,Dez');assert.equal(nodes.filter(n=>n.attrs['data-day']).length,365);
click('kWh');nodes=all(root);assert.equal(nodes.filter(n=>n.attrs['data-kwh']).length,12);assert(nodes.filter(n=>n.attrs['data-kwh']).every(n=>Number(n.attrs.y)>=34));click('2025');assert.equal(all(root).find(n=>n.attrs['data-kwh']).attrs['data-kwh'],'35');click('Jahresvergleich');assert.equal(all(root).filter(n=>n.attrs['data-kwh']).length,24);
click('Tag');click('W');const details=all(root).find(n=>n.className==='waterbedTemperatureDetails');details.open=true;details.events.toggle();assert.equal(all(root).filter(n=>n.tag==='svg').length,2);assert.equal(all(details).find(n=>n.tag==='svg').attrs['data-unit'],'°C');click('Sven');assert(!all(details).some(n=>n.attrs['data-series']==='Sven'));assert.equal(JSON.parse([...store.values()][0]).visible.sven,false);
for(const [iso,count] of [['2024-02-01T00:00:00+01:00',29],['2025-02-01T00:00:00+01:00',28],['2026-04-01T00:00:00+02:00',30]])assert.equal(ctx.waterbedCalendar({start:epoch(iso)},'month').ticks.length,count);
assert.equal(data.day.end-data.day.start,25*3600);
const cal=ctx.waterbedCalendar(data.day,'day'),frame=ctx.waterbedFrame('Test',{min:0,max:100,step:20},'W',cal.ticks),plot=ctx.waterbedSeries(frame,[{t:cal.start+3600,v:50},{t:cal.start+7200,v:null},{t:cal.start+10800,v:0}],cal,{mode:'steps'});ctx.waterbedInspect(frame,plot,cal,{mode:'steps'});frame.svg.events.pointermove({clientX:64});assert(frame.readout.textContent.includes('fehlen'));assert(all(frame.svg).find(n=>n.tag==='path').attrs.d.includes(' H'));
assert(all(ctx.renderWaterbedChart(null)).some(n=>n.textContent.includes('fehlen')));
if(process.env.WATERBED_FIXTURE)fs.writeFileSync(process.env.WATERBED_FIXTURE,JSON.stringify({data,annual,code,css:html.match(/<style[^>]*>([\s\S]*?)<\/style>/)[1]}));
console.log('waterbed-chart: OK (8 modes, steps, daily table/detail, adaptive axes, year, DST, gaps, temperatures, settings, script syntax)');
