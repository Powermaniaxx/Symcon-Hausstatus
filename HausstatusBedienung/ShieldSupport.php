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

    /**
     * Presentation-only structure in the existing System category:
     * System / Medienstatus / four devices / links to the source variables.
     * The original variables MUST remain direct children of this module,
     * because RegisterVariable* and SetValue use their original idents.
     */
    private function OrganizeShieldStatusLinks(): void
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0
            || $this->ReadPropertyInteger('View') !== 0) { return; }

        $roots = array_unique(array_filter([
            $this->ReadPropertyInteger('HomepageCategory'),
            IPS_GetParent($this->InstanceID)
        ], static fn(int $id): bool => $id > 0));
        $system = 0;
        foreach ($roots as $root) {
            if (!IPS_ObjectExists($root) || (int)IPS_GetObject($root)['ObjectType'] !== 0) { continue; }
            $possible = [];
            foreach (IPS_GetChildrenIDs($root) as $id) {
                $obj = IPS_GetObject($id);
                if ((int)$obj['ObjectType'] !== 0) { continue; }
                if ((string)$obj['ObjectIdent'] === 'SVHSSystemPage'
                    || $this->PresentationBaseName(IPS_GetName($id)) === 'System') { $possible[] = $id; }
            }
            if (count($possible) === 1) { $system = $possible[0]; break; }
            if (count($possible) > 1) {
                throw new RuntimeException('Mehrere System-Kategorien unter ' . $root
                    . ': Kein automatisches Anlegen von Medien-Verknuepfungen.');
            }
        }
        if ($system === 0) {
            $this->SendDebug('Medienstatus', 'Keine eindeutige System-Kategorie gefunden. Keine Medienlinks angelegt.', 0);
            return;
        }

        $status = $this->ShieldStatusLinkCategory($system, 'SVHSMediaStatus', 'Medienstatus', 80);
        $devices = [
            'Shield'         => ['Shield Wohnzimmer', 10],
            'ShieldBedroom'  => ['Shield Schlafzimmer', 20],
            'ShieldDressing' => ['Shield Ankleidezimmer', 30],
            'PhilipsTv'      => ['Philips-TV Wohnzimmer', 40]
        ];
        $fieldNames = [
            'Online'   => 'Online',
            'Power'    => 'Betriebszustand',
            'App'      => 'Aktive App',
            'Playback' => 'Wiedergabe',
            'Title'    => 'Medientitel',
            'Channel'  => 'Sender',
            'Subtitle' => 'Sendungsdetails',
            'Error'    => 'Diagnose',
            'Updated'  => 'Letzte Abfrage'
        ];
        foreach ($devices as $prefix => [$label, $position]) {
            $group = $this->ShieldStatusLinkCategory(
                $status, 'SVHSMedia_' . $prefix, $label, $position
            );
            $i = 0;
            foreach ($fieldNames as $field => $name) {
                $original = @IPS_GetObjectIDByIdent($prefix . $field, $this->InstanceID);
                if ($original === false || !IPS_VariableExists($original)) { continue; }
                $ident = 'SVHSMediaLink_' . $prefix . $field;
                $link = @IPS_GetObjectIDByIdent($ident, $group);
                if ($link === false) {
                    $candidates = [];
                    foreach (IPS_GetChildrenIDs($group) as $child) {
                        if (IPS_LinkExists($child)
                            && (int)IPS_GetLink($child)['TargetID'] === (int)$original) { $candidates[] = $child; }
                    }
                    if (count($candidates) > 1) {
                        $this->SendDebug('Medienstatus', 'Mehrere Verknuepfungen auf ' . $original . '; unveraendert.', 0);
                        continue;
                    }
                    if ($candidates) {
                        $link = $candidates[0];
                        $oldIdent = (string)IPS_GetObject($link)['ObjectIdent'];
                        if ($oldIdent !== '' && $oldIdent !== $ident) {
                            $this->SendDebug('Medienstatus', 'Fremde Verknuepfung ' . $link . ' wird nicht uebernommen.', 0);
                            continue;
                        }
                        IPS_SetIdent($link, $ident);
                    } else {
                        $link = IPS_CreateLink();
                        IPS_SetParent($link, $group);
                        IPS_SetIdent($link, $ident);
                        IPS_SetLinkTargetID($link, (int)$original);
                    }
                }
                if (!IPS_LinkExists($link)) {
                    $this->SendDebug('Medienstatus', 'Kennung ' . $ident . ' durch Fremdobjekt belegt.', 0);
                    continue;
                }
                if ((int)IPS_GetLink($link)['TargetID'] !== (int)$original) {
                    // Do not silently repoint a link somebody has modified.
                    $this->SendDebug('Medienstatus', 'Link ' . $link . ' hat ein anderes Ziel; nicht ueberschrieben.', 0);
                    continue;
                }
                $linkName = $this->VersionedPresentationName($name);
                if (IPS_GetName($link) !== $linkName) { IPS_SetName($link, $linkName); }
                IPS_SetPosition($link, (++$i) * 10);
                if (IPS_GetObject($link)['ObjectIsHidden']) { IPS_SetHidden($link, false); }
                // Keep the originals addressable by the module but hide the
                // clutter in the master. The visible link shows the same value.
                if (!IPS_GetObject($original)['ObjectIsHidden']) { IPS_SetHidden($original, true); }
            }
        }
    }

    private function ShieldStatusLinkCategory(int $parent, string $ident, string $name, int $position): int
    {
        $id = @IPS_GetObjectIDByIdent($ident, $parent);
        if ($id === false) {
            $matches = [];
            foreach (IPS_GetChildrenIDs($parent) as $child) {
                $obj = IPS_GetObject($child);
                if ((int)$obj['ObjectType'] === 0
                    && $this->PresentationBaseName(IPS_GetName($child)) === $name) { $matches[] = $child; }
            }
            if (count($matches) > 1) {
                throw new RuntimeException('Mehrere Kategorien namens ' . $name . ': Kein doppelter Medienordner.');
            }
            if ($matches) {
                $id = $matches[0];
                $oldIdent = (string)IPS_GetObject($id)['ObjectIdent'];
                if ($oldIdent !== '' && $oldIdent !== $ident) {
                    throw new RuntimeException('Medienordner ' . $id . ' hat eine fremde Kennung.');
                }
                IPS_SetIdent($id, $ident);
            } else {
                $id = IPS_CreateCategory();
                IPS_SetParent($id, $parent);
                IPS_SetIdent($id, $ident);
            }
        }
        if ((int)IPS_GetObject($id)['ObjectType'] !== 0) {
            throw new RuntimeException('Medienordner-Kennung ' . $ident . ' ist durch ein Fremdobjekt belegt.');
        }
        $versioned = $this->VersionedPresentationName($name);
        if (IPS_GetName($id) !== $versioned) { IPS_SetName($id, $versioned); }
        IPS_SetPosition($id, $position);
        if (IPS_GetObject($id)['ObjectIsHidden']) { IPS_SetHidden($id, false); }
        return $id;
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
