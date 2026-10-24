<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/WaterbedSupport.php';
date_default_timezone_set('UTC');
$missing=false;$aggregation=1;$calls=[];
function AC_GetAggregationType($archive,$id){global $aggregation;return $aggregation;}
function AC_GetAggregatedValues($archive,$id,$level,$start,$end,$limit){
 global $missing,$calls;
 if($id!==17732||$level!==3)throw new Exception('Wrong energy source or aggregation');
 $calls[]=[$start,$end];$zone=new DateTimeZone('Europe/Berlin');
 $year=(int)(new DateTimeImmutable('@'.$start))->setTimezone($zone)->format('Y');
 $rows=[];
 for($m=1;$m<=12;$m++){
  $t=(new DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00',$year,$m),$zone))->getTimestamp();
  if($t>$end||($missing&&$m===2))continue;
  $rows[]=['TimeStamp'=>$t,'Avg'=>$m*1000];
 }return $rows;
}
class AnnualFixture{
 use HausstatusWaterbedSupport;
 public $buffers=[];public $unit='Wh';
 function ConfigString($key){return $this->unit;}
 function GetBuffer($key){return $this->buffers[$key]??'';}
 function SetBuffer($key,$v){$this->buffers[$key]=$v;}
 function run(){return $this->WaterbedYearComparison(99,17732,(new DateTimeImmutable('2026-10-24 12:00:00',new DateTimeZone('Europe/Berlin')))->getTimestamp());}
}
function check($ok,$why){if(!$ok)throw new Exception($why);}
$f=new AnnualFixture;$r=$f->run();
check($r['currentYear']===2026&&$r['previousYear']===2025,'Years');
check($r['months'][0]['current']===1.0&&$r['months'][1]['current']===2.0,'Individual months and Wh conversion');
check($r['currentTotal']===55.0&&$r['previousTotal']===78.0,'Separate annual sums');
check($r['months'][10]['current']===null,'Future is missing, not zero');
check(count($calls)===2,'Two bounded monthly archive reads');
$f->run();check(count($calls)===2,'Cache');
$missing=true;$f->buffers=[];$r=$f->run();check($r['currentTotal']===null&&$r['previousTotal']===null,'Missing months invalidate totals');
$missing=false;$aggregation=0;$f->buffers=[];$r=$f->run();check($r['currentTotal']===null&&str_contains($r['note'],'Zähler'),'Counter aggregation required');
echo "waterbed-annual: OK
";
