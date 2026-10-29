<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/RoomSupport.php';
$objects=[100=>['name'=>'HomeMatic Systemvariablen','type'=>1,'parent'=>0],101=>['name'=>'STATE','type'=>2,'parent'=>100],200=>['name'=>'Technischer Bewegungsmelder','type'=>1,'parent'=>0],201=>['name'=>'MOTION','type'=>2,'parent'=>200],301=>['name'=>'Wohnzimmer Bewegung','type'=>2,'parent'=>300]];
$queries=[];
function IPS_ObjectExists($id){return isset($GLOBALS['objects'][$id]);}
function IPS_InstanceExists($id){return isset($GLOBALS['objects'][$id])&&$GLOBALS['objects'][$id]['type']===1;}
function IPS_GetObject($id){return ['ObjectType'=>$GLOBALS['objects'][$id]['type'],'ObjectPosition'=>0,'ObjectIsHidden'=>false,'ObjectIsDisabled'=>false,'ObjectIdent'=>$GLOBALS['objects'][$id]['name']];}
function IPS_GetName($id){return $GLOBALS['objects'][$id]['name'];}
function IPS_GetParent($id){return $GLOBALS['objects'][$id]['parent'];}
function IPS_GetChildrenIDs($id){return $id===200?[201]:[];}
function IPS_VariableExists($id){return isset($GLOBALS['objects'][$id])&&$GLOBALS['objects'][$id]['type']===2;}
function IPS_GetVariable($id){return ['VariableType'=>0,'VariableChanged'=>time()-120];}
function IPS_GetLocation($id){return 'Haus/Terrasse/'.$id;}
function AC_GetLoggingStatus($archive,$id){return true;}
function AC_GetLoggedValues($archive,$id,$from,$until,$limit){$GLOBALS['queries'][]=$id;return $from===0?[['TimeStamp'=>$until,'Value'=>false]]:[['TimeStamp'=>$from+60,'Value'=>true],['TimeStamp'=>$until-300,'Value'=>false]];}
$source=file_get_contents(__DIR__.'/../HausstatusBedienung/module.php');$start=strpos($source,'    private function MotionState(');$end=strpos($source,'    // Testversion:', $start);$motion=substr($source,$start,$end-$start);
eval('class TerraceFixture {
 use HausstatusRoomSupport;
 public $room="Terrasse",$buffers=[],$entries=[];
 function CurrentView(){return 12;} function CurrentRoom(){return $this->room;}
 function ConfigString($key){return $key==="RoomEntries"?json_encode($this->entries):"";}
 function ConfigInteger($key){return 0;}
 function IsCinemaVariable($id){return false;} function IsHeosSelection(int $id):bool{return false;} function IsRoomSetpoint($entry){return false;}
 function RoomWebContent(int $id):bool{return false;}
 function MotionSensors(){return [["Name"=>"Alte Beschriftung","Variable"=>201],["Name"=>"Wohnzimmer","Variable"=>301]];}
 function MotionArchive(){return 99;} function Read($id){return ["raw"=>$id===201,"text"=>"Status"];}
 function GetBuffer($key){return $this->buffers[$key]??"";} function SetBuffer($key,$value){$this->buffers[$key]=$value;}
 function expanded(){return $this->ExpandedRoomEntries($this->room);} function motion(){return $this->RoomMotionState();} function home(){return $this->MotionState();}
 '.$motion.' }');
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=new TerraceFixture();$f->entries=[['Room'=>'Terrasse','Group'=>'Licht','Name'=>'Terrassenlicht','Target'=>101,'Operate'=>true],['Room'=>'Terrasse','Group'=>'Sensoren','Name'=>'Bewegung Terrasse','Target'=>200,'Operate'=>false]];
$items=$f->expanded();check($items[0]['deviceName']==='Terrassenlicht','Editable name overrides generic parent name');check($items[0]['id']===101&&$items[0]['operate']===true,'Control ID and permission unchanged');check($items[1]['deviceName']==='Bewegung Terrasse','Instance label retained when expanded');
$motion=$f->motion();check(count($motion['sensors'])===1&&$motion['sensors'][0]['variable']===201,'Only terrace motion included');check($motion['sensors'][0]['name']==='Bewegung Terrasse','Configured room label used in history');check(count($motion['sensors'][0]['history']['entries'])===2,'Same archive history format as homepage');check(array_unique($queries)===[201],'No unrelated archive queried');
$home=$f->home();check(count($home['sensors'])===2,'Homepage keeps all configured sensors');check(isset($f->buffers['MotionCache']),'Homepage cache remains separate');
$f->room='Wohnzimmer';$count=count($queries);check($f->motion()===null&&count($queries)===$count,'No extra queries in other rooms');
echo "room-terrace: OK (editable captions, source/action preservation, shared motion history, scoped archive queries, homepage isolation)\n";
