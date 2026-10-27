const assert=require('node:assert/strict'),fs=require('node:fs'),vm=require('node:vm');
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
class E{constructor(tag){this.tag=tag;this.children=[];this.events={};this.style={};this.textContent='';}append(...n){this.children.push(...n);}replaceChildren(...n){this.children=n;}setAttribute(){}addEventListener(k,f){this.events[k]=f;}}
const all=n=>[n,...n.children.flatMap(all)];
const ctx={document:{createElement:t=>new E(t),createElementNS:(ns,t)=>new E(t)},text:(n,t)=>n.textContent=String(t)};
vm.createContext(ctx);vm.runInContext(html.slice(html.indexOf('function renderWaterbedYearComparison('),html.indexOf('function deviceItems(')),ctx);
const data={values:{power:{raw:51,text:'51 W'}},history:null,annual:null};
let panel=ctx.renderWaterbedEnergy(data);
assert(all(panel).some(n=>n.textContent==='51 W'));assert(all(panel).some(n=>n.tag==='svg'),'Chart appears in initial render without any requestAction or timer API');
assert(!all(panel).some(n=>n.textContent==='Diagramm und Tageswerte laden'),'No second loader button');
ctx.renderWaterbedChart=()=>{throw Error('Malformed archive');};panel=ctx.renderWaterbedEnergy(data);assert(all(panel).some(n=>n.textContent==='51 W'));assert(all(panel).some(n=>n.textContent.includes('Malformed archive')),'Chart error contained');
assert(!html.includes('waterbedAutoHistoryRequested'));assert(!html.includes("'history')"));
const php=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.php','utf8');assert(php.includes("str_replace('<!--HAUSSTATUS_RELEASE-->'"));assert(html.includes('Hausstatus · v<!--HAUSSTATUS_RELEASE-->'));
console.log('waterbed-page-loading: OK (direct chart rendering, no additional bridge request or timer, error containment, shared release marker)');
