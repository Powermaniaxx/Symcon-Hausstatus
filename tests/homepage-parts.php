<?php
declare(strict_types=1);
require __DIR__ . '/../HausstatusBedienung/HomepageSupport.php';
const MODULE = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
$objects = [10=>['ObjectType'=>0,'parent'=>0],11=>['ObjectType'=>0,'parent'=>0],52627=>['ObjectType'=>1,'parent'=>11],20=>['ObjectType'=>6,'parent'=>10,'target'=>52627],21=>['ObjectType'=>6,'parent'=>11,'target'=>52627]];
foreach ($objects as &$o) { $o += ['ObjectIdent'=>'','ObjectIsHidden'=>false,'ObjectPosition'=>0,'name'=>'test']; } unset($o);
$props=[52627=>['ConfigSource'=>0,'View'=>0,'HomepageCategory'=>10,'PreserveHomepageStyle'=>true,'SeparateHomepageTiles'=>true,'ShieldCompact'=>false,'ShieldEnabled'=>true,'SeparateDetails'=>false,'WasherPower'=>1,'ExtraTileVariables'=>'[{"id":123}]']];
function IPS_GetObjectList(){return array_keys($GLOBALS['objects']);}
function IPS_GetObject($id){return $GLOBALS['objects'][$id];}
function IPS_ObjectExists($id){return isset($GLOBALS['objects'][$id]);}
function IPS_InstanceExists($id){return ($GLOBALS['objects'][$id]['ObjectType']??null)===1;}
function IPS_LinkExists($id){return ($GLOBALS['objects'][$id]['ObjectType']??null)===6;}
function IPS_GetLink($id){return ['TargetID'=>$GLOBALS['objects'][$id]['target']];}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>MODULE]];}
function IPS_GetInstanceListByModuleID(string $module): array {
    return $module===MODULE ? array_values(array_filter(IPS_GetObjectList(),fn($id)=>IPS_InstanceExists($id))) : [];
}
function IPS_GetProperty($id,$key){return $GLOBALS['props'][$id][$key]??0;}
function IPS_SetProperty($id,$key,$value){$GLOBALS['props'][$id][$key]=$value;}
function IPS_GetParent($id){return $GLOBALS['objects'][$id]['parent'];}
function IPS_GetName($id){return $GLOBALS['objects'][$id]['name'];}
function IPS_SetName($id,$name){$GLOBALS['objects'][$id]['name']=$name;}
function IPS_SetParent($id,$parent){$GLOBALS['objects'][$id]['parent']=$parent;}
function IPS_SetIdent($id,$ident){$GLOBALS['objects'][$id]['ObjectIdent']=$ident;}
function IPS_SetPosition($id,$pos){$GLOBALS['objects'][$id]['ObjectPosition']=$pos;}
function IPS_SetHidden($id,$hidden){$GLOBALS['objects'][$id]['ObjectIsHidden']=$hidden;}
function IPS_GetChildrenIDs($root){return array_keys(array_filter($GLOBALS['objects'],fn($o)=>$o['parent']===$root));}
function IPS_GetObjectIDByIdent($ident,$root){foreach(IPS_GetChildrenIDs($root) as $id){if(IPS_GetObject($id)['ObjectIdent']===$ident)return $id;}return false;}
function IPS_CreateInstance($module){$id=max(array_keys($GLOBALS['objects']))+1;$GLOBALS['objects'][$id]=['ObjectType'=>1,'parent'=>0,'ObjectIdent'=>'','ObjectIsHidden'=>false,'ObjectPosition'=>0,'name'=>''];$GLOBALS['props'][$id]=['View'=>0,'ConfigSource'=>0];return $id;}
function IPS_ApplyChanges($id){}
function check($yes,$message){if(!$yes)throw new RuntimeException($message);}
class HomeFixture {
 use HausstatusHomepageSupport;
 public int $InstanceID=52627;
 public array $attributes=['HomepageLayout'=>''];
 public array $buffers=[];
 public function run(){ $this->OrganizeSeparateHomepage(); }
 private function ReadPropertyInteger($key){return (int)IPS_GetProperty($this->InstanceID,$key);}
 private function ReadPropertyBoolean($key){return (bool)IPS_GetProperty($this->InstanceID,$key);}
 private function ReadPropertyString($key){return (string)IPS_GetProperty($this->InstanceID,$key);}
 private function ReadAttributeString($key){return $this->attributes[$key];}
 private function WriteAttributeString($key,$value){$this->attributes[$key]=$value;}
 private function GetBuffer($key){return $this->buffers[$key]??'';}
 private function SetBuffer($key,$value){$this->buffers[$key]=$value;}
 private function RegisterReference($id){}
 private function SendDebug(...$args){}
 private function ConfigInteger($key){return $this->ReadPropertyInteger($key);}
 private function ConfigBoolean($key){return $this->ReadPropertyBoolean($key);}
 private function MotionSensors(){return [1];}
 private function RoomSections(){return [1];}
 private function ExtraTileVariables(){return [1];}
}
$m=new HomeFixture();$m->run();
check(count($objects)===17,'Expected twelve separate sections');
check(IPS_GetObject(20)['ObjectIsHidden']===true,'Selected combined link must be hidden');
check(IPS_GetObject(21)['ObjectIsHidden']===false,'Other visualization must keep its master link');
foreach(range(1,12) as $part){$id=IPS_GetObjectIDByIdent('SVHSHomePart_'.$part,10);check($id!==false && IPS_GetProperty($id,'HomepagePart')===$part && IPS_GetProperty($id,'ConfigSource')===52627,'Section/source mismatch');check(!IPS_GetObject($id)['ObjectIsHidden'],'Section must be visible');}
$extra=IPS_GetObjectIDByIdent('SVHSHomePart_12',10);check(IPS_GetProperty($extra,'ExtraTileVariables')==='[{"id":123}]','Custom variables must be copied');
$m->run();check(count($objects)===17,'Repeated setup duplicated tiles');
$props[52627]['SeparateHomepageTiles']=false;$m->run();
check(!IPS_GetObject(20)['ObjectIsHidden'] && !IPS_GetObject(21)['ObjectIsHidden'],'Combined presentation not restored');
foreach(range(1,12) as $part){check(IPS_GetObject(IPS_GetObjectIDByIdent('SVHSHomePart_'.$part,10))['ObjectIsHidden'],'Separate section remained visible');}
$props[52627]['SeparateHomepageTiles']=true;
$collision=IPS_GetObjectIDByIdent('SVHSHomePart_5',10);$props[$collision]['ConfigSource']=999;
$m->run();check(!IPS_GetObject(20)['ObjectIsHidden'],'Collision must preserve combined homepage');
check(str_contains($m->buffers['HomepageLayoutError'],'belegt'),'Collision diagnostic missing');
echo "Homepage parts: idempotency, shared source, custom rows, rollback and collision protection passed\n";
