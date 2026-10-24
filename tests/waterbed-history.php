<?php
declare(strict_types=1);
require __DIR__ . '/../HausstatusBedienung/WaterbedHistorySupport.php';
$calls = 0; $counter = true; $missing = false;
function IPS_VariableExists($id) { return true; }
function AC_GetLoggingStatus($archive, $id) { global $missing; return !($missing && $id === 43240); }
function AC_GetAggregationType($archive, $id) { global $counter; return $counter ? 1 : 0; }
function AC_GetAggregatedValues($archive, $id, $level, $start, $end, $limit) {
    global $calls; $calls++;
    if (!in_array($id, [16905,17732,30213,43240], true)) { throw new Exception('Wrong source'); }
    return [['TimeStamp'=>$start+60,'Avg'=>$id===17732?1250:28],['TimeStamp'=>$start,'Avg'=>null]];
}
class Fixture {
    use HausstatusWaterbedHistorySupport;
    public $buffers=[]; public $unit='Wh';
    function WaterbedArchiveID(){return 99;}
    function ConfigInteger($key){return $key==='WaterbedSvenTemperature'?30213:43240;}
    function ConfigString($key){return $this->unit;}
    function GetBuffer($key){return $this->buffers[$key]??'';}
    function SetBuffer($key,$value){$this->buffers[$key]=$value;}
    function run(){return $this->WaterbedChartHistory();}
}
function check($v,$message){if(!$v)throw new Exception($message);}
$f=new Fixture; $data=$f->run();
check(array_keys($data)===['day','week','month','year'],'All periods');
check($data['day']['series']['kwh'][1]['v']===1.25,'Wh conversion');
check($data['day']['series']['kwh'][0]['v']===null,'Missing stays null');
check($calls===16,'Bounded archive calls');
$f->run();check($calls===16,'Cache reuse');
$f->unit='kWh';$data=$f->run();check($data['day']['series']['kwh'][1]['v']===1250.0,'Unit invalidates cache');
$counter=false;$f->buffers=[];$data=$f->run();check($data['day']['series']['kwh']===[],'Reject standard mean for cumulative counter');
check(str_contains($data['day']['note'],'Zähler'),'Counter setup note');
$counter=true;$missing=true;$f->buffers=[];$data=$f->run();
check($data['day']['series']['susi']===[] && count($data['day']['series']['sven'])===2,'Independent missing series');
echo "waterbed-history: OK\n";
