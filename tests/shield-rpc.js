'use strict';
const assert = require('node:assert/strict');
const http = require('node:http');
const {send,blank} = require('../docs/integrations/shield/poll.js');
let reply = {jsonrpc:'2.0',id:1,result:null};
let received;
const server = http.createServer((req,res) => {
    let body=''; req.on('data',part => body+=part);
    req.on('end',() => { received={body:JSON.parse(body),auth:req.headers.authorization}; res.setHeader('Content-Type','application/json'); res.end(JSON.stringify(reply)); });
});
(async () => {
    await new Promise(resolve => server.listen(0,'127.0.0.1',resolve));
    process.env.SYMCON_RPC_URL=`http://127.0.0.1:${server.address().port}/api/`;
    process.env.SYMCON_INSTANCE_ID='12345';
    process.env.SYMCON_RPC_USER='fixture'; process.env.SYMCON_RPC_PASSWORD='local-test';
    try {
        const status=blank('ADB-Timeout'); await send(status);
        assert.equal(received.body.method,'SVHS_UpdateShield');
        assert.equal(received.body.params[0],12345);
        assert.deepEqual(JSON.parse(received.body.params[1]),status);
        assert.equal(received.auth,'Basic '+Buffer.from('fixture:local-test').toString('base64'));
        const command={id:'a'.repeat(24),action:'wake',expires:12345};
        reply={jsonrpc:'2.0',id:1,result:JSON.stringify({command})};
        assert.deepEqual(await send(status),command);
        reply={jsonrpc:'2.0',id:1,error:{code:-1,message:'fixture local-test'}};
        await assert.rejects(send(status),error => {
            assert.match(error.message,/Symcon RPC fehlgeschlagen \(-1\):/);
            assert.ok(!error.message.includes('local-test'));
            assert.ok(!error.message.includes('fixture'));
            return true;
        });
        process.env.SYMCON_INSTANCE_ID='invalid';
        await assert.rejects(send(status),/fehlen/);
        console.log('Shield RPC: 6 checks passed');
    } finally { server.close(); }
})().catch(error => {console.error(error);process.exitCode=1;});
