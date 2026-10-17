<?php
declare(strict_types=1);

trait HausstatusHeatingCenterSupport
{
    private function HeatingCenterIDs(): array
    {
        return function_exists('IPS_GetInstanceListByModuleID')
            ? IPS_GetInstanceListByModuleID('{C9813E5D-940A-42B2-B7B4-A6A78F895E51}') : [];
    }

    private function ReadHeatingCenter(int $id): array
    {
        if (!function_exists('SVHZ_GetCommunicationReport')) {
            throw new RuntimeException('Bitte die Heizungszentrale auf Version 1.8.2 oder neuer aktualisieren.');
        }
        $report=json_decode(SVHZ_GetCommunicationReport($id),true,64,JSON_THROW_ON_ERROR);
        $control=$report['control']??null;
        if (!is_array($control)||!is_bool($control['active']??null)||!is_bool($control['testMode']??null)
            ||!in_array($control['mode']??null,['auto','spring','summer','autumn','winter','away'],true)
            ||!in_array($control['season']??null,['spring','summer','autumn','winter','away'],true)) {
            throw new RuntimeException('Bitte die Heizungszentrale auf Version 1.8.2 oder neuer aktualisieren.');
        }
        return $report;
    }

    private function HeatingCenterItem(): ?array
    {
        $ids=$this->HeatingCenterIDs();
        if (!$ids) { return null; }
        $item=['id'=>count($ids)===1?$ids[0]:0,'room'=>'','group'=>'Heizung','name'=>'Heizung','label'=>'Heizung',
            'primary'=>true,'deviceKey'=>'heating-center','deviceName'=>'Heizung',
            'heatingCenter'=>true,'command'=>'HeatingCenterMode','value'=>['raw'=>null,'text'=>'Status nicht verfügbar'],
            'control'=>null,'note'=>'','type'=>1,'role'=>'control'];
        try {
            if (count($ids)!==1) { throw new RuntimeException('Mehrere Heizungszentralen gefunden; keine automatische Bedienzuordnung.'); }
            $report=$this->ReadHeatingCenter($ids[0]);$center=$report['control'];
            $mode=$center['mode']==='auto'?'auto':'manual';
            $run=$center['testMode']?($center['active']?'Testmodus · Dauertest':'Testmodus · Vorschau')
                :($center['active']?'Regelung aktiv':'Regelung nicht aktiv');
            $item['value']=['raw'=>$mode,'text'=>($mode==='auto'?'Automatik':'Manuell').' · '.$run.' · '.$center['seasonLabel']];
            $item['center']=$center;
            $item['note']=$center['reason'].($center['testMode']?' Keine Gerätebefehle.':(!$center['active']?' Zum Starten die Heizungszentrale öffnen.':''));
            if ($this->ConfigBoolean('RoomControlsEnabled')) {
                $item['control']=['kind'=>'enum','options'=>[['value'=>'auto','name'=>'Automatik'],['value'=>'manual','name'=>'Manuell']]];
            }
        } catch (Throwable $error) { $item['note']=$error->getMessage(); }
        return $item;
    }

    private function SetHeatingCenterMode(mixed $value): void
    {
        if (!$this->ConfigBoolean('RoomControlsEnabled')) { throw new RuntimeException('Raumbedienung ist deaktiviert.'); }
        if (!is_string($value)||!in_array($value,['auto','manual','spring','summer','autumn','winter'],true)) {
            throw new InvalidArgumentException('Ungültige Heizungsbetriebsart.');
        }
        $ids=$this->HeatingCenterIDs();
        if (count($ids)!==1) { throw new RuntimeException('Keine eindeutig zugeordnete Heizungszentrale.'); }
        $id=$ids[0];$before=$this->ReadHeatingCenter($id);
        $mode=$value==='manual'?($before['control']['manualSeason']??'winter'):$value;
        if (!in_array($mode,['auto','spring','summer','autumn','winter'],true)) { throw new RuntimeException('Der letzte manuelle Plan ist ungültig.'); }
        $request='hausstatus-heating-'.bin2hex(random_bytes(8));
        // Die Zentrale bleibt alleinige Steuerung. Kein Zugriff auf alte Profilverteiler.
        IPS_RequestAction($id,'Do',json_encode(['action'=>'mode','mode'=>$mode,'requestId'=>$request],JSON_THROW_ON_ERROR));
        $reply=$this->ReadHeatingCenter($id)['lastActionReply']??[];
        if (($reply['requestId']??null)!==$request) { throw new RuntimeException('Die Heizungszentrale arbeitet noch. Bitte Status aktualisieren; nicht sofort erneut umschalten.'); }
        if (($reply['success']??false)!==true) { throw new RuntimeException($reply['error']??'Heizungsmodus konnte nicht übernommen werden.'); }
    }
}
