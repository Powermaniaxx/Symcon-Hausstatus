<?php
declare(strict_types=1);

trait HausstatusPhilipsTVSupport
{
    private function DirectPhilipsInstance(): int
    {
        $id = $this->ConfigInteger('PhilipsTVInstance');
        if ($id <= 0 || !IPS_InstanceExists($id)
            || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== '{7A9BA592-5E11-4F37-B9D0-740BC1C61C19}') {
            throw new RuntimeException('Eine gültige Philips-TV-Instanz in der zentralen Hausstatus-Konfiguration auswählen.');
        }
        return $id;
    }

    private function DirectPhilipsState(): array
    {
        try {
            $id = $this->DirectPhilipsInstance();
            if (!function_exists('SVPTV_GetState')) { throw new RuntimeException('Philips-TV-Modul zuerst aktualisieren.'); }
            $state = json_decode(SVPTV_GetState($id), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($state) || !isset($state['power'], $state['caps'], $state['enabled'])) { throw new RuntimeException('Ungültiger Philips-TV-Status.'); }
            return $state;
        } catch (Throwable $e) {
            return ['kind' => 'tv', 'direct' => true, 'device' => 'philips', 'enabled' => false,
                'online' => false, 'power' => 'unknown', 'stale' => true, 'caps' => [], 'error' => $e->getMessage()];
        }
    }

    private function DirectPhilipsAction(string $ident, mixed $value): void
    {
        if (!$this->ConfigBoolean('PhilipsTvEnabled')) { throw new RuntimeException('Philips-TV-Bedienung ist deaktiviert.'); }
        $actions = ['PhilipsVolume' => 'Volume', 'PhilipsMute' => 'Mute', 'PhilipsAmbilight' => 'Ambilight', 'PhilipsRemoteKey' => 'RemoteKey', 'PhilipsRefresh' => 'Refresh', 'PhilipsPower' => 'Power'];
        if (!isset($actions[$ident])) { throw new InvalidArgumentException('Unbekannter Philips-Befehl.'); }
        $id = $this->DirectPhilipsInstance();
        if ($ident === 'PhilipsRefresh') {
            if ($value !== true) { throw new InvalidArgumentException('Aktualisieren erwartet true.'); }
        }
        IPS_RequestAction($id, $actions[$ident], $value);
    }
}
