<?php
declare(strict_types=1);

class HausstatusBedienung extends IPSModuleStrict
{
    private const SOURCES = [
        'Presence' => 12936, 'Lock' => 14438, 'DoorContact' => 47467,
        'DoorControl' => 30053, 'DoorPermission' => 33983, 'Alarm' => 14477, 'BatteryWarnings' => 0,
        'LightState' => 57731, 'Brightness' => 31102, 'CinemaState' => 45754,
        'CinemaControl' => 0, 'CinemaSource' => 0, 'CinemaVolume' => 45376, 'PVPower' => 55194, 'PVEnergy' => 50290,
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
        $this->RegisterPropertyInteger('View', 0);
        $this->RegisterPropertyBoolean('ActiveView', true);
        $this->RegisterPropertyInteger('ConfigSource', 0);
        $this->RegisterPropertyInteger('MotionArchive', 0);
        $this->RegisterPropertyBoolean('MotionLogging', true);
        $this->RegisterPropertyString('MotionSensors', '[{"Name":"Flur","Variable":26325},{"Name":"Wohnzimmer Bewegung","Variable":58943},{"Name":"Wohnzimmer Praesenz","Variable":37345},{"Name":"Terrasse","Variable":34118},{"Name":"Schlafzimmer Praesenz","Variable":0},{"Name":"Keller","Variable":0}]');
        $this->RegisterPropertyBoolean('DoorEnabled', false);
        $this->RegisterPropertyBoolean('LightEnabled', false);
        $this->RegisterPropertyBoolean('CinemaEnabled', false);
        $this->RegisterPropertyInteger('LightCommandScript', 0);
        $this->RegisterPropertyString('Rooms', '[{"Name":"Wohnzimmer","Variable":50943},{"Name":"Schlafzimmer","Variable":47495},{"Name":"Flur","Variable":20554},{"Name":"Ankleidezimmer","Variable":45685},{"Name":"Bad","Variable":47658},{"Name":"B\u00fcro","Variable":58625},{"Name":"B\u00fcro Keller","Variable":17053},{"Name":"Sportraum","Variable":13673},{"Name":"Keller","Variable":47839}]');
        $this->RegisterTimer('Refresh', 0, 'SVHS_Refresh($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        if (!$this->ReadPropertyBoolean('ActiveView')) {
            $this->SetTimerInterval('Refresh', 0);
            $this->SetBuffer('DoorChallenges', '{}');
            foreach ($this->GetMessageList() as $id => $messages) {
                foreach ($messages as $message) { $this->UnregisterMessage($id, $message); }
            }
            $this->SetStatus(102);
            $this->SetSummary('Ansicht pausiert');
            return;
        }
        $this->SetVisualizationType(1);
        $this->SetBuffer('DoorChallenges', '{}');
        foreach ($this->GetMessageList() as $id => $messages) {
            foreach ($messages as $message) { $this->UnregisterMessage($id, $message); }
        }
        foreach ($this->GetReferenceList() as $id) { $this->UnregisterReference($id); }
        
        $this->SetBuffer('MotionCache', '');
        $archive = $this->MotionArchive();
        if ($archive > 0) { $this->RegisterReference($archive); }
        if ($this->HasMotionView() && $archive > 0 && $this->ConfigBoolean('MotionLogging')) {
            foreach ($this->MotionSensors() as $sensor) {
                $id = $sensor['Variable'];
                if (IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0) {
                    try { AC_SetLoggingStatus($archive, $id, true); }
                    catch (Throwable $e) { $this->SendDebug('Bewegungsarchiv', $e->getMessage(), 0); }
                }
            }
        }
        $ids = [];
        foreach ($this->SourceNames() as $name) { $ids[] = $this->ConfigInteger($name); }
        if (in_array($this->ReadPropertyInteger('View'), [0, 9], true)) {
            foreach ($this->Rooms() as $room) { $ids[] = $room['Variable']; }
        }
        if ($this->HasMotionView()) {
            foreach ($this->MotionSensors() as $sensor) { $ids[] = $sensor['Variable']; }
        }
        $source = $this->ReadPropertyInteger('ConfigSource');
        if ($source > 0 && IPS_InstanceExists($source)) { $this->RegisterReference($source); }
        foreach (array_unique($ids) as $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterMessage($id, VM_DELETE);
            }
        }
        $script = $this->ConfigInteger('LightCommandScript');
        if ($script > 0 && IPS_ScriptExists($script)) { $this->RegisterReference($script); }
        $this->SetTimerInterval('Refresh', 30000);
        $this->SetStatus(102);
        $this->SetSummary('Hausstatus mit HTML-Bedienung');
        $this->SetBuffer('LastState', '');
        $this->Refresh();
        if ($this->ReadPropertyInteger('ConfigSource') === 0) {
            foreach (IPS_GetInstanceListByModuleID('{9E33E109-4881-4E78-9906-38CAC2F1E210}') as $child) {
                if ($child !== $this->InstanceID && IPS_GetProperty($child, 'ConfigSource') === $this->InstanceID) { IPS_ApplyChanges($child); }
            }
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE || $Message === VM_DELETE) {
            foreach ($this->MotionSensors() as $sensor) {
                if ($sensor['Variable'] === $SenderID) { $this->SetBuffer('MotionCache', ''); break; }
            }
            $this->Refresh();
        }
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
        if (!$this->ReadPropertyBoolean('ActiveView')) { throw new RuntimeException('Diese Ansicht ist pausiert. Bitte die gemeinsame Hausstatus-Kachel verwenden.'); }
        if ($Ident === 'Refresh') {
            $this->UpdateVisualizationValue(json_encode($this->State(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            return;
        }
        if (!in_array($Ident, ['Light', 'Brightness', 'Cinema', 'CinemaSource', 'CinemaVolume', 'DoorConfirm', 'DoorOpen', 'DoorPermission'], true)) {
            throw new InvalidArgumentException('Unbekannte Bedienaktion.');
        }
        $key = 'SVHSCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($key, 1000)) {
            $this->UpdateVisualizationValue(json_encode(['error' => 'Bitte kurz warten und erneut bedienen.']));
            return;
        }
        try {
            if ($Ident === 'DoorPermission') {
                $this->SetDoorPermission($Value);
            } elseif ($Ident === 'DoorConfirm') {
                $this->PrepareDoor($Value);
                return;
            } elseif ($Ident === 'DoorOpen') {
                $this->OpenDoor($Value);
            } elseif ($Ident === 'CinemaSource') {
                $this->SetCinemaSource($Value);
            } elseif ($Ident === 'CinemaVolume') {
                $this->SetCinemaVolume($Value);
            } elseif ($Ident === 'Cinema') {
                if (!$this->ConfigBoolean('CinemaEnabled')) { throw new RuntimeException('Cinema-Bedienung ist deaktiviert.'); }
                if (!is_bool($Value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
                $id = $this->ConfigInteger('CinemaControl');
                $this->ValidateAction($id, 0);
                RequestAction($id, $Value);
            } else {
                if (!$this->ConfigBoolean('LightEnabled')) { throw new RuntimeException('Lichtbedienung ist deaktiviert.'); }
                if ($Ident === 'Light' && !is_bool($Value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
                if ($Ident === 'Brightness' && (!is_int($Value) || $Value < 1 || $Value > 100)) {
                    throw new InvalidArgumentException('Helligkeit muss zwischen 1 und 100 liegen.');
                }
                $script = $this->ConfigInteger('LightCommandScript');
                if ($script <= 0) { throw new RuntimeException('Zuerst Lichtautomatik 3.3 als Licht-Bedienskript auswaehlen.'); }
                if (!IPS_ScriptExists($script)) { throw new RuntimeException('Licht-Bedienskript fehlt.'); }
                // The selected script performs the command AND informs the light automation.
                if (!IPS_RunScriptEx($script, ['COMMAND' => $Ident, 'VALUE' => $Value, 'SOURCE' => 'HausstatusBedienung'])) {
                    throw new RuntimeException('Licht-Bedienskript konnte nicht gestartet werden.');
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


    private function ConfigValue(string $name): mixed
    {
        $source = $this->ReadPropertyInteger('ConfigSource');
        if ($source === 0) { return IPS_GetProperty($this->InstanceID, $name); }
        if ($source === $this->InstanceID || !IPS_InstanceExists($source)
            || IPS_GetInstance($source)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
            || IPS_GetProperty($source, 'ConfigSource') !== 0) {
            throw new RuntimeException('Zentrale Hausstatus-Konfiguration fehlt oder ist ungueltig.');
        }
        return IPS_GetProperty($source, $name);
    }

    private function ConfigInteger(string $name): int { return (int)$this->ConfigValue($name); }
    private function ConfigBoolean(string $name): bool { return (bool)$this->ConfigValue($name); }
    private function ConfigString(string $name): string { return (string)$this->ConfigValue($name); }
    private function HasMotionView(): bool { return in_array($this->ReadPropertyInteger('View'), [0, 8], true); }

    private function SourceNames(): array
    {
        $views = [1 => ['Presence', 'Alarm'], 2 => ['Lock', 'DoorContact', 'DoorPermission', 'DoorControl', 'DoorOpened', 'DoorClosed'],
            4 => ['BatteryWarnings'], 5 => ['LightState', 'Brightness'],
            6 => ['CinemaState', 'CinemaControl', 'CinemaSource', 'CinemaVolume'], 7 => ['PVPower', 'PVEnergy'], 8 => [], 9 => [],
            10 => ['Weather', 'Wind', 'Rain', 'Warning', 'Sunrise', 'Sunset']];
        return $views[$this->ReadPropertyInteger('View')] ?? array_keys(self::SOURCES);
    }

    private function CinemaSourceOptions(): array
    {
        $id = $this->ConfigInteger('CinemaSource');
        if (!$this->ConfigBoolean('CinemaEnabled') || !$this->HasAction($id, 1)) { return []; }
        $presentation = $this->VariablePresentation($id);
        if (($presentation['PRESENTATION'] ?? '') === '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}') {
            $options = $presentation['OPTIONS'] ?? [];
            if (is_string($options)) { $options = json_decode($options, true); }
            $result = [];
            foreach (is_array($options) ? $options : [] as $option) {
                if (!is_array($option) || !isset($option['Value'], $option['Caption'])) { continue; }
                $value = $option['Value'];
                if ((is_int($value) || is_float($value)) && is_finite((float)$value)
                    && (float)$value === (float)(int)$value) {
                    $result[(int)$value] = ['value' => (int)$value, 'name' => strip_tags((string)$option['Caption'])];
                }
            }
            return array_values($result);
        }
        if (!in_array($presentation['PRESENTATION'] ?? '', ['', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}'], true)) { return []; }
        $v = IPS_GetVariable($id);
        $name = $presentation['PROFILE'] ?? ($v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile']);
        if ($name === '' || !IPS_VariableProfileExists($name)) { return []; }
        $result = [];
        foreach (IPS_GetVariableProfile($name)['Associations'] as $a) {
            if (is_numeric($a['Value']) && (float)$a['Value'] === (float)(int)$a['Value']) {
                $result[] = ['value' => (int)$a['Value'], 'name' => strip_tags((string)$a['Name'])];
            }
        }
        return $result;
    }

    private function SetCinemaSource(mixed $Value): void
    {
        if (!is_int($Value) || !in_array($Value, array_column($this->CinemaSourceOptions(), 'value'), true)) {
            throw new RuntimeException('Quelle ist nicht als schaltbare Auswahl eingerichtet.');
        }
        if (!RequestAction($this->ConfigInteger('CinemaSource'), $Value)) { throw new RuntimeException('Quellenwahl fehlgeschlagen.'); }
    }

    private function VariablePresentation(int $id): array
    {
        try { return IPS_GetVariablePresentation($id); }
        catch (Throwable $e) { $this->SendDebug('Variablendarstellung', $e->getMessage(), 0); return []; }
    }

    private function CinemaVolumePlan(): array
    {
        $id = $this->ConfigInteger('CinemaVolume');
        if (!$this->ConfigBoolean('CinemaEnabled')) { throw new RuntimeException('Cinema-Bedienung ist im Modul deaktiviert.'); }
        if ($id <= 0 || !IPS_VariableExists($id)) { throw new RuntimeException('Lautstaerkevariable fehlt.'); }
        $v = IPS_GetVariable($id); $type = (int)$v['VariableType'];
        if (!in_array($type, [1, 2], true)) { throw new RuntimeException('Lautstaerke muss eine Integer- oder Float-Variable sein.'); }
        if (!$this->HasAction($id, $type)) { throw new RuntimeException('Die Lautstaerkevariable hat keine aktive Bedienaktion.'); }
        $presentation = $this->VariablePresentation($id);
        $kind = $presentation['PRESENTATION'] ?? '';
        if ($kind === '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}') {
            $min = $presentation['MIN'] ?? null; $max = $presentation['MAX'] ?? null;
            $step = $presentation['STEP_SIZE'] ?? 0;
        } elseif (in_array($kind, ['', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}'], true)) {
            $name = $presentation['PROFILE'] ?? ($v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile']);
            if ($name === '' || !IPS_VariableProfileExists($name)) {
                throw new RuntimeException('In der Lautstaerkevariable sind keine Reglergrenzen hinterlegt.');
            }
            $p = IPS_GetVariableProfile($name);
            $min = $p['MinValue']; $max = $p['MaxValue']; $step = $p['StepSize'];
        } else {
            throw new RuntimeException('Die Lautstaerkevariable verwendet keine Schieberegler-Darstellung.');
        }
        if (!is_numeric($min) || !is_numeric($max) || !is_numeric($step)
            || !is_finite((float)$min) || !is_finite((float)$max) || !is_finite((float)$step)
            || (float)$max <= (float)$min || (float)$step < 0) {
            throw new RuntimeException('Die Lautstaerkedarstellung hat ungueltige Reglergrenzen.');
        }
        $min = (float)$min; $max = (float)$max; $step = (float)$step;
        if ($type === 1 && (floor($min) !== $min || floor($max) !== $max || floor($step) !== $step)) {
            throw new RuntimeException('Reglergrenzen und Schrittweite passen nicht zum Integer-Typ.');
        }
        // Step 0 means no fixed increment in Symcon, not an invented 0.5 dB step.
        $step = $step > 0 ? $step : ($type === 1 ? 1 : 'any');
        return ['min' => $min, 'max' => $max, 'step' => $step, 'type' => $type];
    }

    private function SetCinemaVolume(mixed $Value): void
    {
        $control = $this->CinemaVolumePlan();
        if ((!is_int($Value) && !is_float($Value)) || !is_finite((float)$Value)
            || $Value < $control['min'] || $Value > $control['max']
            || ($control['type'] === 1 && floor($Value) !== (float)$Value)) {
            throw new RuntimeException('Lautstaerke liegt ausserhalb der konfigurierten Reglergrenzen.');
        }
        if (is_numeric($control['step'])) {
            $steps = ((float)$Value - $control['min']) / $control['step'];
            if (abs($steps - round($steps)) > 0.000001) { throw new RuntimeException('Lautstaerke passt nicht zur konfigurierten Schrittweite.'); }
        }
        $value = $control['type'] === 1 ? (int)$Value : (float)$Value;
        if (!RequestAction($this->ConfigInteger('CinemaVolume'), $value)) { throw new RuntimeException('Lautstaerkeaktion fehlgeschlagen.'); }
    }

    private function CanSetDoorPermission(): bool
    {
        $id = $this->ConfigInteger('DoorPermission');
        return $this->ConfigBoolean('DoorEnabled') && $id > 0
            && IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0
            && (int)IPS_GetVariable($id)['VariableCustomAction'] !== 1;
    }

    private function SetDoorPermission(mixed $Value): void
    {
        if (!is_bool($Value) || !$this->CanSetDoorPermission()) {
            throw new RuntimeException('Tuerfreigabe ist deaktiviert oder keine Boolean-Variable.');
        }
        $id = $this->ConfigInteger('DoorPermission');
        if ($this->HasAction($id, 0)) {
            if (!RequestAction($id, $Value)) { throw new RuntimeException('Freigabeaktion fehlgeschlagen.'); }
        } else {
            // This is the explicitly selected permission flag, not the lock actuator.
            SetValueBoolean($id, $Value);
        }
        // Any pending opening confirmation is invalid after a permission command.
        $this->SetBuffer('DoorChallenges', '{}');
    }

    private function DoorPlan(): array
    {
        if (!$this->ConfigBoolean('DoorEnabled')) {
            throw new RuntimeException('Tueroeffnung ist im Modul deaktiviert.');
        }
        $permissionID = $this->ConfigInteger('DoorPermission');
        if (!IPS_VariableExists($permissionID)
            || IPS_GetVariable($permissionID)['VariableType'] !== 0
            || GetValueBoolean($permissionID) !== true) {
            throw new RuntimeException('Tuer oeffnen ist gesperrt. Zuerst die vorhandene Tuerfreigabe aktivieren.');
        }
        $contact = $this->Read($this->ConfigInteger('DoorContact'))['raw'];
        if ($contact === true || $contact === 1) {
            throw new RuntimeException('Die Tuer ist bereits offen.');
        }
        $id = $this->ConfigInteger('DoorControl');
        $this->ValidateAction($id, 1);
        $v = IPS_GetVariable($id);
        // Use the existing custom door script, never write directly to the lock.
        $script = (int)$v['VariableCustomAction'];
        if ($script <= 0 || !IPS_ScriptExists($script)) {
            throw new RuntimeException('Die Tuer-Bedienvariable muss ein vorhandenes Aktionsskript besitzen.');
        }
        $profileName = $v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile'];
        if ($profileName === '' || !IPS_VariableProfileExists($profileName)) {
            throw new RuntimeException('Das Variablenprofil fuer die Tuerbedienung fehlt.');
        }
        $values = [];
        foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
            $name = trim(strip_tags((string)$association['Name']));
            $name = strtolower(str_replace(["\u{d6}", "\u{f6}", "\u{dc}", "\u{fc}"], ['Oe', 'oe', 'Ue', 'ue'], $name));
            if (in_array($name, ['oeffnen', 'tuer oeffnen', 'open'], true)) {
                $value = $association['Value'];
                if (is_numeric($value) && (float)$value === (float)(int)$value) { $values[] = (int)$value; }
            }
        }
        if (count($values) !== 1) {
            throw new RuntimeException('Die Aktion Oeffnen ist im Tuerprofil nicht eindeutig. Kein Befehl gesendet.');
        }
        return ['id' => $id, 'value' => $values[0], 'script' => $script, 'permission' => $permissionID];
    }

    private function DoorAvailable(): bool
    {
        return $this->DoorReason() === '';
    }

    private function DoorReason(): string
    {
        try { $this->DoorPlan(); return ''; } catch (Throwable $e) { return $e->getMessage(); }
    }

    private function PrepareDoor(mixed $Value): void
    {
        if (!is_array($Value) || !isset($Value['request']) || !is_string($Value['request'])
            || !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $Value['request'])) {
            throw new InvalidArgumentException('Ungueltige Bestaetigungsanfrage.');
        }
        $plan = $this->DoorPlan();
        $pending = json_decode($this->GetBuffer('DoorChallenges'), true) ?: [];
        $pending = array_filter($pending, static fn(array $entry): bool => $entry['expires'] > time());
        if (count($pending) >= 10) { throw new RuntimeException('Bitte kurz warten und erneut versuchen.'); }
        $token = bin2hex(random_bytes(24));
        $expires = time() + 20;
        $pending[$Value['request']] = ['token' => $token, 'expires' => $expires, 'plan' => $plan];
        $this->SetBuffer('DoorChallenges', json_encode($pending, JSON_THROW_ON_ERROR));
        $this->UpdateVisualizationValue(json_encode(['doorConfirmation' => [
            'request' => $Value['request'], 'token' => $token, 'expires' => $expires, 'ttl' => 20
        ]], JSON_THROW_ON_ERROR));
    }

    private function OpenDoor(mixed $Value): void
    {
        if (!is_array($Value) || !isset($Value['request'], $Value['token'])
            || !is_string($Value['request']) || !is_string($Value['token'])
            || strlen($Value['request']) > 64 || strlen($Value['token']) !== 48) {
            throw new InvalidArgumentException('Bestaetigung fehlt.');
        }
        $pending = json_decode($this->GetBuffer('DoorChallenges'), true) ?: [];
        $entry = $pending[$Value['request']] ?? null;
        if (!$entry || $entry['expires'] <= time() || !hash_equals($entry['token'], $Value['token'])) {
            throw new RuntimeException('Bestaetigung fehlt oder ist abgelaufen. Bitte erneut Tuer oeffnen waehlen.');
        }
        // Consume first; even a failing action must not be replayed.
        unset($pending[$Value['request']]);
        $this->SetBuffer('DoorChallenges', json_encode($pending, JSON_THROW_ON_ERROR));
        $plan = $this->DoorPlan();
        if ($plan !== $entry['plan']) { throw new RuntimeException('Tuerkonfiguration wurde geaendert. Bitte erneut bestaetigen.'); }
        if (!RequestAction($plan['id'], $plan['value'])) {
            throw new RuntimeException('Das Tuer-Aktionsskript konnte nicht ausgefuehrt werden.');
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
        if ((int)$v['VariableCustomAction'] === 1) { return false; }
        $action = (int)$v['VariableCustomAction'] > 0 ? (int)$v['VariableCustomAction'] : (int)$v['VariableAction'];
        return (int)$v['VariableType'] === $type && $action > 0;
    }

    private function Rooms(): array
    {
        $list = json_decode($this->ConfigString('Rooms'), true);
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

    private function MotionSensors(): array
    {
        $list = json_decode($this->ConfigString('MotionSensors'), true);
        if (!is_array($list)) { return []; }
        return array_values(array_filter($list, static fn($v): bool => is_array($v)
            && isset($v['Name'], $v['Variable']) && is_string($v['Name']) && is_int($v['Variable'])));
    }

    private function MotionArchive(): int
    {
        $id = $this->ConfigInteger('MotionArchive');
        if ($id > 0) { return IPS_InstanceExists($id) ? $id : 0; }
        $ids = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($ids) === 1 ? $ids[0] : 0;
    }

    private function MotionState(): array
    {
        $now = time(); $start = $now - 86400;
        $cache = json_decode($this->GetBuffer('MotionCache'), true);
        if (!is_array($cache) || ($cache['time'] ?? 0) < $now - 30) {
            $cache = ['time' => $now, 'history' => []];
            $archive = $this->MotionArchive();
            foreach ($this->MotionSensors() as $sensor) {
                $id = $sensor['Variable'];
                $history = ['entries' => [], 'previous' => null, 'note' => ''];
                try {
                    if ($id <= 0 || !IPS_VariableExists($id) || IPS_GetVariable($id)['VariableType'] !== 0) {
                        throw new RuntimeException('Boolean-Bewegungsvariable auswaehlen.');
                    }
                    if ($archive <= 0) { throw new RuntimeException('Archiv auswaehlen: kein eindeutiges Archiv gefunden.'); }
                    if (!AC_GetLoggingStatus($archive, $id)) { throw new RuntimeException('Archivierung ist nicht aktiviert.'); }
                    $values = AC_GetLoggedValues($archive, $id, $start, $now, 10000);
                    foreach ($values as $v) { $history['entries'][] = ['time' => (int)$v['TimeStamp'], 'active' => (bool)$v['Value']]; }
                    $previous = AC_GetLoggedValues($archive, $id, 0, $start - 1, 1);
                    if ($previous) { $history['previous'] = (bool)$previous[0]['Value']; }
                    if (count($values) >= 10000) { $history['note'] = 'Abfragelimit erreicht: Verlauf moeglicherweise unvollstaendig.'; }
                    elseif (!$values && !$previous) { $history['note'] = 'Noch keine Archivdaten. Aufzeichnung beginnt mit Aktivierung.'; }
                } catch (Throwable $e) { $history['note'] = $e->getMessage(); }
                $cache['history'][(string)$id] = $history;
            }
            $this->SetBuffer('MotionCache', json_encode($cache, JSON_THROW_ON_ERROR));
        }
        $result = ['start' => $start, 'end' => $now, 'sensors' => []];
        foreach ($this->MotionSensors() as $sensor) {
            $id = $sensor['Variable'];
            $result['sensors'][] = ['name' => $sensor['Name'], 'current' => $this->Read($id)['raw'],
                'history' => $cache['history'][(string)$id] ?? ['entries' => [], 'previous' => null, 'note' => 'Noch keine Daten.']];
        }
        return $result;
    }

    private function State(): array
    {
        $state = ['View' => $this->ReadPropertyInteger('View')];
        foreach (array_keys(self::SOURCES) as $name) { $state[$name] = $this->Read($this->ConfigInteger($name)); }
        $state['DoorReason'] = $this->DoorReason();
        $state['Motion'] = $this->HasMotionView() ? $this->MotionState() : null;
        $state['CinemaOptions'] = $this->CinemaSourceOptions();
        $state['VolumeControl'] = null;
        $state['VolumeReason'] = '';
        try { $state['VolumeControl'] = $this->CinemaVolumePlan(); }
        catch (Throwable $e) { $state['VolumeReason'] = $e->getMessage(); }
        $state['Rooms'] = [];
        foreach ($this->Rooms() as $room) {
            $state['Rooms'][] = ['name' => $room['Name'], 'value' => $this->Read($room['Variable'])['text']];
        }
        $script = $this->ConfigInteger('LightCommandScript');
        $scriptOK = $script > 0 && IPS_ScriptExists($script);
        $state['Controls'] = [
            'DoorPermission' => $this->CanSetDoorPermission(),
            'Door' => $this->DoorAvailable(),
            'Light' => $this->ConfigBoolean('LightEnabled')
                && $scriptOK,
            'Brightness' => $this->ConfigBoolean('LightEnabled')
                && $scriptOK,
            'Cinema' => $this->ConfigBoolean('CinemaEnabled')
                && $this->HasAction($this->ConfigInteger('CinemaControl'), 0)
        ];
        return $state;
    }
}
