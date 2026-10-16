<?php
declare(strict_types=1);
require __DIR__ . '/../HausstatusBedienung/ShieldSupport.php';
require __DIR__ . '/../HausstatusBedienung/BedroomMediaSupport.php';

// Isolated fixtures. No network or device writes.
$GLOBALS['vars'] = []; $GLOBALS['actions'] = [];
function IPS_GetObjectIDByIdent(string $ident, int $owner): int|false { return array_search($ident, array_column($GLOBALS['vars'], 'ident', 'id'), true); }
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['vars'][$id]); }
function GetValue(int $id): mixed { return $GLOBALS['vars'][$id]['value']; }
function RequestAction(int $id, mixed $value): bool { $GLOBALS['actions'][] = [$id, $value]; return true; }
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
function rejects(callable $fn): void { try { $fn(); } catch (InvalidArgumentException|RuntimeException $e) { return; } throw new Exception('Expected rejection'); }
class Fixture {
    use HausstatusShieldSupport;
    use HausstatusBedroomMediaSupport;
    public int $InstanceID = 100;
    public array $props = ['ConfigSource' => 0];
    public function __construct() { $this->CreateShield(); $this->CreateBedroomMedia(); $this->props['ShieldEnabled'] = true; }
    private function RegisterPropertyBoolean(string $key, bool $default): void { $this->props[$key] = $default; }
    private function RegisterPropertyInteger(string $key, int $default): void { $this->props[$key] = $default; }
    private function RegisterTimer(mixed ...$args): void {}
    private function register(string $ident, mixed $default): void { $id=count($GLOBALS['vars'])+1000; $GLOBALS['vars'][$id]=['id'=>$id,'ident'=>$ident,'value'=>$default]; }
    private function RegisterVariableBoolean(string $ident, mixed ...$args): void { $this->register($ident,false); }
    private function RegisterVariableString(string $ident, mixed ...$args): void { $this->register($ident,''); }
    private function RegisterVariableInteger(string $ident, mixed ...$args): void { $this->register($ident,0); }
    private function ReadPropertyBoolean(string $key): bool { return $this->props[$key]; }
    private function ReadPropertyInteger(string $key): int { return $this->props[$key]; }
    private function ConfigBoolean(string $key): bool { return $this->props[$key]; }
    private function ConfigInteger(string $key): int { return $this->props[$key]; }
    private function CurrentView(): int { return 0; }
    public function SetValue(string $ident, mixed $value): void { $GLOBALS['vars'][IPS_GetObjectIDByIdent($ident,100)]['value']=$value; }
    private function GetValue(string $ident): mixed { return GetValue(IPS_GetObjectIDByIdent($ident,100)); }
    private function HasAction(int $id, int $type): bool { return ($GLOBALS['vars'][$id]['type']??-1)===$type && ($GLOBALS['vars'][$id]['action']??false); }
    private function Read(int $id): array { return ['raw'=>GetValue($id),'text'=>(string)GetValue($id)]; }
    private function RoomOptions(int $id, int $type): array { return [['value'=>2,'name'=>'MediaPlayer']]; }
    private function SliderPlan(int $id): array { return ['min'=>-80,'max'=>0,'step'=>0.5,'type'=>2]; }
    private function SetSlider(int $id, mixed $value, array $plan): void { if (!is_numeric($value)||$value<$plan['min']||$value>$plan['max']) { throw new RuntimeException('Bounds'); } RequestAction($id,$value); }
    public function state(): ?array { return $this->ShieldState(); }
    public function ids(): array { return $this->ShieldIDs(); }
    public function bedroom(): array { return $this->BedroomMediaState(); }
    public function living(): array { return $this->LivingMediaStatus(); }
    public function operate(string $name, mixed $value): void { $this->SetBedroomMedia($name,$value); }
}
$m=new Fixture();
$status=['online'=>true,'power'=>'awake','app'=>'smarttube','playback'=>'playing','title'=>'Wohnzimmer','error'=>''];
$m->UpdateShield(json_encode($status));
$m->UpdateShield(json_encode(['device'=>'ankleidezimmer']+array_replace($status,['title'=>'Ankleide'])));
$m->UpdateShield(json_encode(['device'=>'schlafzimmer']+array_replace($status,['title'=>'Schlafen'])));
$s=$m->state();
check($s['title']==='Wohnzimmer','Legacy maps to living room');
check($s['devices']['ankleidezimmer']['title']==='Ankleide','Dressing isolated');
check($s['devices']['schlafzimmer']['title']==='Schlafen','Bedroom isolated');
check(count($m->ids())===21,'All device sources watched');
$m->UpdateShield(json_encode(['device'=>'ankleidezimmer']+array_replace($status,['power'=>'standby'])));
check($m->state()['devices']['ankleidezimmer']['title']==='','Standby clears only own title');
check($m->state()['title']==='Wohnzimmer','Other titles preserved');
$m->SetValue('ShieldBedroomUpdated',time()-90); $m->ExpireShield();
check($m->state()['devices']['schlafzimmer']['stale'],'Independent watchdog');
check($m->state()['online'],'Watchdog preserves fresh living room');
$m->props['ShieldCompact']=true; $s=$m->state();
foreach ([$s,...array_values($s['devices'])] as $device) { check(!isset($device['title'])&&!isset($device['app'])&&$device['compact'],'Compact all rooms'); }
rejects(fn()=>$m->UpdateShield(json_encode(['device'=>'unknown']+$status)));
foreach ([13490=>0,47173=>0,33178=>1,18046=>2,41849=>0,20175=>0] as $id=>$type) { $GLOBALS['vars'][$id]=['id'=>$id,'ident'=>'fixture'.$id,'value'=>0,'type'=>$type,'action'=>true]; }
rejects(fn()=>$m->operate('BedroomSubwoofer',true));
check(array_values(array_filter($m->bedroom(),fn($item)=>$item['command']==='BedroomSubwoofer'))[0]['control']===null,'Subwoofer status only');
$m->operate('BedroomAvrSource',2);check(end($GLOBALS['actions'])===[33178,2],'Source routed');
$m->operate('BedroomAvrVolume',-64.0);check(end($GLOBALS['actions'])===[18046,-64.0],'Volume routed');
rejects(fn()=>$m->operate('BedroomAvrSource',99));
rejects(fn()=>$m->operate('BedroomAvrPower',1));
rejects(fn()=>$m->operate('BedroomAvrVolume',10));
rejects(fn()=>$m->operate('Cinema',true));
$m->props['BedroomMediaEnabled']=false;rejects(fn()=>$m->operate('BedroomSubwoofer',false));
foreach ([13241,50165,40994,17569] as $id) { $GLOBALS['vars'][$id]=['id'=>$id,'ident'=>'status'.$id,'value'=>true]; }
$before=count($GLOBALS['actions']);
$living=$m->living();
check(array_column($living,'name')===['Rear Height','Subwoofer Cinema 40','Surround + Front Lautsprecher','Ventilator Cinema 40'],'Living status labels');
check(count(array_filter($living,fn($item)=>$item['value']['raw']===true))===4,'Living status values');
check(count($GLOBALS['actions'])===$before,'Reading status never operates devices');
echo "Shield devices and bedroom actions: passed\n";
