<?php
declare(strict_types=1);

trait HausstatusDisplaySupport
{
    private const DISPLAY_DEFAULTS = [
        'StatusTitle' => 'Anwesenheit und Alarm', 'DoorTitle' => 'Haustür',
        'Presence' => 'Anwesenheit', 'Lock' => 'Türschloss', 'DoorContact' => 'Türkontakt',
        'DoorPermission' => 'Tür öffnen', 'Alarm' => 'Alarm innen', 'BatteryWarnings' => 'Geräte',
        'LightState' => 'Wohnzimmerlicht', 'Dining' => 'Esstisch', 'CinemaState' => 'Cinema 40',
        'PVPower' => 'PV-Anlage', 'OutdoorTitle' => 'Markise und Dachfenster',
        'Awning' => 'Markise', 'Roof' => 'Dachfenster', 'MotionTitle' => 'Bewegung – letzte 24 Stunden',
        'TemperatureTitle' => 'Räume', 'HeatingProfile' => 'Heizungsprofil', 'WeatherTitle' => 'Wetter',
        'NetworkTitle' => 'FritzBox'
    ];
    private const DISPLAY_LABELS = [
        'StatusTitle' => 'Überschrift Anwesenheit / Alarm', 'DoorTitle' => 'Überschrift Haustür',
        'Presence' => 'Anwesenheit', 'Lock' => 'Türschloss', 'DoorContact' => 'Türkontakt',
        'DoorPermission' => 'Türfreigabe', 'Alarm' => 'Alarm', 'BatteryWarnings' => 'Gerätewarnungen',
        'LightState' => 'Hauptlicht', 'Dining' => 'Zusätzliche Lampe', 'CinemaState' => 'Mediengerät',
        'PVPower' => 'PV-Anlage', 'OutdoorTitle' => 'Überschrift Außenbereich', 'Awning' => 'Beschattung',
        'Roof' => 'Dachfenster', 'MotionTitle' => 'Bewegungsverlauf', 'TemperatureTitle' => 'Temperaturen',
        'HeatingProfile' => 'Heizungsprofil', 'WeatherTitle' => 'Wetter', 'NetworkTitle' => 'Netzwerk / Router'
    ];

    private function DefaultDisplayTexts(): string
    {
        $rows = [];
        foreach (self::DISPLAY_DEFAULTS as $key => $text) {
            $rows[] = ['Key' => $key, 'Text' => $text, 'Description' => '', 'OnText' => '', 'OffText' => ''];
        }
        return json_encode($rows, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function DisplaySettings(): array
    {
        $result = [];
        foreach (self::DISPLAY_DEFAULTS as $key => $text) {
            $result[$key] = ['Text' => $text, 'Description' => '', 'OnText' => '', 'OffText' => ''];
        }
        $rows = json_decode($this->ConfigString('DisplayTexts'), true);
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !is_string($row['Key'] ?? null) || !isset($result[$row['Key']])) { continue; }
            foreach (['Text', 'Description', 'OnText', 'OffText'] as $field) {
                if (!is_string($row[$field] ?? null)) { continue; }
                $value = trim($row[$field]);
                if ($field !== 'Text' || $value !== '') { $result[$row['Key']][$field] = $value; }
            }
        }
        return $result;
    }

    private function DisplayText(string $key): string
    {
        return $this->DisplaySettings()[$key]['Text'] ?? $key;
    }

    private function DisplayRows(): array
    {
        $rows = [];
        foreach ($this->DisplaySettings() as $key => $settings) {
            $rows[] = ['Key' => $key, 'Section' => self::DISPLAY_LABELS[$key]] + $settings;
        }
        return $rows;
    }

    private function HeatingProfileEntry(): ?array
    {
        $id = $this->ConfigInteger('HeatingProfile');
        if ($id <= 0) { return null; }
        return ['room' => '', 'group' => 'Heizung', 'name' => $this->DisplayText('HeatingProfile'),
            'id' => $id, 'type' => IPS_VariableExists($id) ? 2 : -1, 'operate' => true];
    }

    private function HeatingProfileItem(): ?array
    {
        if (!$this->HasTemperatureView()) { return null; }
        $entry = $this->HeatingProfileEntry();
        return $entry !== null ? $this->RoomItem($entry) : null;
    }

    private function IsCinemaVariable(int $id): bool
    {
        if ($id <= 0 || !IPS_VariableExists($id)) { return false; }
        $parent = IPS_GetParent($id);
        if (!IPS_InstanceExists($parent)) { return false; }
        foreach (['CinemaState', 'CinemaControl', 'CinemaSource', 'CinemaVolume'] as $source) {
            $configured = $this->ConfigInteger($source);
            if ($configured > 0 && IPS_VariableExists($configured) && IPS_GetParent($configured) === $parent) { return true; }
        }
        return false;
    }

    private function SetLight(string $command, mixed $value): void
    {
        if (!$this->ConfigBoolean('LightEnabled')) { throw new RuntimeException('Lichtbedienung ist deaktiviert.'); }
        if ($command === 'Light' && !is_bool($value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
        if ($command === 'Brightness' && (!is_int($value) || $value < 1 || $value > 100)) {
            throw new InvalidArgumentException('Helligkeit muss zwischen 1 und 100 liegen.');
        }
        $script = $this->ConfigInteger('LightCommandScript');
        if ($script > 0) {
            if (!IPS_ScriptExists($script)) { throw new RuntimeException('Licht-Bedienskript fehlt.'); }
            // A selected script remains the only command path, including its automation protection.
            if (!IPS_RunScriptEx($script, ['COMMAND' => $command, 'VALUE' => $value, 'SOURCE' => 'HausstatusBedienung'])) {
                throw new RuntimeException('Licht-Bedienskript konnte nicht gestartet werden.');
            }
            return;
        }
        $id = $this->ConfigInteger($command === 'Light' ? 'LightState' : 'Brightness');
        $this->ValidateAction($id, $command === 'Light' ? 0 : 1);
        if (!RequestAction($id, $value)) { throw new RuntimeException('Lichtaktion fehlgeschlagen.'); }
    }
}

