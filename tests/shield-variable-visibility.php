<?php
declare(strict_types=1);
require __DIR__.'/../HausstatusBedienung/ShieldSupport.php';
$hidden=[];$identities=[];$next=10;
function IPS_GetObjectIDByIdent(string $ident,int $owner): int|false {global $identities;return $identities[$owner][$ident]??false;}
function IPS_VariableExists(int $id): bool {return $id>0;}
function IPS_SetHidden(int $id,bool $value): void {$GLOBALS['hidden'][$id]=$value;}
class VisibilityHarness {
    use HausstatusShieldSupport;
    public int $InstanceID=100;
    public int $source=0;
    public int $view=0;
    private function ReadPropertyInteger(string $name): int {return $name==='View'?$this->view:$this->source;}
    public function run(): void {$this->HideUnusedShieldVariables();}
}
foreach(['Shield','ShieldDressing','ShieldBedroom','PhilipsTv'] as $prefix) {
 foreach(['Online','Power','App','Playback','Title','Channel','Subtitle','Error','Updated'] as $field)$identities[100][$prefix.$field]=$next++;
}
$identities[100]['UserVariable']=999;
$h=new VisibilityHarness();
foreach([0,6] as $view){$h->view=$view;$h->run();if($hidden)throw new Exception('Zentrale Medienquelle wurde verändert.');}
$h->view=15;$h->run();if(count($hidden)!==36||isset($hidden[999]))throw new Exception('PV-Ansicht muss nur ihre eigenen generierten Medienvariablen ausblenden.');
$hidden=[];$h->view=6;$h->source=200;$h->run();if(count($hidden)!==36)throw new Exception('Untergeordnete Ansicht verwendet den zentralen Besitzer.');
echo "OK: Technische Ansichten blenden nur eigene Medienkopien aus; zentrale Quellen und Benutzerobjekte bleiben erhalten.\n";
