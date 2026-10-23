<?php
declare(strict_types=1);
// Isolated SDK fixture: no connection to Symcon and no real device action.
const VM_UPDATE = 10603;
const VM_DELETE = 10602;
class IPSModuleStrict
{
    public int $InstanceID = 100;
    public array $buffers = [], $timers = [], $messages = [], $events = [];
    public function GetBuffer(string $name): string { return $this->buffers[$name] ?? ''; }
    public function SetBuffer(string $name, string $value): void { $this->buffers[$name] = $value; }
    public function SetTimerInterval(string $name, int $value): void { $this->timers[$name] = $value; $this->events[] = ['timer', $name, $value]; }
    public function ReadPropertyInteger(string $name): int { return (int)IPS_GetProperty($this->InstanceID, $name); }
    public function ReadPropertyString(string $name): string { return (string)IPS_GetProperty($this->InstanceID, $name); }
    public function ReadPropertyBoolean(string $name): bool { return (bool)IPS_GetProperty($this->InstanceID, $name); }
    public function UpdateVisualizationValue(string $value): void { $this->messages[] = json_decode($value, true, 512, JSON_THROW_ON_ERROR); $this->events[] = ['reply']; }
    public function GetMessageList(): array { return []; }
    public function SendDebug(mixed ...$args): void {}
}
$props = ['ConfigSource'=>0,'ActiveView'=>true,'CinemaEnabled'=>true,'CinemaControl'=>123,'Raining'=>0,'View'=>5,'MotionSensors'=>'[]'];
function IPS_GetProperty(int $id, string $name): mixed { return $GLOBALS['props'][$name] ?? 0; }
function IPS_VariableExists(int $id): bool { return in_array($id,[123,124,125,126],true); }
function IPS_GetVariable(int $id): array { return ['VariableType'=>$id===126?1:0,'VariableCustomAction'=>$id===126?789:0,'VariableAction'=>456,'VariableCustomProfile'=>'','VariableProfile'=>'Door']; }
function GetValueBoolean(int $id): bool { return true; }
function GetValue(int $id): mixed { return false; }
function GetValueFormatted(int $id): string { return 'Geschlossen'; }
function IPS_ScriptExists(int $id): bool { return $id===789; }
function IPS_VariableProfileExists(string $name): bool { return $name==='Door'; }
function IPS_GetVariableProfile(string $name): array { return ['Associations'=>[['Name'=>'Open','Value'=>2]]]; }
function IPS_SemaphoreEnter(string $key, int $timeout): bool { return $GLOBALS['lockAvailable']; }
function IPS_SemaphoreLeave(string $key): void { $GLOBALS['released']++; }
function RequestAction(int $id, mixed $value): bool { $GLOBALS['actions'][]=[$id,$value]; return true; }
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
require __DIR__.'/../HausstatusBedienung/module.php';
$lockAvailable=true; $released=0; $actions=[];
$m=new HausstatusBedienung();$m->buffers['LastViewerSeen']=(string)time();
$m->RequestAction('Command:client-1:Cinema',true);
check($actions===[[123,true]],'Original action and Boolean preserved');
check($m->messages===[['commandDone'=>true,'commandRequest'=>'client-1']],'Fast acknowledgement needs no expensive state construction');
check($m->events[0]===['reply'],'Acknowledge before scheduling state');
check($released===1,'Command lock released');
check($m->timers['Refresh']===100,'One short refresh queued');
// An event burst must not restart the deadline and starve the actual update.
$before=count($m->events);
for($i=0;$i<50;$i++)$m->MessageSink(time(),123,VM_UPDATE,[true,true]);
check(count($m->events)===$before,'50 changed events share a single pending refresh');
$m->MessageSink(time(),123,VM_UPDATE,[true,false]);
check(count($m->events)===$before,'Unchanged events ignored');
$lockAvailable=false;$m->RequestAction('Command:client-2:Cinema',false);
check(end($m->messages)['commandRequest']==='client-2' && isset(end($m->messages)['error']),'Lock rejection correlated');
check(count($actions)===1,'Busy command never reaches hardware');
$lockAvailable=true;$m->RequestAction('Command:client-3:Cinema','true');
check(isset(end($m->messages)['error']) && count($actions)===1,'Type validation preserved');
$m->RequestAction('Command:client-4:DoorOpen','{}');
check(isset(end($m->messages)['error']) && count($actions)===1,'Door without permission/challenge rejected');
$m->buffers['LastViewerSeen']=(string)(time()-100);$m->Refresh();
check($m->timers['Refresh']===0 && $m->buffers['RefreshQueued']==='0','Expired viewer disables one-shot timer before any state work');
$m->RequestAction('Cinema',false);
check(end($m->messages)['commandRequest']===null && count($actions)===2,'Legacy PHP caller remains supported');
check($m->timers['Refresh']===0,'No viewer means no refresh timer after command');
$props+=['DoorEnabled'=>true,'DoorPermission'=>124,'DoorContact'=>125,'DoorControl'=>126];
$m->RequestAction('Command:door-1:DoorConfirm',json_encode(['request'=>'challenge-123456789']));
$confirmation=end($m->messages)['doorConfirmation'];
check(count($actions)===2 && $confirmation['ttl']===20,'Confirmation alone never opens the door');
$m->RequestAction('Command:door-2:DoorOpen',json_encode(['request'=>$confirmation['request'],'token'=>str_repeat('0',48)]));
check(count($actions)===2,'Wrong token cannot open door');
$valid=json_encode(['request'=>$confirmation['request'],'token'=>$confirmation['token']]);
$m->RequestAction('Command:door-3:DoorOpen',$valid);
check(end($actions)===[126,2] && count($actions)===3,'Confirmed door uses the original action/profile value');
$m->RequestAction('Command:door-4:DoorOpen',$valid);
check(count($actions)===3 && isset(end($m->messages)['error']),'Consumed confirmation cannot be replayed');
echo "Interaction performance: early ack, 50-event coalescing, validation, legacy, idle timers OK\n";
