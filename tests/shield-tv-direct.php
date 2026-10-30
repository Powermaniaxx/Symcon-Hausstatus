<?php
declare(strict_types=1);
$values=[11=>true,12=>'awake',13=>time(),20=>true];$types=[11=>0,12=>3,13=>1,20=>0];
$objects=['26475:ShieldOnline'=>11,'26475:ShieldPower'=>12,'26475:ShieldUpdated'=>13,'456:Power'=>20];
$sourceExists=true;$linkTargets=[];
$actions=[];$created=100;$enabled=true;$target=456;$fail=false;$wrongModule=false;$logs=[];$triggers=[];
function IPS_ScriptExists($id){return $id===42;}
function IPS_InstanceExists($id){return in_array($id,[52627,456,777],true)||($id===26475&&$GLOBALS['sourceExists']);}
function IPS_ObjectExists($id){return IPS_InstanceExists($id)||IPS_VariableExists($id);}
function IPS_LinkExists($id){return isset($GLOBALS['linkTargets'][$id]);}
function IPS_GetLink($id){return ['TargetID'=>$GLOBALS['linkTargets'][$id]];}
function IPS_GetName($id){return $id===26475?'Shield':'Fixture '.$id;}
function IPS_GetChildrenIDs($parent){$out=[];foreach($GLOBALS['objects'] as $key=>$id){if(str_starts_with($key,$parent.':'))$out[]=$id;}return $out;}
function IPS_GetObject($id){foreach($GLOBALS['objects'] as $key=>$value){if($value===$id)return ['ObjectIdent'=>explode(':',$key,2)[1]];}return ['ObjectIdent'=>''];}
function IPS_GetParent($id){foreach($GLOBALS['objects'] as $key=>$value){if($value===$id)return (int)explode(':',$key,2)[0];}return 0;}
function IPS_GetInstance($id){return ['ModuleInfo'=>['ModuleID'=>$id===456?($GLOBALS['wrongModule']?'{WRONG}':'{7A9BA592-5E11-4F37-B9D0-740BC1C61C19}'):'{9E33E109-4881-4E78-9906-38CAC2F1E210}']];}
function IPS_GetConfiguration($id){return json_encode($id===52627?['ConfigSource'=>0,'PhilipsTvEnabled'=>true,'PhilipsTVInstance'=>$GLOBALS['target']]:['Enabled'=>$GLOBALS['enabled']]);}
function IPS_GetObjectIDByIdent($ident,$parent){return $GLOBALS['objects'][$parent.':'.$ident]??false;}
function IPS_VariableExists($id){return array_key_exists($id,$GLOBALS['values']);}
function IPS_GetVariable($id){return ['VariableType'=>$GLOBALS['types'][$id]??3];}
function IPS_SemaphoreEnter(...$args){return true;}
function IPS_SemaphoreLeave(...$args){}
function IPS_CreateVariable($type){$id=++$GLOBALS['created'];$GLOBALS['values'][$id]='';$GLOBALS['types'][$id]=$type;return $id;}
function IPS_CreateEvent($type){return ++$GLOBALS['created'];}
function IPS_SetParent(...$args){}
function IPS_SetIdent($id,$ident){$GLOBALS['objects']['42:'.$ident]=$id;}
function IPS_SetName(...$args){}
function IPS_SetHidden(...$args){}
function IPS_EventExists($id){return $id===($GLOBALS['objects']['42:ShieldTvFollowEvent']??false);}
function IPS_GetEvent($id){return ['EventType'=>0];}
function IPS_SetEventTrigger($id,$type,$variable){check($type===1&&$variable===13,'Trigger must observe committed ShieldUpdated');$GLOBALS['triggers'][]=$variable;}
function IPS_SetEventScript($id,$code){check($code==='IPS_RunScript(42);','Event invokes this script');}
function IPS_SetEventActive(...$args){}
function GetValue($id){return $GLOBALS['values'][$id];}
function SetValue($id,$value){$GLOBALS['values'][$id]=$value;}
function RequestAction($id,$value){check($id===20&&is_bool($value),'Action uses new TV Power Boolean');if($GLOBALS['fail'])return false;$GLOBALS['actions'][]=$value;return true;}
function IPS_LogMessage($name,$text){$GLOBALS['logs'][]=$text;}
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
function rejects(callable $fn){try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Expected rejection');}
function runFollow($sender='Variable'){$_IPS=['SELF'=>42,'SENDER'=>$sender];ob_start();try{include __DIR__.'/../docs/integrations/shield/ShieldWohnzimmer-TV-Kopplung.php';return ob_get_contents();}finally{ob_end_clean();}}
runFollow('Execute');check($actions===[]&&$created===102,'Manual setup sends no TV command');
$cache=$objects['42:ShieldTvFollowState'];check(json_decode($values[$cache],true)['tvInstance']===456,'Cache records selected standalone target');
runFollow();check($actions===[],'Unchanged awake sends nothing');
$values[12]='standby';runFollow();check($actions===[false],'Transition sends direct standby');
$before=$values[$cache];$values[11]=false;$values[12]='unknown';runFollow();check($values[$cache]===$before&&$actions===[false],'Offline keeps cache and sends no command');
$values[11]=true;$values[12]='standby';runFollow();check($actions===[false],'Recovery to same mode sends nothing');
$values[12]='awake';runFollow();check($actions===[false,true],'Changed valid mode sends direct on');
$values[12]='standby';$values[13]=time()-180;runFollow();check($actions===[false,true],'Stale state ignored');
$values[13]=time()+20;runFollow();check($actions===[false,true],'Future state ignored');
$values[13]=time();$fail=true;$before=$values[$cache];rejects(fn()=>runFollow());check($values[$cache]===$before,'Failed action does not advance cache');$fail=false;runFollow();check($actions===[false,true,false],'Failed transition retries on next committed status');
$values[12]='awake';runFollow('Execute');check($actions===[false,true,false]&&$created===102,'Repeated manual start only re-baselines; objects reused');
$wrongModule=true;rejects(fn()=>runFollow());$wrongModule=false;$enabled=false;rejects(fn()=>runFollow());$enabled=true;
$target=0;rejects(fn()=>runFollow());$target=456;
$before=$actions;$values[12]='awake';$values[13]=time()-90;$values[$cache]='';
$output=runFollow('Execute');check(str_contains($output,'Ausgangszustand gespeichert')&&$actions===$before,'90-second-old source matches Hausstatus tolerance; manual still sends nothing');
$values[13]=time()-180;$output=runFollow('Execute');check(str_contains($output,'Grenze: 150')&&str_contains($output,'ShieldOnline [11]')&&str_contains($output,'ShieldPower [12]')&&str_contains($output,'ShieldUpdated [13]'),'Stale diagnostic names reason and source variable IDs');
$values[11]=false;$values[13]=time();$output=runFollow('Execute');check(str_contains($output,'ShieldOnline ist false')&&$actions===$before,'Offline cause shown without TV commands');
check(str_contains($output,'Shield [26475]'),'Requested Shield source appears in diagnostics');
$values[11]=true;$values[12]=true;$types[12]=0;
unset($objects['26475:ShieldOnline'],$objects['26475:ShieldPower'],$objects['26475:ShieldUpdated']);
$objects['26475:Online']=11;$objects['26475:Power']=12;$objects['26475:Updated']=13;
runFollow('Execute');$before=$actions;$values[12]=false;runFollow();check($actions===[...$before,false],'Direct Shield module Boolean Power mapped to standby');
$types[12]=3;$values[12]='On';runFollow();check(end($actions)===true,'String On mapped to awake');
$before=$actions;$values[12]='unavailable';runFollow();check($actions===$before,'Unknown direct module value cannot trigger standby');
unset($objects['26475:Updated']);rejects(fn()=>runFollow());check($actions===$before,'Missing real update timestamp fails closed');
$objects['26475:Updated']=13;$sourceExists=false;rejects(fn()=>runFollow());check($actions===$before,'Missing requested Shield source cannot fall back to old master');
$values[26475]='awake';$types[26475]=3;$objects['777:PowerState']=26475;$objects['777:Online']=11;$objects['777:Updated']=13;
runFollow('Execute');$before=$actions;$values[26475]='standby';runFollow();check($actions===[...$before,false],'Supplied status variable resolves siblings through its parent');
unset($values[26475]);$objects['777:PowerState']=12;$values[12]='awake';$linkTargets[26475]=777;$before=$actions;
runFollow('Execute');check($actions===$before,'Source link resolves target and manual remains non-switching');
$linkTargets[26475]=26475;rejects(fn()=>runFollow());check($actions===$before,'Cyclic link cannot issue command');
echo "Shield/TV direct: new Power action, manual baseline, state changes, offline/stale guards, idempotency and retry passed.\n";
