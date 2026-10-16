'use strict';
const assert=require('node:assert/strict');
process.env.SHIELD_ADB_HOME=require('node:fs').mkdtempSync(require('node:path').join(require('node:os').tmpdir(),'shield-power-test-'));
const {executePower}=require('../docs/integrations/shield/poll.js');
(async()=>{
 const calls=[];const run=async args=>calls.push(args);
 const command={id:'a'.repeat(24),action:'wake',expires:Math.floor(Date.now()/1000)+40};
 assert.equal(await executePower(command,run),true);
 assert.deepEqual(calls[0],['-s','192.168.178.107:5555','shell','input','keyevent','224']);
 await executePower({...command,action:'sleep'},run);
 assert.equal(calls[1].at(-1),'223');
 assert.equal(await executePower({...command,expires:1},run),false);
 assert.equal(await executePower(null,run),false);
 await assert.rejects(executePower({...command,action:'reboot'},run),/Ungültiger/);
 await assert.rejects(executePower({...command,id:'; shell'},run),/Ungültiger/);
 assert.equal(calls.length,2);
 console.log('Shield explicit wake/sleep: passed');
})().catch(error=>{console.error(error);process.exitCode=1;});
