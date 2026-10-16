<?php
declare(strict_types=1);

trait HausstatusShieldSupport
{
    private const SHIELD_FIELDS = ['ShieldOnline', 'ShieldPower', 'ShieldApp', 'ShieldPlayback', 'ShieldTitle', 'ShieldError', 'ShieldUpdated'];

    private const SHIELD_DEVICES = ['wohnzimmer' => 'Shield', 'ankleidezimmer' => 'ShieldDressing', 'schlafzimmer' => 'ShieldBedroom'];

    private function CreateShield(): void
    {
        $this->RegisterPropertyBoolean('ShieldEnabled', false);
        // Presentation belongs to this view, independently of ConfigSource.
        $this->RegisterPropertyBoolean('ShieldCompact', false);
        foreach (self::SHIELD_DEVICES as $room => $prefix) {
            $this->RegisterVariableBoolean($prefix . 'Online', 'Shield ' . $room . ' online', '', 80);
            foreach (['Power' => 'awake/standby', 'App' => 'aktive App', 'Playback' => 'Playback',
                'Title' => 'Medientitel', 'Error' => 'Diagnose'] as $field => $name) {
                $this->RegisterVariableString($prefix . $field, 'Shield ' . $room . ' ' . $name, '', 81);
            }
            $this->RegisterVariableInteger($prefix . 'Updated', 'Shield ' . $room . ' letzte Abfrage', '', 87);
        }
        $this->RegisterTimer('ShieldWatchdog', 0, 'SVHS_ExpireShield($_IPS["TARGET"]);');
    }

    private function ShieldOwner(): int
    {
        // ConfigValue validates the central instance before resolving its variables.
        $this->ConfigBoolean('ShieldEnabled');
        return $this->ReadPropertyInteger('ConfigSource') ?: $this->InstanceID;
    }

    private function ShieldIDs(): array
    {
        if (!$this->ConfigBoolean('ShieldEnabled') || !in_array($this->CurrentView(), [0, 6], true)) { return []; }
        $ids = [];
        foreach (self::SHIELD_DEVICES as $prefix) {
            foreach (self::SHIELD_FIELDS as $field) {
                $id = @IPS_GetObjectIDByIdent($prefix . substr($field, 6), $this->ShieldOwner());
                if ($id !== false) { $ids[] = $id; }
            }
        }
        return $ids;
    }

    public function UpdateShield(string $JSON): void
    {
        if (!$this->ReadPropertyBoolean('ShieldEnabled') || $this->ReadPropertyInteger('ConfigSource') !== 0) {
            throw new RuntimeException('Shield in der zentralen Hausstatus-Instanz aktivieren.');
        }
        if (strlen($JSON) > 16384) { throw new InvalidArgumentException('Shield-Nachricht zu groß.'); }
        $data = json_decode($JSON, true, 16, JSON_THROW_ON_ERROR);
        $device = is_array($data) ? ($data['device'] ?? 'wohnzimmer') : '';
        if (!is_string($device) || !isset(self::SHIELD_DEVICES[$device])) { throw new InvalidArgumentException('Unbekannte Shield.'); }
        $prefix = self::SHIELD_DEVICES[$device];
        if (!is_array($data) || !isset($data['online']) || !is_bool($data['online'])
            || !in_array($data['power'] ?? null, ['awake', 'standby', 'unknown'], true)
            || !in_array($data['playback'] ?? null, ['playing', 'paused', 'stopped', 'unknown'], true)) {
            throw new InvalidArgumentException('Ungültiger Shield-Status.');
        }
        foreach (['app', 'title', 'error'] as $key) {
            if (!isset($data[$key]) || !is_string($data[$key]) || strlen($data[$key]) > 4096) {
                throw new InvalidArgumentException('Ungültiges Shield-Textfeld.');
            }
        }
        if (!$data['online'] || $data['power'] !== 'awake') {
            $data['app'] = ''; $data['title'] = ''; $data['playback'] = 'unknown';
        }
        $this->SetValue($prefix . 'Online', $data['online']);
        $this->SetValue($prefix . 'Power', $data['online'] ? $data['power'] : 'unknown');
        foreach (['App' => 'app', 'Playback' => 'playback', 'Title' => 'title', 'Error' => 'error'] as $ident => $key) {
            $this->SetValue($prefix . $ident, $data[$key]);
        }
        // Receipt time is the commit marker and does not depend on the NAS clock.
        $this->SetValue($prefix . 'Updated', time());
    }

    public function ExpireShield(): void
    {
        if (!$this->ReadPropertyBoolean('ShieldEnabled') || $this->ReadPropertyInteger('ConfigSource') !== 0) { return; }
        foreach (self::SHIELD_DEVICES as $prefix) {
            $updated = $this->GetValue($prefix . 'Updated');
            if ($updated > 0 && time() - $updated > 60) {
                $this->SetValue($prefix . 'Online', false);
                foreach (['Power', 'Playback'] as $name) { $this->SetValue($prefix . $name, 'unknown'); }
                foreach (['App', 'Title'] as $name) { $this->SetValue($prefix . $name, ''); }
                $this->SetValue($prefix . 'Error', 'Seit über 60 Sekunden keine Abfrage empfangen.');
            }
        }
    }

    private function ShieldState(string $prefix = 'Shield'): ?array
    {
        if (!$this->ConfigBoolean('ShieldEnabled')) { return null; }
        $data = [];
        foreach (self::SHIELD_FIELDS as $ident) {
            $id = @IPS_GetObjectIDByIdent($prefix . substr($ident, 6), $this->ShieldOwner());
            $data[lcfirst(substr($ident, 6))] = $id !== false && IPS_VariableExists($id) ? GetValue($id) : null;
        }
        $data['stale'] = !$data['updated'] || time() - (int)$data['updated'] > 60;
        if ($data['stale']) {
            $data['online'] = false; $data['power'] = 'unknown'; $data['playback'] = 'unknown';
            $data['app'] = ''; $data['title'] = '';
            $data['error'] = 'Keine aktuellen Shield-Daten.';
        }
        $data['compact'] = $this->ReadPropertyBoolean('ShieldCompact');
        if ($data['compact']) {
            $data = ['online' => $data['online'], 'error' => $data['error'],
                'stale' => $data['stale'], 'compact' => true];
        }
        if ($prefix === 'Shield') {
            $data['devices'] = ['ankleidezimmer' => $this->ShieldState('ShieldDressing'),
                'schlafzimmer' => $this->ShieldState('ShieldBedroom')];
        }
        return $data;
    }
}
