<?php
declare(strict_types=1);
// Exercise the real Hausstatus command entry point including mirrored configuration.
const VM_UPDATE = 10603;
const VM_DELETE = 10602;
class IPSModuleStrict
{
    public int $InstanceID = 100;
    public array $buffers = [], $messages = [];
    public function GetBuffer(string $n): string { return $this->buffers[$n] ?? ''; }
    public function SetBuffer(string $n, string $v): void { $this->buffers[$n] = $v; }
    public function ReadPropertyInteger(string $n): int { return (int)IPS_GetProperty($this->InstanceID,$n); }
    public function ReadPropertyBoolean(string $n): bool { return (bool)IPS_GetProperty($this->InstanceID,$n); }
    public function SetTimerInterval(string $n, int $v): void {}
    public function UpdateVisualizationValue(string $v): void { $this->messages[] = json_decode($v,true); }
    public function GetMessageList(): array { return []; }
    public function SendDebug(mixed ...$args): void {}
}
$props=[100=>['ConfigSource'=>200,'ActiveView'=>true,'View'=>6],200=>['ConfigSource'=>0,'PhilipsTVInstance'=>456,'PhilipsTvEnabled'=>true,'ShieldEnabled'=>false]];
$sent=[];$refresh=0;
function IPS_GetProperty(int $id,string $n): mixed { return $GLOBALS['props'][$id][$n] ?? 0; }
function IPS_InstanceExists(int $id): bool { return in_array($id,[100,200,456,999],true); }
function IPS_GetInstance(int $id): array { return ['ModuleInfo'=>['ModuleID'=>$id===456?'{7A9BA592-5E11-4F37-B9D0-740BC1C61C19}':($id===999?'{WRONG}':'{9E33E109-4881-4E78-9906-38CAC2F1E210}')]]; }
function IPS_SemaphoreEnter(string $key,int $timeout): bool { return true; }
function IPS_SemaphoreLeave(string $key): void {}
function IPS_RequestAction(int $id,string $ident,mixed $value): void { $GLOBALS['sent'][]=[$id,$ident,$value]; }
function IPS_GetObjectIDByIdent(string $ident,int $owner): int|false { return $owner===456&&$ident==='State'?123:false; }
function IPS_VariableExists(int $id): bool { return $id===123; }
function SVPTV_GetState(int $id): string { return json_encode(['kind'=>'tv','direct'=>true,'power'=>'awake','enabled'=>true,'caps'=>['audio'=>true],'volume'=>35]); }
function SVPTV_UpdateStatus(int $id): void { $GLOBALS['refresh']++; }
function check(bool $v,string $m): void { if(!$v)throw new RuntimeException($m); }
require __DIR__.'/../HausstatusBedienung/module.php';
$m=new HausstatusBedienung();
foreach (['PhilipsVolume'=>['Volume',30],'PhilipsMute'=>['Mute',true],'PhilipsAmbilight'=>['Ambilight',false],'PhilipsRemoteKey'=>['RemoteKey','Home'],'ShieldPower_philips'=>['Power',true]] as $command=>[$ident,$value]) {
    $m->RequestAction('Command:test-1:'.$command,$value);
    check(end($sent)===[456,$ident,$value],$command.' reaches configured standalone instance');
    check(end($m->messages)===['commandDone'=>true,'commandRequest'=>'test-1'],'Bridge acknowledges completion');
}
$m->RequestAction('PhilipsRefresh',true);check(end($sent)===[456,'Refresh',true]&&$refresh===0,'Status refresh scheduled without blocking the HTML bridge');
$method=new ReflectionMethod($m,'ShieldState');$s=$method->invoke($m,'PhilipsTv');check($s['direct']&&$s['volume']===35,'Mirror reads central selected TV, no legacy copied state');
$ids=(new ReflectionMethod($m,'ShieldIDs'))->invoke($m);check($ids===[123],'Visible view subscribes to standalone state variable');
check($m->UpdateShield('{"device":"philips","online":true}')==='{"command":null}','Old Node-RED cannot receive TV commands after switching');
$props[200]['PhilipsTVInstance']=999;$before=count($sent);$m->RequestAction('PhilipsVolume',20);
check(count($sent)===$before&&isset(end($m->messages)['error']),'Wrong module selection fails closed');
check($method->invoke($m,'PhilipsTv')['enabled']===false,'Invalid selection rendered as configuration error');
$props[200]['PhilipsTVInstance']=456;$props[200]['PhilipsTvEnabled']=false;$before=count($sent);$m->RequestAction('ShieldPower_philips',true);
check(count($sent)===$before&&$method->invoke($m,'PhilipsTv')===null,'Central disabled switch applies to mirrors');
echo "Philips/Hausstatus: real command bridge, mirror source, direct state subscriptions, legacy isolation and invalid/disabled guards passed.\n";
