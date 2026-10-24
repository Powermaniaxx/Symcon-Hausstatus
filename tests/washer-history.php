<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/LaundrySupport.php';
function stamp(string $s): int { return (new DateTimeImmutable($s,new DateTimeZone('Europe/Berlin')))->getTimestamp(); }
$rows=[15161=>[
 ['TimeStamp'=>stamp('2025-10-24 12:00'),'Avg'=>2000],
 ['TimeStamp'=>stamp('2026-10-23 12:00'),'Avg'=>1000],
 ['TimeStamp'=>stamp('2026-10-24 00:00'),'Avg'=>1000],
 ['TimeStamp'=>stamp('2026-10-24 13:00'),'Avg'=>1000]],
 50687=>[['TimeStamp'=>stamp('2026-10-24 12:00'),'Avg'=>9000],
 ['TimeStamp'=>stamp('2026-10-24 13:00'),'Avg'=>2000],
 ['TimeStamp'=>stamp('2026-10-25 00:00'),'Avg'=>3000]]];
$reads=0;$logging=true;
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['rows'][$id]); }
function IPS_GetInstanceListByModuleID(string $id): array { return [1]; }
function AC_GetLoggingStatus(int $a,int $id): bool { return $GLOBALS['logging']; }
function AC_GetAggregatedValues(int $a,int $id,int $level,int $from,int $to,int $limit): array {
 $GLOBALS['reads']++;return array_values(array_filter($GLOBALS['rows'][$id],fn($r)=>$r['TimeStamp']>=$from&&$r['TimeStamp']<=$to));
}
class HistoryFixture {
 use HausstatusLaundrySupport;
 private array $cache=[];
 private function ConfigInteger(string $name): int { return $name==='WasherLegacyPower'?15161:stamp('2026-10-24 13:30'); }
 private function GetBuffer(string $name): string { return $this->cache[$name]??''; }
 private function SetBuffer(string $name,string $value): void { $this->cache[$name]=$value; }
 public function energy(int $now): array { return $this->WasherPeriodEnergy(50687,$now); }
}
function check(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
$m=new HistoryFixture();$now=stamp('2026-10-24 14:00');$e=$m->energy($now);
check($e['today']===2.5,'Cutover within an hour must split old/new, not sum overlapping whole hours');
check($e['yesterday']===1.0,'Yesterday uses old archive only');
check($e['week']===3.5&&$e['year']===3.5,'Week/year combine both ranges');
check($e['lastYear']===2.0,'Previous calendar year available');
$n=$reads;$cached=$m->energy($now+60);check($reads===$n&&$cached['today']===$e['today'],'Cached period results stable');
$next=$m->energy(stamp('2026-10-25 02:00'));
check($next['today']===3.0&&$next['yesterday']===2.5,'Berlin midnight rollover keeps yesterday and starts new today');
$logging=false;$missing=(new HistoryFixture())->energy($now);
check($missing['today']===null&&$missing['lastYear']===null,'Missing archives must remain unknown');
echo "Washer history: cutover, yesterday, week/year, previous year, cache, rollover, missing archive OK\n";

