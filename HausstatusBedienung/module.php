<?php
declare(strict_types=1);

class HausstatusBedienung extends IPSModuleStrict
{
    private const SOURCES = [
        'Presence' => 12936, 'Lock' => 14438, 'DoorContact' => 47467,
        'DoorPermission' => 33983, 'Alarm' => 14477, 'BatteryWarnings' => 0,
        'LightState' => 57731, 'Brightness' => 31102, 'CinemaState' => 45754,
        'CinemaControl' => 0, 'PVPower' => 55194, 'PVEnergy' => 50290,
        'Weather' => 43788, 'Wind' => 29921, 'Rain' => 54690, 'Warning' => 37768,
        'Sunrise' => 33479, 'Sunset' => 16488,
        'DoorOpened' => 19534, 'DoorClosed' => 55355
    ];

    public function Create(): void
    {
        parent::Create();
        foreach (self::SOURCES as $name => $id) {
            $this->RegisterPropertyInteger($name, $id);
        }
        $this->RegisterPropertyBoolean('LightEnabled', false);
        $this->RegisterPropertyBoolean('CinemaEnabled', false);
        $this->RegisterPropertyInteger('LightCommandScript', 0);
        $this->RegisterPropertyString('Rooms', '[{"Name":"Wohnzimmer","Variable":50943},{"Name":"Schlafzimmer","Variable":47495},{"Name":"Flur","Variable":20554},{"Name":"Ankleidezimmer","Variable":45685},{"Name":"Bad","Variable":47658},{"Name":"B\u00fcro","Variable":58625},{"Name":"B\u00fcro Keller","Variable":17053},{"Name":"Sportraum","Variable":13673},{"Name":"Keller","Variable":47839}]');
        $this->RegisterTimer('Refresh', 0, 'SVHS_Refresh($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        $this->SetVisualizationType(1);
        foreach ($this->GetMessageList() as $id => $messages) {
            foreach ($messages as $message) { $this->UnregisterMessage($id, $message); }
        }
        foreach ($this->GetReferenceList() as $id) { $this->UnregisterReference($id); }
        $ids = [];
        foreach (array_keys(self::SOURCES) as $name) { $ids[] = $this->ReadPropertyInteger($name); }
        foreach ($this->Rooms() as $room) { $ids[] = $room['Variable']; }
        foreach (array_unique($ids) as $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterMessage($id, VM_DELETE);
            }
        }
        $script = $this->ReadPropertyInteger('LightCommandScript');
        if ($script > 0 && IPS_ScriptExists($script)) { $this->RegisterReference($script); }
        $this->SetTimerInterval('Refresh', 30000);
        $this->SetStatus(102);
        $this->SetSummary('Hausstatus mit HTML-Bedienung');
        $this->SetBuffer('LastState', '');
        $this->Refresh();
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE || $Message === VM_DELETE) { $this->Refresh(); }
    }

    public function Refresh(): void
    {
        $state = $this->State();
        $encoded = json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($this->GetBuffer('LastState') !== $encoded) {
            $this->SetBuffer('LastState', $encoded);
            $this->UpdateVisualizationValue($encoded);
        }
    }

    public function GetVisualizationTile(): string
    {
        $html = file_get_contents(__DIR__ . '/module.html');
        if ($html === false) { throw new RuntimeException('module.html fehlt.'); }
        $initial = json_encode($this->State(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        return str_replace('/*INITIAL_STATE*/null', $initial, $html);
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if ($Ident === 'Refresh') {
            $this->UpdateVisualizationValue(json_encode($this->State(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }
        if (!in_array($Ident, ['Light', 'Brightness', 'Cinema'], true)) {
            throw new InvalidArgumentException('Unbekannte Bedienaktion.');
        }
        $key = 'SVHSCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($key, 1000)) {
            $this->UpdateVisualizationValue(json_encode(['error' => 'Bitte kurz warten und erneut bedienen.']));
            return;
        }
        try {
            if ($Ident === 'Cinema') {
                if (!$this->ReadPropertyBoolean('CinemaEnabled')) { throw new RuntimeException('Cinema-Bedienung ist deaktiviert.'); }
                if (!is_bool($Value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
                $id = $this->ReadPropertyInteger('CinemaControl');
                $this->ValidateAction($id, 0);
                RequestAction($id, $Value);
            } else {
                if (!$this->ReadPropertyBoolean('LightEnabled')) { throw new RuntimeException('Lichtbedienung ist deaktiviert.'); }
                if ($Ident === 'Light' && !is_bool($Value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
                if ($Ident === 'Brightness' && (!is_int($Value) || $Value < 1 || $Value > 100)) {
                    throw new InvalidArgumentException('Helligkeit muss zwischen 1 und 100 liegen.');
                }
                $script = $this->ReadPropertyInteger('LightCommandScript');
                if ($script > 0) {
                    if (!IPS_ScriptExists($script)) { throw new RuntimeException('Licht-Bedienskript fehlt.'); }
                    // The selected script performs the command AND informs the light automation.
                    IPS_RunScriptEx($script, ['COMMAND' => $Ident, 'VALUE' => $Value, 'SOURCE' => 'HausstatusBedienung']);
                } else {
                    $id = $this->ReadPropertyInteger($Ident === 'Light' ? 'LightState' : 'Brightness');
                    $this->ValidateAction($id, $Ident === 'Light' ? 0 : 1);
                    RequestAction($id, $Value);
                }
            }
            $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'state' => $this->State()], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $e) {
            $this->SendDebug('Bedienfehler', $e->getMessage(), 0);
            $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE));
        } finally {
            IPS_SemaphoreLeave($key);
        }
    }

    private function ValidateAction(int $id, int $type): void
    {
        if (!$this->HasAction($id, $type)) { throw new RuntimeException('Die gewaehlte Variable fehlt, hat den falschen Typ oder keine Bedienaktion.'); }
    }

    private function HasAction(int $id, int $type): bool
    {
        if ($id <= 0 || !IPS_VariableExists($id)) { return false; }
        $v = IPS_GetVariable($id);
        $action = (int)$v['VariableCustomAction'] > 0 ? (int)$v['VariableCustomAction'] : (int)$v['VariableAction'];
        return (int)$v['VariableType'] === $type && $action > 0;
    }

    private function Rooms(): array
    {
        $list = json_decode($this->ReadPropertyString('Rooms'), true);
        if (!is_array($list)) { return []; }
        $result = [];
        foreach ($list as $room) {
            if (is_array($room) && isset($room['Name'], $room['Variable'])
                && is_string($room['Name']) && is_numeric($room['Variable'])) {
                $result[] = ['Name' => $room['Name'], 'Variable' => (int)$room['Variable']];
            }
        }
        return $result;
    }

    private function Read(int $id): array
    {
        if ($id <= 0 || !IPS_VariableExists($id)) { return ['raw' => null, 'text' => 'Unbekannt']; }
        try {
            return ['raw' => GetValue($id), 'text' => (string)GetValueFormatted($id)];
        } catch (Throwable $e) { return ['raw' => null, 'text' => 'Unbekannt']; }
    }

    private function State(): array
    {
        $state = [];
        foreach (array_keys(self::SOURCES) as $name) { $state[$name] = $this->Read($this->ReadPropertyInteger($name)); }
        $state['Rooms'] = [];
        foreach ($this->Rooms() as $room) {
            $state['Rooms'][] = ['name' => $room['Name'], 'value' => $this->Read($room['Variable'])['text']];
        }
        $script = $this->ReadPropertyInteger('LightCommandScript');
        $scriptOK = $script > 0 && IPS_ScriptExists($script);
        $state['Controls'] = [
            'Light' => $this->ReadPropertyBoolean('LightEnabled')
                && ($script > 0 ? $scriptOK : $this->HasAction($this->ReadPropertyInteger('LightState'), 0)),
            'Brightness' => $this->ReadPropertyBoolean('LightEnabled')
                && ($script > 0 ? $scriptOK : $this->HasAction($this->ReadPropertyInteger('Brightness'), 1)),
            'Cinema' => $this->ReadPropertyBoolean('CinemaEnabled')
                && $this->HasAction($this->ReadPropertyInteger('CinemaControl'), 0)
        ];
        return $state;
    }
}
