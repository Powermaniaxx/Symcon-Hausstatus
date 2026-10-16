<?php
declare(strict_types=1);
const MODULE = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
$objects=[];$properties=[];$next=60000;
function seed($id,$type,$parent,$name,$ident='',$hidden=false,$target=0){$GLOBALS['objects'][$id]=['ObjectType'=>$type,'parent'=>$parent,'ObjectName'=>$name,'ObjectIdent'=>$ident,'ObjectIsHidden'=>$hidden,'ObjectPosition'=>$id,'TargetID'=>$target];}
seed(100,0,0,'Visualisierungen');seed(55503,0,100,'Master');
seed(200,0,100,'Visualisierung Sven','MirrorVisualisierungSven');seed(201,0,100,'Visualisierung Susi','MirrorVisualisierungSusi');seed(202,0,100,'Visualisierung Sophie','MirrorVisualisierungSophie');
seed(52627,1,100,'Hausstatus Master','',true);
seed(30001,1,55503,'Medien Wohnzimmer','SVHSHomePart_5');seed(30002,1,55503,'Medien Schlafzimmer','SVHSHomePart_6');
seed(30003,6,55503,'Alte gemeinsame Übersicht','old',true,52627);
seed(30100,0,55503,'Wetter');seed(30101,2,30100,'Außentemperatur');
seed(40001,6,200,'Alter Sven-Link','MirrorLink30003',false,52627);
seed(40002,6,201,'Alter Susi-Link','MirrorLink30003',false,52627);
seed(40003,6,202,'Sophies eigene Kachel','SophieOnly',false,52627);
seed(40004,6,200,'Manuell angelegt','ManualTile',false,30101);
$base=['ConfigSource'=>0,'View'=>0,'HomepagePart'=>0,'ShieldCompact'=>false,'ActiveView'=>true,'RoomFilter'=>'','ExtraTileVariables'=>'[]'];
$properties[52627]=$base;$properties[30001]=array_replace($base,['ConfigSource'=>52627,'HomepagePart'=>5]);$properties[30002]=array_replace($base,['ConfigSource'=>52627,'HomepagePart'=>6]);
$sophie=[$objects[202],$objects[40003]];
function IPS_GetChildrenIDs($parent){return array_keys(array_filter($GLOBALS['objects'],fn($o)=>$o['parent']===$parent));}
function IPS_GetObject($id){return $GLOBALS['objects'][$id];}
function IPS_GetName($id){return IPS_GetObject($id)['ObjectName'];}
function IPS_GetParent($id){return IPS_GetObject($id)['parent'];}
function IPS_ObjectExists($id){return isset($GLOBALS['objects'][$id]);}
function IPS_InstanceExists($id){return (IPS_GetObject($id)['ObjectType']??null)===1;}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>MODULE]];}
function IPS_GetConfiguration($id){return json_encode($GLOBALS['properties'][$id]);}
function IPS_GetProperty($id,$key){return $GLOBALS['properties'][$id][$key]??null;}
function IPS_SetProperty($id,$key,$value){$GLOBALS['properties'][$id][$key]=$value;}
function IPS_GetObjectIDByIdent($ident,$parent){foreach(IPS_GetChildrenIDs($parent) as $id){if(IPS_GetObject($id)['ObjectIdent']===$ident)return $id;}return false;}
function IPS_GetLink($id){return ['TargetID'=>IPS_GetObject($id)['TargetID']];}
function IPS_SetLinkTargetID($id,$target){$GLOBALS['objects'][$id]['TargetID']=$target;}
function create($type){$id=++$GLOBALS['next'];seed($id,$type,0,'');return $id;}
function IPS_CreateCategory(){return create(0);}
function IPS_CreateLink(){return create(6);}
function IPS_CreateInstance($module){$id=create(1);$GLOBALS['properties'][$id]=$GLOBALS['base'];return $id;}
function IPS_SetParent($id,$parent){$GLOBALS['objects'][$id]['parent']=$parent;}
function IPS_SetIdent($id,$ident){$GLOBALS['objects'][$id]['ObjectIdent']=$ident;}
function IPS_SetName($id,$name){$GLOBALS['objects'][$id]['ObjectName']=$name;}
function IPS_SetPosition($id,$pos){$GLOBALS['objects'][$id]['ObjectPosition']=$pos;}
function IPS_SetHidden($id,$hidden){$GLOBALS['objects'][$id]['ObjectIsHidden']=$hidden;}
function IPS_ApplyChanges($id){}
function IPS_SemaphoreEnter(...$args){return true;}
function IPS_SemaphoreLeave(...$args){}
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$_IPS=['SENDER'=>'TimerEvent'];ob_start();include __DIR__.'/../docs/Kachelvisualisierung-Spiegelung.php';$output=ob_get_clean();
check($output==='', 'Automatic invocation must be silent');
$sven=IPS_GetObjectIDByIdent('MirrorLink30001',200);check(IPS_GetLink($sven)['TargetID']===30001,'Sven must mirror current original section');
$susi=IPS_GetObjectIDByIdent('MirrorLink30001',201);$clone=IPS_GetLink($susi)['TargetID'];
check($clone!==30001 && IPS_GetProperty($clone,'ConfigSource')===52627 && IPS_GetProperty($clone,'HomepagePart')===5 && IPS_GetProperty($clone,'ShieldCompact')===true,'Susi must receive separate compact section');
check($properties[30001]['ShieldCompact']===false && $properties[52627]['ShieldCompact']===false,'Master/Sven must remain detailed');
check(IPS_GetObject(40001)['ObjectIsHidden'] && IPS_GetObject(40002)['ObjectIsHidden'],'Obsolete owned combined links must be hidden');
check(!IPS_GetObject(40004)['ObjectIsHidden'],'Manual links must remain visible');
check([$objects[202],$objects[40003]]===$sophie,'Sophie must remain unchanged');
$count=count($objects);foreach(['Sven'=>200,'Susi'=>201] as $name=>$root){$statistics=['neu'=>0,'aktualisiert'=>0,'vorhanden'=>0,'fehler'=>0];ksSpiegeln(55503,$root,0,'full',2,true,[],$statistics,$name);}
check(count($objects)===$count,'Repeated synchronization duplicated objects');
check(count(array_filter(IPS_GetChildrenIDs(201),fn($id)=>IPS_GetObject($id)['ObjectType']===6 && !IPS_GetObject($id)['ObjectIsHidden']))===2,'Susi unexpected visible duplicate links');
IPS_SetHidden(30002,true);foreach(['Sven'=>200,'Susi'=>201] as $name=>$root){$statistics=['neu'=>0,'aktualisiert'=>0,'vorhanden'=>0,'fehler'=>0];ksSpiegeln(55503,$root,0,'full',2,true,[],$statistics,$name);check(IPS_GetObject(IPS_GetObjectIDByIdent('MirrorLink30002',$root))['ObjectIsHidden'],'Hidden source not propagated');}
IPS_SetHidden(30002,false);$statistics=['neu'=>0,'aktualisiert'=>0,'vorhanden'=>0,'fehler'=>0];ksSpiegeln(55503,201,0,'full',2,true,[],$statistics,'Susi');check(!IPS_GetObject(IPS_GetObjectIDByIdent('MirrorLink30002',201))['ObjectIsHidden'],'Returning source must become visible again');
echo "Visu mirror: Sven current, Susi compact, Sophie unchanged, stale links and idempotency passed\n";
