<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/DisplaySupport.php';
$calls=[];$script=33054;$enabled=true;$scriptOK=true;$scriptRun=true;
function IPS_ScriptExists($id) {return $GLOBALS['scriptOK'];}
function IPS_RunScriptEx($id,$args) {$GLOBALS['calls'][]=['script',$id,$args];return $GLOBALS['scriptRun'];}
function RequestAction($id,$value) {$GLOBALS['calls'][]=['action',$id,$value];return true;}
class LightColorHarness {
    use HausstatusDisplaySupport;
    private function ConfigBoolean(string $name): bool {return $GLOBALS['enabled'];}
    private function ConfigInteger(string $name): int {return $name==='LightCommandScript'?$GLOBALS['script']:['LightState'=>10,'Brightness'=>11,'LightColor'=>12][$name];}
    private function ValidateAction(int $id,int $type): void {if($id!==12||$type!==1)throw new Exception('Invalid direct target');}
    public function color(mixed $value): void {$this->SetLight('LightColor',$value);}
}
function check($v,$message) {if(!$v)throw new Exception($message);}
function rejects(callable $f): void {try{$f();}catch(Throwable $e){return;}throw new Exception('Invalid command accepted');}
$h=new LightColorHarness();
foreach([0,0xff0000,0xffffff] as $color){$h->color($color);check($calls[array_key_last($calls)]===['script',33054,['COMMAND'=>'Color','VALUE'=>$color,'SOURCE'=>'HausstatusBedienung']],'Color must use selected automation script');}
$before=$calls;
foreach([-1,0x1000000,'#ff0000',true,1.5] as $bad)rejects(fn()=>$h->color($bad));
check($calls===$before,'Invalid color must not send commands');
$scriptRun=false;rejects(fn()=>$h->color(0xff0000));check(end($calls)[0]==='script','Script failure never bypasses automation');$scriptRun=true;
$scriptOK=false;$before=$calls;rejects(fn()=>$h->color(0xff0000));check($calls===$before,'Missing script must not write directly');$scriptOK=true;
$script=0;$h->color(0x00ff00);check(end($calls)===['action',12,0x00ff00],'Direct action uses configured color only');
$enabled=false;$before=$calls;rejects(fn()=>$h->color(0xff0000));check($calls===$before,'Disabled controls never send commands');
echo "Light color bounds, script routing, failure guard and configured target: passed\n";
