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
check($archiveLookups===0,'Opening bedroom performs no archive lookup');
check($result['historyLoaded']===false&&$result['values']['power']['raw']===51,'Immediate current values, deferred history');
$f->pageContext=['waterbedHistory'=>true];$f->fail=true;$result=$f->run();
check(isset($result['historyError'])&&$result['historyLoaded']===false,'History exception is contained in waterbed panel');
check($result['values']['power']['raw']===51,'Current readings survive failed history');
$f->fail=false;$result=$f->run();check($result['historyLoaded']===true,'Explicit history request can load');
$calls=$archiveLookups;$f->pageContext=null;$result=$f->run();check($result['historyLoaded']===true&&$archiveLookups===$calls,'Cached history never blocks room refresh');
$cache=json_decode($f->buffers['WaterbedPageCache'],true);$cache['until']=time()-1;$f->buffers['WaterbedPageCache']=json_encode($cache);$result=$f->run();
check($result['historyLoaded']===false&&$archiveLookups===$calls,'Expired history does not trigger archive work on navigation');
$f->room='Wohnzimmer';check($f->run()===null,'Other rooms unaffected');
echo "waterbed-page-loading: OK (no archive on navigation, isolated errors, cached/expired data, other rooms)\n";
