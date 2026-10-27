<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/NavigationSupport.php';
require __DIR__.'/../HausstatusBedienung/PageSupport.php';
$module='{9E33E109-4881-4E78-9906-38CAC2F1E210}';
$names=[100=>'Hausstatus',101=>'Räume · v0.120',102=>'Wohnzimmer · v0.121',200=>'Räume · v0.120',201=>'Räume · v0.120',202=>'Andere Ansicht',203=>'Zyklus',204=>'Zyklus',300=>'Fremdes Modul',400=>'Räume · v0.120'];
$props=[100=>['ConfigSource'=>0,'View'=>0,'RoomFilter'=>''],101=>['ConfigSource'=>100,'View'=>12,'RoomFilter'=>'Schlafzimmer'],102=>['ConfigSource'=>100,'View'=>12,'RoomFilter'=>'Wohnzimmer'],300=>['ConfigSource'=>999,'View'=>12]];
$links=[200=>101,201=>200,202=>300,203=>204,204=>203];$renamed=[];
function IPS_GetObjectList(){return array_keys($GLOBALS['names']);}
function IPS_GetName($id){return $GLOBALS['names'][$id];}
function IPS_SetName($id,$name){$GLOBALS['names'][$id]=$name;$GLOBALS['renamed'][]=$id;}
function IPS_GetProperty($id,$key){return $GLOBALS['props'][$id][$key]??0;}
function IPS_InstanceExists($id){return isset($GLOBALS['props'][$id]);}
function IPS_VariableExists($id){return false;}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>$GLOBALS['module']]];}
function IPS_LinkExists($id){return isset($GLOBALS['links'][$id]);}
function IPS_GetLink($id){return ['TargetID'=>$GLOBALS['links'][$id]];}
function IPS_GetChildrenIDs($id){return [200];}
function IPS_GetObject($id){return ['ObjectIsHidden'=>false];}
class RoomFixture{
 use HausstatusNavigationSupport,HausstatusPageSupport;
 public $InstanceID=100;
 function ReadPropertyInteger($key){return (int)IPS_GetProperty($this->InstanceID,$key);}
 function ReadPropertyString($key){return (string)IPS_GetProperty($this->InstanceID,$key);}
 private function RoomNavigationTarget():int{return 999;}
 function version(){return $this->HausstatusReleaseVersion();}
 function update(){return $this->VersionHausstatusPresentationLinks();}
 function room(){return $this->CurrentRoom();}
}
function check($ok,$message){if(!$ok)throw new RuntimeException($message);}
$f=new RoomFixture();$f->InstanceID=101;
check($f->room()==='','Versioned Rooms instance is still a landing page');
$names[101]='Schlafzimmer';check($f->room()==='','Versioned Rooms link is also recognized');
$names[200]='Schlafzimmer';check($f->room()==='Schlafzimmer','Ordinary detail page keeps configured room');
$names[101]='Räume · v0.120';$names[200]='Räume · v0.120';$f->InstanceID=100;$f->update();$version=$f->version();
foreach([100,101,102,200,201] as $id)check(str_ends_with($names[$id],' · v'.$version),'Owned page/link updated independent of location');
check($names[101]==='Räume · v'.$version,'Old version is replaced, not appended');
check($names[202]==='Andere Ansicht'&&$names[300]==='Fremdes Modul'&&$names[400]==='Räume · v0.120','Foreign links/instances and categories unchanged');
check($links[200]===101&&$links[201]===200,'Targets unchanged');
$count=count($renamed);$f->update();check(count($renamed)===$count,'Version update is idempotent');
$f->InstanceID=101;check($f->room()==='','Navigation also works after renaming');
echo "room-version-navigation: OK (old/new versioned room menus, all owned locations, link chains, cycles, foreign objects, idempotence)\n";
