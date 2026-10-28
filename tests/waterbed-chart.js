const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
class E{constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.style={};this.events={};this.textContent='';}append(...n){this.children.push(...n);}prepend(n){this.children.unshift(n);}replaceChildren(...n){this.children=n;}setAttribute(k,v){this.attrs[k]=String(v);}addEventListener(k,f){this.events[k]=f;}}
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');for(const script of html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(script[1]);
const ctx={document:{createElement:t=>new E(t),createElementNS:(ns,t)=>new E(t)},text:(n,v)=>n.textContent=String(v)};vm.createContext(ctx);const code=html.slice(html.indexOf('function renderWaterbedYearComparison('),html.indexOf('function deviceItems('));vm.runInContext(code,ctx);
const all=n=>[n,...n.children.flatMap(all)],start=Date.parse('2026-01-01T00:00:00+01:00')/1000,history={};for(const key of ['day','week','month','year','previousYear'])history[key]={start,end:start+86400,series:{w:[{t:start,v:51}],kwh:[{t:start,v:2}],sven:[{t:start,v:28}],susi:[{t:start,v:29}]}};
const annual={currentYear:2026,previousYear:2025,currentTotal:30,previousTotal:21,months:[{name:'Jan',current:10,previous:9},{name:'Feb',current:20,previous:12}]};
const root=ctx.renderWaterbedEnergy({history,annual,values:{power:{raw:51,text:'51 W'}}});
const click=t=>{const b=all(root).find(n=>n.tag==='button'&&n.textContent===t);assert(b,t);b.events.click();};
for(const period of ['Tag','Woche','Monat','Jahr']){click(period);for(const unit of period==='Tag'?['W']:['W','kWh']){if(period!=='Tag')click(unit);const nodes=all(root);assert.equal(nodes.filter(n=>n.tag==='svg').length,1);assert(!nodes.some(n=>Object.values(n.attrs).some(v=>/NaN|Infinity/.test(v))));}}
click('Jahresvergleich');assert.deepEqual(all(root).filter(n=>n.attrs['data-kwh']).map(n=>Number(n.attrs['data-kwh'])).sort((a,b)=>a-b),[9,10,12,20]);click('Sven · °C');click('Susi · °C');
assert(!html.includes('waterbedAutoHistory'));assert(!html.includes('ReadLargeBuffer'));
console.log('Restored chart smoke: OK (direct rendering, 7 period/unit modes, comparison, temperature toggles, script syntax)');
