<?php
declare(strict_types=1);
require __DIR__ . '/../HausstatusBedienung/WaterbedHistorySupport.php';
$calls = 0; $counter = true; $missing = false; $powerCounter = false;
function IPS_VariableExists($id) { return true; }
function AC_GetLoggingStatus($archive, $id) { global $missing; return !($missing && $id === 43240); }
function AC_GetAggregationType($archive, $id) { global $counter, $powerCounter; return $id === 16905 ? ($powerCounter ? 1 : 0) : ($counter ? 1 : 0); }
function AC_GetLoggedValues($archive, $id, $start, $end, $limit) {
    global $rawPowerRows;
    if ($id !== 16905) { throw new Exception('Unexpected raw source'); }
    if (isset($rawPowerRows)) { return array_values(array_filter($rawPowerRows,static fn($row)=>$row['TimeStamp'] >= $start && $row['TimeStamp'] <= $end)); }
    return [['TimeStamp'=>$start,'Value'=>51]];
}
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
    function raw($start,$end,$level){return $this->WaterbedRawPower(99,16905,$start,$end,$level);}
}
function check($v,$message){if(!$v)throw new Exception($message);}
$f=new Fixture; $data=$f->run();
check(array_keys($data)===['day','week','month','year'],'All periods');
check($data['day']['series']['kwh'][1]['v']===1.25,'Wh conversion');
check($data['day']['series']['kwh'][0]['v']===null,'Missing stays null');
check($data['day']['series']['w'][1]['v']===28.0,'Watt mean preserved without conversion');
check($calls===16,'Bounded archive calls');
$f->run();check($calls===16,'Cache reuse');
$f->unit='kWh';$data=$f->run();check($data['day']['series']['kwh'][1]['v']===1250.0,'Unit invalidates cache');
$counter=false;$f->buffers=[];$data=$f->run();check($data['day']['series']['kwh']===[],'Reject standard mean for cumulative counter');
check(str_contains($data['day']['note'],'Zähler'),'Counter setup note');
$counter=true;$missing=true;$f->buffers=[];$data=$f->run();
check($data['day']['series']['susi']===[] && count($data['day']['series']['sven'])===2,'Independent missing series');
$powerCounter=true;$f->buffers=[];$data=$f->run();
check(count($data['day']['series']['w'])>0,'Raw power fallback');
check($data['day']['series']['w'][0]['v']===51.0,'Raw 51 W remains 51 W, never a sum');
check(str_contains($data['day']['note'],'Rohdaten'),'Power fallback note');
foreach([0,1,3] as $level){
    $start=new DateTimeImmutable('2026-03-01 00:00:00',new DateTimeZone('Europe/Berlin'));
    $raw=$f->raw($start,$start->modify('+31 days')->getTimestamp(),$level);
    check(count($raw['points'])>0,'Raw intervals exist');
    foreach($raw['points'] as $point){check($point['v']===51.0,'Time weighted watts across DST');}
}
$start=new DateTimeImmutable('2026-10-24 00:00:00',new DateTimeZone('Europe/Berlin'));$t=$start->getTimestamp();
$rawPowerRows=[['TimeStamp'=>$t,'Value'=>50],['TimeStamp'=>$t+1800,'Value'=>100]];
$raw=$f->raw($start,$t+3600,0);check($raw['points'][0]['v']===75.0,'Time weighted mean, not sum or delta');
$rawPowerRows=[['TimeStamp'=>$t+1800,'Value'=>51]];
$raw=$f->raw($start,$t+7200,0);check($raw['points'][0]['v']===null && $raw['points'][1]['v']===51.0,'Incomplete interval stays empty');
echo "waterbed-history: OK\n";
