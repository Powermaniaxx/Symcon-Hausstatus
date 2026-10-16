'use strict';
const fs=require('fs'),vm=require('vm'),assert=require('assert/strict');
const html=fs.readFileSync(__dirname+'/../HausstatusBedienung/module.html','utf8');
const code=html.slice(html.indexOf('function applyHomepagePart('),html.indexOf('// Built-in illustrated avatars.'));
const ids=['cards','weatherRoomsRow','weatherOutdoorPair','roomsHeaterPair','motionSection','roomDeviceSections','extraTileSection','heatingProfileSection','networkSection','dockerSection','presenceHistorySection','roomNavigation','inlineRooms'];
const nodes=Object.fromEntries(ids.map(id=>[id,{hidden:false,children:[]}]));
const context={el:id=>nodes[id],document:{body:{classList:{toggle(){}}}}};vm.createContext(context);vm.runInContext(code,context);
for(let part=1;part<=12;part++){
 for(const n of Object.values(nodes))n.hidden=false;
 nodes.cards.children=['entry','energy','laundry','light','cinema','shield-schlafzimmer','shield-ankleidezimmer'].map(name=>({id:'card-'+name,hidden:false}));
 context.applyHomepagePart({HomepagePart:part,View:0});
 assert.equal(nodes.cards.children.filter(n=>!n.hidden).length,part<=7?1:0);
 assert.equal(nodes.weatherRoomsRow.hidden,![8,9].includes(part));
 assert.equal(nodes.weatherOutdoorPair.hidden,part!==8);assert.equal(nodes.roomsHeaterPair.hidden,part!==9);
 assert.equal(nodes.roomDeviceSections.hidden,part!==11);
}
nodes.cards.hidden=false;context.applyHomepagePart({HomepagePart:5,View:7});assert.equal(nodes.cards.hidden,false,'Inline PV/detail pages must remain available');
console.log('Homepage section visibility and detail navigation: passed');
