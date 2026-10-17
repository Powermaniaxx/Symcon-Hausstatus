<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/HeatingCenterSupport.php';
$ids=[123];$enabled=true;$actions=[];$busy=false;$fail=false;
$report=['control'=>['mode'=>'auto','active'=>true,'testMode'=>false,'season'=>'autumn','seasonLabel'=>'Herbst','manualSeason'=>'winter','reason'=>'Jahreszeit'], 'lastActionReply'=>[]];
function IPS_GetInstanceListByModuleID(string $guid): array {return $GLOBALS['ids'];}
function SVHZ_GetCommunicationReport(int $id): string {return json_encode($GLOBALS['report'],JSON_THROW_ON_ERROR);}
function IPS_RequestAction(int $id,string $ident,mixed $value): void {
    global $report,$actions,$busy,$fail;
    if ($id!==123||$ident!=='Do')throw new Exception('Falsches Ziel');
    $p=json_decode($value,true,64,JSON_THROW_ON_ERROR);$actions[]=$p;
    if($p['action']!=='mode')throw new Exception('Nur Moduswahl erlaubt');
    if($busy)return;
    $report['lastActionReply']=['requestId'=>$p['requestId'],'success'=>!$fail,'error'=>'Gerätefehler'];
    if(!$fail) {$report['control']['mode']=$p['mode'];if($p['mode']!=='auto')$report['control']['manualSeason']=$p['mode'];}
}
class HeatingHarness {
    use HausstatusHeatingCenterSupport;
    private function ConfigBoolean(string $key): bool {return $GLOBALS['enabled'];}
    public function item(): ?array {return $this->HeatingCenterItem();}
    public function mode(mixed $value): void {$this->SetHeatingCenterMode($value);}
}
function check(bool $condition,string $message): void {if(!$condition)throw new Exception($message);}
function reject(callable $fn): void {try{$fn();}catch(Throwable $error){return;}throw new Exception('Aktion musste abgewiesen werden');}
$h=new HeatingHarness();$item=$h->item();
check($item['value']['text']==='Automatik · Regelung aktiv · Herbst','Betriebsart, tatsächlicher Status und Saison aus Zentrale.');
$report['control']['active']=false;
check(str_contains($h->item()['value']['text'],'Regelung nicht aktiv'),'Auswahl alleine ist keine laufende Regelung.');
$h->mode('manual');check(end($actions)['mode']==='winter'&&!$report['control']['active'],'Manuell verwendet letzten manuellen Plan ohne automatische Aktivierung.');
$h->mode('spring');check(end($actions)['mode']==='spring','Manuelle Saisonwahl.');
$h->mode('auto');check(end($actions)['mode']==='auto','Automatik an alleinige Zentrale übergeben.');
$enabled=false;$before=count($actions);check($h->item()['control']===null,'Bedienung gesperrt.');reject(fn()=>$h->mode('auto'));check(count($actions)===$before,'Sperre sendet keine Aktion.');$enabled=true;
foreach([1,true,[], 'away','invalid'] as $value)reject(fn()=>$h->mode($value));
check(count($actions)===$before,'Ungültige Befehle senden nichts.');
$busy=true;reject(fn()=>$h->mode('auto'));$busy=false;
$fail=true;reject(fn()=>$h->mode('auto'));$fail=false;
$report['control']['testMode']=true;check(str_contains($h->item()['value']['text'],'Testmodus'),'Teststatus wird nicht als Echtregelung ausgegeben.');
$ids=[123,456];check($h->item()['control']===null,'Mehrdeutige Instanzen nicht bedienen.');reject(fn()=>$h->mode('auto'));
$ids=[];check($h->item()===null,'Ohne Zentrale alte Anzeige weiterhin möglich.');
$ids=[123];$report=['version'=>'1.8.1'];check(str_contains($h->item()['note'],'1.8.2')&&$h->item()['control']===null,'Alte API zeigt Updatehinweis und keinen alten Profilverteiler.');
echo "OK: Heizungszentrale als einzige Quelle, Betriebsart, Status, manuelle Saison, Sperren und Bestätigungsfehler.\n";
