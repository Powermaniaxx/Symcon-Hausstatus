<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/PresenceHistorySupport.php';
$queries=[];$dense=false;$missing=[];
function IPS_InstanceExists($id){return $id===99;}
function IPS_VariableExists($id){return $id>0;}
function IPS_GetVariable($id){return ['VariableType'=>0];}
function AC_GetLoggingStatus($archive,$id){return !in_array($id,$GLOBALS['missing'],true);}
function AC_GetLoggedValues($archive,$id,$from,$until,$limit){
 $GLOBALS['queries'][]=[$id,$from,$until,$limit];
 if($from===0)return [['TimeStamp'=>$until-60,'Value'=>false]];
 if($GLOBALS['dense']){return array_map(fn($i)=>['TimeStamp'=>$until-$i,'Value'=>$i%2===0],range(1,$limit));}
 return [['TimeStamp'=>$until-3600,'Value'=>false],['TimeStamp'=>$until-2*86400,'Value'=>true]];
}
class Fixture {
 use HausstatusPresenceHistorySupport;
 public array $buffers=[];
 public int $maximum=0;
 function ConfigInteger($key){return ['PresenceArchive'=>99,'Presence'=>52726,'SvenPresence'=>111,'SusiPresence'=>222,'Alarm'=>333][$key]??0;}
 function ConfigString($key){return $key==='AdditionalAlarmVariables'?'[{"Name":"Alarm außen","Variable":444}]':'';}
 function GetBuffer($key){return $this->buffers[$key]??'';}
 function SetBuffer($key,$value){$this->maximum=max($this->maximum,strlen($value));if(strlen($value)>262144)throw new RuntimeException('Native buffer warning');$this->buffers[$key]=$value;}
 function Read($id){return ['raw'=>false,'text'=>'Abwesend'];}
 function run(){return $this->PresenceHistoryState();}
}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=new Fixture();$data=$f->run();check(count($data['week']['days'])===7,'Seven local calendar days including today');check($data['start']===$data['end']-86400,'Existing 24-hour display retained');
$today=end($data['week']['days']);check($today['today']===true&&$today['until']===$data['end']&&$today['until']<$today['end'],'Today stops at now, no future history');
foreach($data['people'] as $person){check(count($person['weekHistory']['entries'])===2,'Seven-day data for every source');check($person['history']['previous']===true&&count($person['history']['entries'])===1,'Correct carried state at 24-hour boundary');}
check(count($data['people'])===6,'House, Google Home, two people and all configured alarms');check(count($queries)===10,'Shared presence variable queried once');foreach($queries as $q){if($q[1]!==0)check($q[1]===$data['week']['start'],'Archive query starts six local midnights before today');}
$calls=count($queries);$f->run();check(count($queries)===$calls,'Repeated state reuses archive cache');
for($i=1;$i<7;$i++)check($data['week']['days'][$i-1]['end']===$data['week']['days'][$i]['start'],'Calendar rows join exactly across time changes');
$dense=true;$f->buffers=[];$data=$f->run();foreach($data['people'] as $person){check($person['weekHistory']['previous']===null,'Truncated old interval remains unknown');check(str_contains($person['weekHistory']['note'],'Viele Statuswechsel'),'Limit is visible');}check($f->maximum<=196608,'Cache stays below native buffer warning');
$dense=false;$missing=[222];$f->buffers=[];$data=$f->run();$susi=array_values(array_filter($data['people'],fn($p)=>$p['source']==='SusiPresence'))[0];check($susi['weekHistory']['entries']===[]&&str_contains($susi['weekHistory']['note'],'nicht aktiviert'),'Missing archive is explained without fabricated history');
echo "presence-week: OK (all sources, deduplication, 24-hour carry, calendar boundaries, partial today, cache, bounded payload, missing data)\n";
