<?php
declare(strict_types=1);

trait HausstatusShieldSupport
{
    private const SHIELD_FIELDS = ['ShieldOnline', 'ShieldPower', 'ShieldApp', 'ShieldPlayback', 'ShieldTitle', 'ShieldChannel', 'ShieldSubtitle', 'ShieldError', 'ShieldUpdated'];

    private const SHIELD_DEVICES = ['wohnzimmer' => 'Shield', 'ankleidezimmer' => 'ShieldDressing', 'schlafzimmer' => 'ShieldBedroom', 'philips' => 'PhilipsTv'];

    private function CreateShield(): void
    {
        $this->RegisterPropertyBoolean('ShieldEnabled', false);
        $this->RegisterPropertyBoolean('PhilipsTvEnabled', true);
        $this->RegisterAttributeString('ShieldPowerQueue', '{}');
        // Presentation belongs to this view, independently of ConfigSource.
        $this->RegisterPropertyBoolean('ShieldCompact', false);
        // Do not register status variables here: Create() also runs for EVERY
        // presentation tile (and can run before ConfigSource is configured).
        // Only the actual data receiver may own Philips/Shield statuses.
        $this->RegisterTimer('ShieldWatchdog', 0, 'SVHS_ExpireShield($_IPS["TARGET"]);');
    }

    /**
     * Lazily register one device's status variables on its real data receiver.
     * Child tiles never receive UpdateShield; they read their source instead.
     */
    private function EnsureShieldDeviceVariables(string $device): void
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0) {
            throw new RuntimeException('Shield-Variablen duerfen nur auf der Datenquelle angelegt werden.');
        }
        if (!isset(self::SHIELD_DEVICES[$device])) {
            throw new InvalidArgumentException('Unbekanntes Shield-Geraet.');
        }
        $prefix = self::SHIELD_DEVICES[$device];
        // Avoid re-registering variables on every status poll.
        $missing = false;
        foreach (self::SHIELD_FIELDS as $field) {
            $id = @IPS_GetObjectIDByIdent($prefix . substr($field, 6), $this->InstanceID);
            if ($id === false || !IPS_VariableExists($id)) { $missing = true; break; }
        }
        if (!$missing) { return; }
        $label = $device === 'philips' ? 'Philips TV Wohnzimmer' : 'Shield ' . $device;
        $this->RegisterVariableBoolean($prefix . 'Online', $label . ' online', '', 80);
        foreach (['Power' => 'awake/standby', 'App' => 'aktive App', 'Playback' => 'Playback',
            'Title' => 'Medientitel', 'Channel' => 'Sender', 'Subtitle' => 'Sendungsdetails', 'Error' => 'Diagnose'] as $field => $name) {
            $this->RegisterVariableString($prefix . $field, $label . ' ' . $name, '', 81);
        }
        $this->RegisterVariableInteger($prefix . 'Updated', $label . ' letzte Abfrage', '', 87);
    }

    private function ShieldOwner(): int
    {
        // ConfigValue validates the central instance before resolving its variables.
        $this->ConfigBoolean('ShieldEnabled');
        return $this->ReadPropertyInteger('ConfigSource') ?: $this->InstanceID;
    }

    private function HideUnusedShieldVariables(): void
    {
        // Only the central/media owner uses these generated status variables.
        // Detail views read their configured owner and do not need visible copies.
        if ($this->ReadPropertyInteger('ConfigSource')===0 && in_array($this->ReadPropertyInteger('View'),[0,6],true)) { return; }
        foreach(self::SHIELD_DEVICES as $prefix) {
            foreach(self::SHIELD_FIELDS as $field) {
                $id=@IPS_GetObjectIDByIdent($prefix.substr($field,6),$this->InstanceID);
                if ($id!==false && $id>0 && IPS_VariableExists($id)) { IPS_SetHidden($id,true); }
            }
        }
    }

    private function ShieldIDs(): array
    {
        if (!in_array($this->CurrentView(), [0, 6], true)) { return []; }
        $ids = [];
        foreach (self::SHIELD_DEVICES as $device => $prefix) {
            if (!$this->ConfigBoolean($device === 'philips' ? 'PhilipsTvEnabled' : 'ShieldEnabled')) { continue; }
            foreach (self::SHIELD_FIELDS as $field) {
                $id = @IPS_GetObjectIDByIdent($prefix . substr($field, 6), $this->ShieldOwner());
                if ($id !== false) { $ids[] = $id; }
            }
        }
        return $ids;
    }

    private function ShieldPowerQueue(string $device, bool|string|null $on = null, string $ack = ''): ?array
    {
        if ($on !== null && !is_bool($on) && !($device === 'philips' && $on === 'hdmi3')) {
            throw new InvalidArgumentException('Ungültiger Medienbefehl.');
        }
        $key = 'SVHSShieldQueue' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($key, 1000)) { throw new RuntimeException('Shield-Bedienung beschäftigt.'); }
        try {
            $queue = json_decode($this->ReadAttributeString('ShieldPowerQueue'), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($queue)) { $queue = []; }
            if ($on !== null) {
                $queue[$device] = ['id' => bin2hex(random_bytes(12)), 'action' => $on === 'hdmi3' ? 'hdmi3' : ($on ? 'wake' : 'sleep'), 'expires' => time() + 45];
            } elseif (isset($queue[$device]) && (($queue[$device]['expires'] ?? 0) < time() || $ack === ($queue[$device]['id'] ?? ''))) {
                unset($queue[$device]);
            }
            $this->WriteAttributeString('ShieldPowerQueue', json_encode($queue, JSON_THROW_ON_ERROR));
            return $queue[$device] ?? null;
        } finally { IPS_SemaphoreLeave($key); }
    }

    private function SetShieldPower(string $device, mixed $on): void
    {
        if (!isset(self::SHIELD_DEVICES[$device]) || !is_bool($on)) { throw new InvalidArgumentException('Ungültiger Shield-Befehl.'); }
        if (!$this->ConfigBoolean($device === 'philips' ? 'PhilipsTvEnabled' : 'ShieldEnabled')) { throw new RuntimeException('Gerät ist deaktiviert.'); }
        $owner = $this->ShieldOwner();
        if ($owner !== $this->InstanceID) { IPS_RequestAction($owner, 'ShieldPower_' . $device, $on); return; }
        $this->ShieldPowerQueue($device, $on);
    }

    private function SetPhilipsHdmi3(mixed $value): void
    {
        if ($value !== true) { throw new InvalidArgumentException('HDMI 3 erwartet true.'); }
        if (!$this->ConfigBoolean('PhilipsTvEnabled')) { throw new RuntimeException('Philips TV ist deaktiviert.'); }
        $owner = $this->ShieldOwner();
        if ($owner !== $this->InstanceID) { IPS_RequestAction($owner, 'PhilipsHdmi3', true); return; }
        $this->ShieldPowerQueue('philips', 'hdmi3');
    }

    public function UpdateShield(string $JSON): string
    {
        if (strlen($JSON) > 16384) { throw new InvalidArgumentException('Shield-Nachricht zu groß.'); }
        $data = json_decode($JSON, true, 16, JSON_THROW_ON_ERROR);
        $device = is_array($data) ? ($data['device'] ?? 'wohnzimmer') : '';
        if (!is_string($device) || !isset(self::SHIELD_DEVICES[$device])) { throw new InvalidArgumentException('Unbekannte Shield.'); }
        if (!$this->ReadPropertyBoolean($device === 'philips' ? 'PhilipsTvEnabled' : 'ShieldEnabled') || $this->ReadPropertyInteger('ConfigSource') !== 0) {
            throw new RuntimeException('Gerät in der zentralen Hausstatus-Instanz aktivieren.');
        }
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
        foreach (['channel', 'subtitle'] as $key) {
            $data[$key] = $data[$key] ?? '';
            if (!is_string($data[$key]) || strlen($data[$key]) > 4096) {
                throw new InvalidArgumentException('Ungültiges Shield-Senderfeld.');
            }
        }
        if (!$data['online'] || $data['power'] !== 'awake') {
            $data['app'] = ''; $data['title'] = ''; $data['playback'] = 'unknown';
            $data['channel'] = ''; $data['subtitle'] = '';
        }
        $this->EnsureShieldDeviceVariables($device);
        $this->SetValue($prefix . 'Online', $data['online']);
        $this->SetValue($prefix . 'Power', $data['online'] ? $data['power'] : 'unknown');
        foreach (['App' => 'app', 'Playback' => 'playback', 'Title' => 'title', 'Channel' => 'channel', 'Subtitle' => 'subtitle', 'Error' => 'error'] as $ident => $key) {
            $this->SetValue($prefix . $ident, $data[$key]);
        }
        // Receipt time is the commit marker and does not depend on the NAS clock.
        $this->SetValue($prefix . 'Updated', time());
        $ack = $data['powerAck'] ?? '';
        if (!is_string($ack) || strlen($ack) > 64) { $ack = ''; }
        return json_encode(['command' => $this->ShieldPowerQueue($device, null, $ack)], JSON_THROW_ON_ERROR);
    }

    public function ExpireShield(): void
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0) { return; }
        foreach (self::SHIELD_DEVICES as $device => $prefix) {
            if (!$this->ReadPropertyBoolean($device === 'philips' ? 'PhilipsTvEnabled' : 'ShieldEnabled')) { continue; }
            $updatedID = @IPS_GetObjectIDByIdent($prefix . 'Updated', $this->InstanceID);
            if ($updatedID === false || !IPS_VariableExists($updatedID)) { continue; }
            $updated = (int)GetValue($updatedID);
            if ($updated > 0 && time() - $updated > 60) {
                $this->SetValue($prefix . 'Online', false);
                foreach (['Power', 'Playback'] as $name) { $this->SetValue($prefix . $name, 'unknown'); }
                foreach (['App', 'Title', 'Channel', 'Subtitle'] as $name) { $this->SetValue($prefix . $name, ''); }
                $this->SetValue($prefix . 'Error', 'Seit über 60 Sekunden keine Abfrage empfangen.');
            }
        }
    }

    private function ShieldState(string $prefix = 'Shield'): ?array
    {
        if (!$this->ConfigBoolean($prefix === 'PhilipsTv' ? 'PhilipsTvEnabled' : 'ShieldEnabled')) { return null; }
        $data = [];
        foreach (self::SHIELD_FIELDS as $ident) {
            $id = @IPS_GetObjectIDByIdent($prefix . substr($ident, 6), $this->ShieldOwner());
            $data[lcfirst(substr($ident, 6))] = $id !== false && IPS_VariableExists($id) ? GetValue($id) : null;
        }
        $data['stale'] = !$data['updated'] || time() - (int)$data['updated'] > 60;
        if ($data['stale']) {
            $data['online'] = false; $data['power'] = 'unknown'; $data['playback'] = 'unknown';
            $data['app'] = ''; $data['title'] = ''; $data['channel'] = ''; $data['subtitle'] = '';
            $data['error'] = $prefix === 'PhilipsTv' ? 'Keine aktuellen TV-Daten.' : 'Keine aktuellen Shield-Daten.';
        }
        $data['compact'] = $this->ReadPropertyBoolean('ShieldCompact');
        if ($data['compact']) {
            $data = ['online' => $data['online'], 'error' => $data['error'],
                'stale' => $data['stale'], 'compact' => true];
        }
        $data['device'] = array_search($prefix, self::SHIELD_DEVICES, true);
        $data['kind'] = $prefix === 'PhilipsTv' ? 'tv' : 'shield';
        if ($prefix === 'Shield') {
            $data['devices'] = ['ankleidezimmer' => $this->ShieldState('ShieldDressing'),
                'schlafzimmer' => $this->ShieldState('ShieldBedroom')];
        }
        return $data;
    }
}
