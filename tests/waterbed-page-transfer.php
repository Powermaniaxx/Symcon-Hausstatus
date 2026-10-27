<?php
declare(strict_types=1);
require __DIR__.'/waterbed-history.php';
require __DIR__.'/../HausstatusBedienung/WaterbedSupport.php';
require __DIR__.'/../HausstatusBedienung/PageSupport.php';
class TransferFixture {
 use HausstatusWaterbedSupport,HausstatusPageSupport;
 public int $InstanceID=101,$maximum=0;
 public array $buffers=[],$messages=[];
 private function UsesInlinePages():bool{return true;}
 private function NavigationRooms():array{return ['Schlafzimmer'];}
 private function WaterbedArchiveID():int{return 99;}
 function ConfigInteger($key){return ['WaterbedSvenTemperature'=>30213,'WaterbedSusiTemperature'=>43240,'WaterbedArchive'=>99][$key]??17732;}
 function ConfigString($key){return 'Wh';}
 function Read($id){return ['raw'=>51,'text'=>'51 W'];}
 function ReadPropertyInteger($key){return $key==='View'?12:0;}
 function GetBuffer($key){return $this->buffers[$key]??'';}
 function SetBuffer($key,$value){$this->maximum=max($this->maximum,strlen($value));if(strlen($value)>262144)throw new RuntimeException('Native Symcon buffer warning');$this->buffers[$key]=$value;}
 function SendDebug(...$args){}
 function UpdateVisualizationValue($value){$this->messages[]=json_decode($value,true,512,JSON_THROW_ON_ERROR);}
 private function State():array{
  $state=['View'=>$this->CurrentView(),'PageRoom'=>$this->CurrentRoom(),'Controls'=>[],'WaterbedEnergy'=>$this->WaterbedEnergyState()];
  $this->WriteLargeBuffer('LastState',json_encode($state,JSON_THROW_ON_ERROR));return $state;
 }
 function request($id,$reason){$this->HandlePageRequest('PageRead',json_encode(['request'=>$id,'view'=>12,'room'=>'Schlafzimmer','reason'=>$reason]));return end($this->messages);}
}
$powerCounter=false;$energyCounter=true;$missing=[];$rawRows=[];$rawCalls=[];
$start=(new DateTimeImmutable('now',new DateTimeZone('Europe/Berlin')))->modify('first day of this month')->setTime(0,0)->getTimestamp();
for($i=0;$i<45000;$i++)$rawRows[]=['TimeStamp'=>$start+$i,'Value'=>$i%2?51:260];
$f=new TransferFixture();$room=$f->request('page-open','navigate');
check(!isset($room['error'])&&$room['state']['WaterbedEnergy']['historyLoaded']===false,'Room navigation succeeds without history');
check(count($rawCalls)===0,'Navigation does not read archive');
$reply=$f->request('page-chart','history');
check(!isset($reply['error'])&&$reply['pageReply']==='page-chart','History response is correlated');
check($reply['state']['WaterbedEnergy']['historyLoaded']===true,'History loaded through actual page request');
check(count($reply['state']['WaterbedEnergy']['history']['month']['series']['w'])>=45000,'Transfer preserves every heating transition');
check($f->maximum<=262144,'No native buffer warning in chart cache, page cache or last state');
$reply=$f->request('page-refresh','background');check($reply['state']['WaterbedEnergy']['historyLoaded']===true,'Subsequent refresh retains cached diagram');
echo 'waterbed-page-transfer: OK (real PageRead → State → archive/cache → JSON reply; largest buffer '.$f->maximum." bytes)\n";
