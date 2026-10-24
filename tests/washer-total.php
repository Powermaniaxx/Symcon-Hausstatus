<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/LaundrySupport.php';
$meter = 0.0;
function IPS_VariableExists(int $id): bool { return $id===40662; }
function GetValue(int $id): mixed { return $GLOBALS['meter']; }
class MeterFixture {
    use HausstatusLaundrySupport;
    public float $offset=7.26;
    private function ConfigInteger(string $name): int { return 40662; }
    private function ConfigValue(string $name): mixed { return $this->offset; }
    public function total(): array { return $this->WasherTotalEnergy(); }
}
function check(bool $value): void { if (!$value) { throw new RuntimeException('Meter total assertion failed'); } }
$m=new MeterFixture();
check($m->total()['total']===7.26);
$meter=2.5;check($m->total()['total']===9.76);
$meter=null;check($m->total()['total']===null);
$meter=-1;check($m->total()['total']===null);
$meter='2.5';check($m->total()['total']===null);
$meter=INF;check($m->total()['total']===null);
$meter=0.0;$m->offset=0;check($m->total()['total']===0.0);
$m->offset=-1;check($m->total()['total']===null);
echo "Washer total: fixed balance, new readings, zero, invalid/missing values OK\n";

