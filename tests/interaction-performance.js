'use strict';
const fs=require('node:fs'),vm=require('node:vm'),assert=require('node:assert/strict');
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
const script=html.slice(html.indexOf('<script>')+8,html.indexOf('</script>'));
new vm.Script(script); // Check the whole frontend, not just the extracted functions.
let timers=new Map(),next=0,calls=[],renders=0,status='',controls=[{disabled:false}];
const elements=new Map();
const ctx={console,Math,Date,JSON,Array,Object,Number,String,
 document:{visibilityState:'visible',addEventListener(){},querySelectorAll(){return controls;}},
 setTimeout(fn,delay){timers.set(++next,{fn,delay});return next;},clearTimeout(id){timers.delete(id);},
 requestAction(...args){calls.push(args);},showStatus(value){status=value;},render(){renders++;},
 el(id){if(!elements.has(id))elements.set(id,{setAttribute(){}});return elements.get(id);},text(){},
 receiveState(message){if(message.Controls)ctx.state=message;},receivePageReply(){},readInlinePage(){calls.push(['PageRead']);}
};
vm.createContext(ctx);
vm.runInContext(`let busy=false,pendingTimer=null,timeoutTimer=null,editing=false,messageTimer=null;
 let commandRequest=null,settleTimers=[],lockedControls=[],inlinePage=null,pageRequest=null,refreshRequest=null,refreshTimer=null;
 let showingRooms=false,homeState={View:0},state={View:0,Controls:{}},doorRequest=null;
 ${script.slice(script.indexOf('function cancelSettling()'),script.indexOf("document.addEventListener('visibilitychange',()=>{if(document.visibilityState!=='visible')cancelSettling();});"))}
 ${script.slice(script.indexOf('function send(command,value)'),script.indexOf('function prepareDoor()'))}
 ${script.slice(script.indexOf('handleMessage=function(data)'),script.indexOf('const renderContents=render;'))}
`,ctx);
const run=code=>vm.runInContext(code,ctx);
const fire=delay=>{for(const [id,t] of [...timers])if(t.delay===delay){timers.delete(id);t.fn();}};
run("send('Light',true)");
assert.equal(renders,0,'dispatch must not wait for a complete render');
assert.equal(controls[0].disabled,true);
assert.match(calls[0][0],/^Command:[a-z0-9-]+:Light$/);assert.equal(calls[0][1],true);
const request=run('commandRequest');
ctx.handleMessage({commandDone:true,commandRequest:'another-browser'});
assert.equal(run('busy'),true,'mirror reply must not unblock this command');
ctx.handleMessage({Controls:{},View:0});assert.equal(run('busy'),true,'status is not a command acknowledgement');
assert.equal([...timers.values()].some(t=>t.delay===1200),false,'no speculative full refresh while a command runs');
ctx.handleMessage({commandDone:true,commandRequest:request});
assert.equal(run('busy'),false);assert.equal(renders,1);
assert.equal(controls[0].disabled,false,'static confirmation buttons are restored too');
for(const delay of [400,1500,3500])fire(delay);
assert.equal(calls.filter(c=>c[0]==='Refresh'&&c[1]===false).length,3);
assert.equal(timers.size,0,'no continuing command poll');
ctx.handleMessage({commandDone:true,commandRequest:request});assert.equal(timers.size,0,'late ack ignored');
run("send('RoomValue',{id:42,value:17})");assert.match(calls.at(-1)[0],/:RoomValue:42$/);assert.equal(calls.at(-1)[1],17);
fire(8000);assert.equal(run('busy'),false);assert.match(status,/Best/);
run("send('DoorOpen',{request:'challenge',token:'secret'})");
assert.match(calls.at(-1)[0],/:DoorOpen$/);assert.equal(calls.at(-1)[1],'{"request":"challenge","token":"secret"}');
ctx.handleMessage({commandDone:true,commandRequest:run('commandRequest')});
ctx.document.visibilityState='hidden';const count=calls.length;
for(const delay of [400,1500,3500])fire(delay);assert.equal(calls.length,count,'hidden view causes no post-command requests');
console.log('Interaction performance: dispatch, mirrors, timeout, bounded/hidden reads, scalar routes OK');
