<?php
declare(strict_types=1);
require __DIR__ . '/../HausstatusBedienung/WaterbedHistorySupport.php';
$rawRows=[];$rawCalls=[];$aggregateCalls=[];$powerCounter=false;$energyCounter=true;$missing=[];
function IPS_VariableExists($id){return true;}
function AC_GetLoggingStatus($archive,$id){global $missing;return !in_array($id,$missing,true);}
function AC_GetAggregationType($archive,$id){global $powerCounter,$energyCounter;return $id===16905?($powerCounter?1:0):($id===17732&&$energyCounter?1:0);}
function AC_GetLoggedValues($archive,$id,$start,$end,$limit){
    global $rawRows,$rawCalls;$rawCalls[]=[$id,$start,$end,$limit];
    if($id!==16905)throw new Exception('Unexpected raw source');
    $rows=array_values(array_filter($rawRows,static fn($row)=>$row['TimeStamp'] >= $start && $row['TimeStamp'] <= $end));
    usort($rows,static fn($a,$b)=>$b['TimeStamp']<=>$a['TimeStamp']);return array_slice($rows,0,$limit);
}
function AC_GetAggregatedValues($archive,$id,$level,$start,$end,$limit){
    global $aggregateCalls;$aggregateCalls[]=[$id,$level,$start,$end];
    return [['TimeStamp'=>$start,'Avg'=>$id===17732?1250:($id===16905?45:28)]];
}
class Fixture{
    use HausstatusWaterbedHistorySupport;
    public $buffers=[];public $unit='Wh';
    function WaterbedArchiveID(){return 99;}
    function ConfigInteger($key){return $key==='WaterbedSvenTemperature'?30213:43240;}
    function ConfigString($key){return $this->unit;}
    function GetBuffer($key){return $this->buffers[$key]??'';}
    function SetBuffer($key,$value){$this->buffers[$key]=$value;}
    function run(){return $this->WaterbedChartHistory();}
    function events($from,$until,$pages=5){return $this->WaterbedPowerEvents(99,16905,$from,$until,$pages);}
    function means($points,$start,$until){return $this->WaterbedDailyPower($points,$start,$until);}
    function slice($points,$from,$until){return $this->WaterbedPowerSlice($points,$from,$until);}
}
function check($value,$message){if(!$value)throw new Exception($message);}
$f=new Fixture;$zone=new DateTimeZone('Europe/Berlin');$start=new DateTimeImmutable('2026-10-01 00:00:00',$zone);$t=$start->getTimestamp();
$rawRows=[['TimeStamp'=>$t-3600,'Value'=>0],['TimeStamp'=>$t+300,'Value'=>50],['TimeStamp'=>$t+600,'Value'=>260],['TimeStamp'=>$t+900,'Value'=>0],['TimeStamp'=>$t+1200,'Value'=>0],['TimeStamp'=>$t+1500,'Value'=>51]];
$events=$f->events($t,$t+1800);
check(array_column($events['points'],'v')===[0.0,50.0,260.0,0.0,51.0,51.0],'Every heating stage survives without averaging');
check($events['points'][2]['t']===$t+600,'Actual transition time retained');
$slice=$f->slice($events['points'],$t+700,$t+1000);check($slice[0]['v']===260.0&&$slice[1]['v']===0.0,'Carry prior phase into selected period');

// More than one archive page must retain older phases, not silently show only the latest 10,000.
$rawRows=[];for($i=0;$i<10005;$i++)$rawRows[]=['TimeStamp'=>$t+$i,'Value'=>$i%2?260:0];
$events=$f->events($t,$t+10005);check(!$events['limited']&&count($events['points'])===10006,'Archive pagination preserves all transitions');
$limited=$f->events($t,$t+10005,1);check($limited['limited']&&$limited['points'][0]['v']===null,'Truncation leaves older values unknown');

// 25-hour DST day: 12 hours at 0 W and 13 hours at 100 W gives 52 W, not a daily watt sum.
$dst=new DateTimeImmutable('2026-10-25 00:00:00',$zone);$a=$dst->getTimestamp();$b=$dst->modify('+1 day')->getTimestamp();
check($b-$a===25*3600,'DST fixture');
$daily=$f->means([['t'=>$a,'v'=>0.0],['t'=>$a+12*3600,'v'=>100.0],['t'=>$b,'v'=>100.0]],$dst,$b);
check($daily[0]['v']===52.0,'Time weighted daily power across DST');
$daily=$f->means([['t'=>$a,'v'=>null],['t'=>$a+3600,'v'=>51.0]],$dst,$b);check($daily[0]['v']===null,'Partial day remains missing');

$now=time();$today=(new DateTimeImmutable('@'.$now))->setTimezone($zone)->setTime(0,0);$month=$today->modify('first day of this month')->getTimestamp();
$rawRows=[['TimeStamp'=>$month-86400,'Value'=>0],['TimeStamp'=>$month+60,'Value'=>50],['TimeStamp'=>$month+120,'Value'=>260],['TimeStamp'=>$month+180,'Value'=>0]];
$rawCalls=[];$aggregateCalls=[];$f->buffers=[];$data=$f->run();
check(array_keys($data)===['day','week','month','year','previousYear'],'All time ranges retained');
check($data['month']['powerMode']==='steps'&&in_array(260.0,array_column($data['month']['series']['w'],'v'),true),'Month uses raw heating stages');
$powerQueries=array_values(array_filter($aggregateCalls,static fn($q)=>$q[0]===16905));check(count($powerQueries)===2&&$powerQueries[0][1]===1&&$powerQueries[1][1]===1,'Year and previous year use daily averages');
check(count($rawCalls)===2,'Day, week and month share one raw query and one baseline');
check($data['day']['series']['kwh'][0]['v']===1.25,'Wh converted to kWh');
check($data['day']['daily'][0]['kwh']===1.25,'Daily table uses daily energy and unit conversion');
check($data['day']['daily'][0]['partial']===true,'Today is marked incomplete');
check(count($data['month']['daily'])===(int)$today->format('j'),'Every elapsed calendar day appears in table');
check($data['year']['daily'][0]['w']===45.0&&$data['year']['daily'][0]['kwh']===1.25,'Year table joins daily W and daily kWh');
check($data['previousYear']['daily'][0]['partial']===false,'Completed year has no partial first day');
check($data['year']['periodEnd']>(int)$data['year']['end'],'Full year domain includes future dates');
$calls=count($aggregateCalls)+count($rawCalls);$f->run();check(count($aggregateCalls)+count($rawCalls)===$calls,'Cache reused');
$f->unit='kWh';$data=$f->run();check($data['day']['series']['kwh'][0]['v']===1250.0,'Unit invalidates cache');
$energyCounter=false;$f->buffers=[];$data=$f->run();check($data['month']['series']['kwh']===[],'Cumulative means never treated as consumption');
check($data['year']['daily'][0]['kwh']===null,'Missing daily consumption is not fabricated as zero');
$energyCounter=true;$missing=[43240];$f->buffers=[];$data=$f->run();check($data['month']['series']['susi']===[]&&$data['month']['series']['sven']!==[],'Independent temperature failure');
$missing=[];$powerCounter=true;$f->buffers=[];$data=$f->run();check($data['year']['powerMode']==='dailyMean','Counter power converted to daily raw means');
echo "waterbed-history: OK (heating transitions, pagination, daily means, DST, missing values, units, cache)\n";
