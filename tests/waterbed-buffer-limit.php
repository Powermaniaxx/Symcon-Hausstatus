<?php
declare(strict_types=1);
require __DIR__.'/waterbed-history.php';
class LimitedBufferFixture extends Fixture {
 public int $maximum=0;
 function SetBuffer($key,$value){
  $this->maximum=max($this->maximum,strlen($value));
  if(strlen($value)>262144)throw new RuntimeException('Symcon buffer warning: '.$key.' = '.strlen($value).' bytes');
  parent::SetBuffer($key,$value);
 }
}
$powerCounter=false;$energyCounter=true;$missing=[];$rawRows=[];
$zone=new DateTimeZone('Europe/Berlin');$start=(new DateTimeImmutable('now',$zone))->modify('first day of this month')->setTime(0,0)->getTimestamp();
for($i=0;$i<45000;$i++)$rawRows[]=['TimeStamp'=>$start+$i,'Value'=>$i%2?260:51];
$f=new LimitedBufferFixture();$result=$f->run();
check(count($result['month']['series']['w'])>=45000,'Every real heating transition retained');
$cached=$f->run();check(json_encode($cached)===json_encode($result),'Large history survives cache round-trip');
check($f->maximum<=262144,'Every physical buffer remains under Symcon warning limit');
echo 'waterbed-buffer-limit: OK (45,000 transitions; largest physical buffer '.$f->maximum." bytes)\n";
