<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/NavigationSupport.php';
require __DIR__.'/../HausstatusBedienung/PageSupport.php';
$names=[100=>'Hausstatus',101=>'Räume · v0.120',102=>'Schlafzimmer · v0.128',103=>'Flur · v0.120',104=>'Keller · v0.120',105=>'Terrasse · v0.128',201=>'Räume · v0.120',202=>'Keller · v0.120',300=>'Fremde Seite',301=>'Fremder Link',400=>'Ordner'];
$props=[100=>['ConfigSource'=>0,'View'=>0],101=>['ConfigSource'=>100,'View'=>12,'RoomFilter'=>'Schlafzimmer'],102=>['ConfigSource'=>100,'View'=>12],103=>['ConfigSource'=>100,'View'=>12],104=>['ConfigSource'=>100,'View'=>12,'ActiveView'=>false],105=>['ConfigSource'=>100,'View'=>12],300=>['ConfigSource'=>999,'View'=>12]];
$links=[201=>101,202=>104,301=>300];$writes=[];
function IPS_GetObjectList(){return array_keys($GLOBALS['names']);}
function IPS_GetName($id){return $GLOBALS['names'][$id];}
function IPS_SetName($id,$name){$GLOBALS['names'][$id]=$name;$GLOBALS['writes'][]=$id;}
function IPS_GetProperty($id,$key){return $GLOBALS['props'][$id][$key]??0;}
function IPS_InstanceExists($id){return isset($GLOBALS['props'][$id]);}
function IPS_VariableExists($id){return false;}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>'{9E33E109-4881-4E78-9906-38CAC2F1E210}']];}
function IPS_LinkExists($id){return isset($GLOBALS['links'][$id]);}
function IPS_GetLink($id){return ['TargetID'=>$GLOBALS['links'][$id]];}
class Fixture {
 use HausstatusNavigationSupport,HausstatusPageSupport;
 public $InstanceID=100;
 function ReadPropertyInteger($key){return (int)IPS_GetProperty($this->InstanceID,$key);}
 function ReadPropertyString($key){return (string)IPS_GetProperty($this->InstanceID,$key);}
 function update(){return $this->VersionHausstatusPresentationLinks();}
 function version(){return $this->HausstatusReleaseVersion();}
 function room(){return $this->CurrentRoom();}
}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=new Fixture();$f->update();$version=$f->version();foreach([100,101,102,103,104,105,201,202] as $id)check(str_ends_with($names[$id],' · v'.$version),'Owned title updated: '.$id);
check($names[101]==='Räume · v'.$version,'Previous suffix replaced');check($names[300]==='Fremde Seite'&&$names[301]==='Fremder Link'&&$names[400]==='Ordner','Foreign objects/categories unchanged');
$count=count($writes);$f->update();check(count($writes)===$count,'Repeated calls do not rename again');
$names[101]='Räume · v0.120';$f->InstanceID=101;$f->update();check($names[101]==='Räume · v'.$version,'Child refresh works without master');check($f->room()==='','Versioned Rooms still recognized as landing page');
$source=file_get_contents(__DIR__.'/../HausstatusBedienung/module.php');$apply=substr($source,strpos($source,'public function ApplyChanges()'),strpos($source,'private function HasLiveViewers()')-strpos($source,'public function ApplyChanges()'));
check(strpos($apply,'$this->VersionHausstatusPresentationLinks();')<strpos($apply,"if (!".'$this'."->ReadPropertyBoolean('ActiveView'))"),'Paused pages updated before early return');
check(strrpos($apply,'$this->VersionHausstatusPresentationLinks();')>strpos($apply,'IPS_ApplyChanges($child)'),'Final titles updated after child setup');
echo "subpage-versions: OK (all owned pages/links, paused pages, child refresh, idempotence, room navigation)\n";
