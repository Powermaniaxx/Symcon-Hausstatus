const fs=require('fs'),vm=require('vm'),assert=require('node:assert/strict');
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');for(const m of html.matchAll(/<script[^>]*>([\s\S]*?)<\/script>/g))new vm.Script(m[1]);
const extract=name=>{const a=html.indexOf('function '+name+'('),b=html.indexOf('\nfunction ',a+1);return html.slice(a,b);};
const code=['presenceWeekDetails','renderPresenceHistory','motionTime','rememberPanel'].map(extract).join('\n');
class E{constructor(tag){this.tag=tag;this.children=[];this.attrs={};this.events={};this.style={};this.textContent='';this.open=false;}append(...nodes){this.children.push(...nodes);}replaceChildren(...nodes){this.children=nodes;}setAttribute(k,v){this.attrs[k]=String(v);}addEventListener(k,f){(this.events[k]??=[]).push(f);}toggle(){this.open=!this.open;for(const f of this.events.toggle??[])f();}}
const root=new E('div'),ctx={document:{createElement:t=>new E(t)},el:()=>root,text:(n,v)=>n.textContent=String(v),defaultPresenceAvatar:()=>new E('svg'),openPanels:new Set()};vm.createContext(ctx);vm.runInContext(code,ctx);const all=n=>[n,...n.children.flatMap(all)];
const dayStart=Date.parse('2026-10-25T00:00:00+02:00')/1000,dayEnd=Date.parse('2026-10-26T00:00:00+01:00')/1000;
const boundaries=Array.from({length:8},(_,i)=>Date.parse('2026-10-'+String(23+i)+'T00:00:00'+(i<3?'+02:00':'+01:00'))/1000);
const days=boundaries.slice(0,7).map((start,i)=>({start,end:boundaries[i+1],until:boundaries[i+1],label:String(23+i)+'.10.',today:i===6}));days[6].until=days[6].start+3600;
const history={previous:false,entries:[{time:dayStart+12*3600,active:true}],note:''};
const people=[['Presence','Allgemeine Anwesenheit','house'],['GoogleHomePresence','Google Home','house'],['SvenPresence','Sven','person'],['SusiPresence','Susi','person'],['Alarm','Alarm innen','alarm']].map(([source,name,kind])=>({source,name,kind,current:{raw:true,text:'Anwesend'},avatar:{src:null,note:''},history:{previous:false,entries:[],note:''},weekHistory:history}));
const data={start:dayEnd,end:days[6].until,week:{start:dayStart,end:days[6].until,days},people};ctx.renderPresenceHistory(data);let panels=all(root).filter(n=>n.className==='presenceWeek');assert.equal(panels.length,5);assert(panels.every(p=>!p.open&&p.children[1].children.length===0),'Closed weekly content is lazy without an extra data request');
for(const panel of panels){panel.toggle();assert.equal(all(panel).filter(n=>n.className==='presenceDay').length,7);}
const first=all(panels[0]).filter(n=>n.className==='presenceDay')[2],bars=all(first).filter(n=>n.className?.startsWith('presenceSegment'));assert(Math.abs(parseFloat(bars[0].style.width)-48)<.001,'25-hour day uses actual duration');assert(all(panels.find(p=>p.children[0].attrs['aria-label'].includes('Alarm innen'))).some(n=>n.textContent==='Scharf'),'Alarm wording remains correct');
const last=all(panels[0]).filter(n=>n.className==='presenceDay').at(-1);assert(all(last).filter(n=>n.className?.startsWith('presenceSegment')).every(n=>parseFloat(n.style.width)<=100/24+.01),'No future today segment');
ctx.renderPresenceHistory(data);panels=all(root).filter(n=>n.className==='presenceWeek');assert(panels.every(p=>p.open),'Expanded panels survive normal refresh');
assert.equal(all(root).filter(n=>n.attrs['aria-label']?.includes('letzten 24 Stunden')).length,5,'Existing daily overview retained');
if(process.env.PRESENCE_FIXTURE)fs.writeFileSync(process.env.PRESENCE_FIXTURE,JSON.stringify({code,data,css:html.match(/<style[^>]*>([\s\S]*?)<\/style>/)[1]}));
console.log('presence-week UI: OK (every card, seven rows, DST, partial today, alarm labels, expansion persistence, existing 24h view)');
