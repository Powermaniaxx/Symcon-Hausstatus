<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/WaterbedSupport.php';
$archiveLookups=0;
function IPS_VariableExists($id){return $id>0;}
function IPS_GetInstanceListByModuleID($id){$GLOBALS['archiveLookups']++;return [];}
class PageFixture {
 use HausstatusWaterbedSupport;
 public $pageContext=null,$buffers=[],$fail=false,$room='Schlafzimmer',$readCount=0;
 function CurrentView(){return 12;}
 function CurrentRoom(){return $this->room;}
 function ConfigInteger($name){return ['WaterbedArchive'=>0,'WaterbedSvenTemperature'=>30213,'WaterbedSusiTemperature'=>43240][$name]??17732;}
 function ConfigString($name){return 'kWh';}
 function Read($id){$this->readCount++;return ['raw'=>51,'text'=>'51 W'];}
 function GetBuffer($key){if($key==='WaterbedChartCache'&&$this->fail)throw new RuntimeException('Test: Archiv nicht verfügbar');return $this->buffers[$key]??'';}
 function SetBuffer($key,$value){$this->buffers[$key]=$value;}
 function SendDebug(...$args){}
 function run(){return $this->WaterbedEnergyState();}
}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=new PageFixture();$result=$f->run();
check(is_array($result['history'])&&$result['values']['power']['raw']===51,'Initial state directly includes chart and current values');
check(!array_key_exists('historyLoaded',$result),'No deferred loader state');
$f->fail=true;$f->buffers=[];$result=$f->run();
check($result['history']===null&&str_contains($result['note'],'Test: Archiv nicht verfügbar'),'History failure stays inside waterbed panel');
check($result['values']['power']['raw']===51,'Current values survive history failure');
$f->fail=false;$result=$f->run();check(is_array($result['history']),'Next ordinary state read retries without a special request');
$calls=$archiveLookups;$result=$f->run();check(is_array($result['history']),'Repeated direct state remains readable');
$f->room='Wohnzimmer';check($f->run()===null,'Other rooms unaffected');
echo "waterbed-page-loading: OK (direct initial chart, contained errors, ordinary retry, other rooms)\n";
