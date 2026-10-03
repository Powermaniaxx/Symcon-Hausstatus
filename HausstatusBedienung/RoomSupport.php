<?php
declare(strict_types=1);

trait HausstatusRoomSupport
{
    private function DefaultRoomJSON(): string
    {
        $json = file_get_contents(__DIR__ . '/room_defaults.json');
        if ($json === false) { throw new RuntimeException('room_defaults.json fehlt.'); }
        json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return $json;
    }

    private function RoomEntries(): array
    {
        $list = json_decode($this->ConfigString('RoomEntries'), true);
        if (!is_array($list)) { return []; }
        return array_values(array_filter($list, static fn($row): bool => is_array($row)
            && isset($row['Room'], $row['Group'], $row['Name'], $row['Target'])
            && is_string($row['Room']) && trim($row['Room']) !== '' && is_string($row['Group'])
            && is_string($row['Name']) && is_int($row['Target']) && $row['Target'] > 0));
    }

    private function SelectedRoomEntries(): array
    {
        $view = $this->ReadPropertyInteger('View');
        if (!in_array($view, [7, 12], true)) { return []; }
        $filter = $view === 7 ? 'PV-Anlage' : $this->ReadPropertyString('RoomFilter');
        $result = []; $seen = [];
        foreach ($this->RoomEntries() as $entry) {
            if ($filter !== '' && $entry['Room'] !== $filter) { continue; }
            $this->ExpandRoomTarget($entry, $entry['Target'], '', $result, $seen, 0);
        }
        return $result;
    }

    private function ExpandRoomTarget(array $entry, int $target, string $prefix, array &$result, array &$seen, int $depth): void
    {
        if ($depth > 8) { return; }
        $key = $entry['Room'] . ':' . $target;
        if (isset($seen[$key])) { return; }
        $seen[$key] = true;
        $exists = IPS_ObjectExists($target);
        $type = $exists ? (int)IPS_GetObject($target)['ObjectType'] : -1;
        if ($type === 1 || ($type === 0 && $depth > 0)) {
            $before = count($result);
            $children = IPS_GetChildrenIDs($target);
            usort($children, static fn(int $a, int $b): int => IPS_GetObject($a)['ObjectPosition'] <=> IPS_GetObject($b)['ObjectPosition']);
            foreach ($children as $child) {
                $object = IPS_GetObject($child);
                if ($object['ObjectIsHidden'] || $object['ObjectIsDisabled']
                    || !in_array($object['ObjectType'], [0, 1, 2], true)) { continue; }
                if ($object['ObjectType'] === 2 && $this->RoomWebContent($child)) { continue; }
                $childPrefix = $prefix === '' ? $entry['Name'] : $prefix;
                if ($object['ObjectType'] === 1 || $object['ObjectType'] === 0) { $childPrefix .= ' · ' . IPS_GetName($child); }
                $this->ExpandRoomTarget($entry, $child, $childPrefix, $result, $seen, $depth + 1);
            }
            if (count($result) > $before) { return; }
        }
        $name = $prefix === '' ? $entry['Name'] : $prefix . ' · ' . $this->RoomVariableName($target);
        $result[] = ['room' => $entry['Room'], 'group' => $entry['Group'], 'name' => $name,
            'id' => $target, 'type' => $type, 'operate' => ($entry['Operate'] ?? false) === true];
    }

    private function RoomVariableName(int $id): string
    {
        if (!IPS_ObjectExists($id)) { return 'Quelle fehlt'; }
        $name = IPS_GetName($id);
        $name = ['ACTUAL_TEMPERATURE' => 'Temperatur', 'SET_POINT_TEMPERATURE' => 'Solltemperatur',
            'HUMIDITY' => 'Luftfeuchte', 'MOTION' => 'Bewegung', 'PRESENCE_DETECTION_STATE' => 'Präsenz',
            'ILLUMINATION' => 'Helligkeit', 'LEVEL' => 'Position', 'STATE' => 'Status', 'POWER' => 'Ein/Aus'][$name] ?? $name;
        return $this->RoomLabel($name);
    }

    private function RoomWebContent(int $id): bool
    {
        $v = IPS_GetVariable($id); $p = $this->VariablePresentation($id);
        return str_contains(strtolower($v['VariableProfile'] . ' ' . $v['VariableCustomProfile']), 'htmlbox')
            || ($p['PRESENTATION'] ?? '') === '{9DE1D610-5106-97FB-714D-1AADEDF8377A}';
    }

    private function RoomLabel(string $label): string
    {
        $words = ['Praesenz' => 'Präsenz', 'Tuer' => 'Tür', 'Tuerkontakt' => 'Türkontakt',
            'Lautstaerke' => 'Lautstärke', 'Uebergang' => 'Übergang', 'Oeffnen' => 'Öffnen',
            'oeffnen' => 'öffnen', 'Schliessen' => 'Schließen', 'schliessen' => 'schließen'];
        return preg_replace_callback('/\b(' . implode('|', array_map(static fn(string $word): string => preg_quote($word, '/'), array_keys($words))) . ')\b/u',
            static fn(array $match): string => $words[$match[1]], $label) ?? $label;
    }

    private function ProtectedDoorTarget(int $id): bool
    {
        $protected = [$this->ConfigInteger('DoorControl'), $this->ConfigInteger('DoorPermission'),
            $this->ConfigInteger('Lock'), 30053, 33983, 31820];
        $control = $this->ConfigInteger('DoorControl');
        if (IPS_VariableExists($control)) {
            $v = IPS_GetVariable($control);
            if ((int)$v['VariableCustomAction'] > 1) { $protected[] = (int)$v['VariableCustomAction']; }
        }
        $lock = $this->ConfigInteger('Lock');
        if (IPS_VariableExists($lock) && IPS_InstanceExists(IPS_GetParent($lock))) { $protected[] = IPS_GetParent($lock); }
        for ($depth = 0; $id > 0 && $depth < 16; $depth++) {
            if (in_array($id, $protected, true)) { return true; }
            if (!IPS_ObjectExists($id)) { break; }
            if ((IPS_GetObject($id)['ObjectIdent'] ?? '') === 'LOCK_TARGET_LEVEL') { return true; }
            $id = IPS_GetParent($id);
        }
        return false;
    }

    private function RoomRoute(int $id): string
    {
        $routes = ['LightState' => 'Light', 'Brightness' => 'Brightness', 'CinemaVolume' => 'CinemaVolume',
            'CinemaSource' => 'CinemaSource', 'CinemaControl' => 'Cinema', 'CinemaState' => 'Cinema',
            'AwningPosition' => 'AwningPosition', 'AwningAuto' => 'AwningAuto',
            'RoofPosition' => 'RoofPosition', 'RoofAuto' => 'RoofAuto', 'RoofNight' => 'RoofNight'];
        foreach ($routes as $source => $command) {
            if ($id > 0 && $this->ConfigInteger($source) === $id) { return $command; }
        }
        // The other known AVR power variable must respect the Cinema enable flag too.
        return [10950 => 'Cinema', 45754 => 'Cinema', 45376 => 'CinemaVolume', 16889 => 'CinemaSource'][$id] ?? '';
    }

    private function RoomOptions(int $id, int $type): array
    {
        $presentation = $this->VariablePresentation($id); $kind = $presentation['PRESENTATION'] ?? '';
        $options = [];
        if ($kind === '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}') {
            $options = $presentation['OPTIONS'] ?? [];
            if (is_string($options)) { $options = json_decode($options, true); }
        } elseif (in_array($kind, ['', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}'], true)) {
            $v = IPS_GetVariable($id);
            $name = $presentation['PROFILE'] ?? ($v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile']);
            if ($name !== '' && IPS_VariableProfileExists($name)) { $options = IPS_GetVariableProfile($name)['Associations']; }
        }
        $result = [];
        foreach (is_array($options) ? $options : [] as $option) {
            if (!is_array($option) || !array_key_exists('Value', $option)) { continue; }
            $value = $option['Value'];
            if ($type === 0 && !is_bool($value)) { continue; }
            if ($type === 1) {
                if (!is_numeric($value) || !is_finite((float)$value) || (float)$value !== (float)(int)$value) { continue; }
                $value = (int)$value;
            } elseif ($type === 2) {
                if (!is_numeric($value) || !is_finite((float)$value)) { continue; }
                $value = (float)$value;
            } elseif ($type === 3 && !is_string($value)) { continue; }
            $name = trim(strip_tags((string)($option['Caption'] ?? $option['Name'] ?? '')));
            if ($name !== '') { $result[json_encode($value)] = ['value' => $value, 'name' => $this->RoomLabel($name)]; }
        }
        return array_values($result);
    }

    private function RoomControl(array $entry): ?array
    {
        $id = $entry['id'];
        if (!$entry['operate'] || !$this->ConfigBoolean('RoomControlsEnabled')) { return null; }
        if ($this->ProtectedDoorTarget($id)) { throw new RuntimeException('Türbedienung erfolgt im Haustürbereich mit Freigabe und Sicherheitsfrage.'); }
        if ($entry['type'] !== 2) {
            return IPS_ObjectExists($id) ? ['kind' => 'native', 'id' => $id] : null;
        }
        if ($this->RoomWebContent($id)) { return ['kind' => 'native', 'id' => $id]; }
        $route = $this->RoomRoute($id);
        $v = IPS_GetVariable($id); $type = (int)$v['VariableType'];
        if (in_array($route, ['Light', 'Brightness'], true)) {
            $script = $this->ConfigInteger('LightCommandScript');
            if (!$this->ConfigBoolean('LightEnabled') || !IPS_ScriptExists($script)) { throw new RuntimeException('Lichtbedienung und Bedienskript in 52627 einstellen.'); }
            if (($route === 'Light' && $type !== 0) || ($route === 'Brightness' && $type !== 1)) {
                throw new RuntimeException('Die Lichtquelle hat nicht den passenden Variablentyp.');
            }
            if ($route === 'Brightness') { return ['kind' => 'slider', 'min' => 1, 'max' => 100, 'step' => 1, 'type' => 1, 'percentage' => false, 'suffix' => ' %', 'digits' => 0, 'route' => $route]; }
        }
        if (str_starts_with($route, 'Cinema') && !$this->ConfigBoolean('CinemaEnabled')) { throw new RuntimeException('Cinema-Bedienung ist deaktiviert.'); }
        if ($route === 'Cinema') { $this->ValidateAction($this->ConfigInteger('CinemaControl'), 0); }
        if (in_array($route, ['AwningPosition', 'AwningAuto', 'RoofPosition', 'RoofAuto', 'RoofNight'], true)
            && !$this->ConfigBoolean('OutdoorEnabled')) { throw new RuntimeException('Markise/Dachfenster-Bedienung ist deaktiviert.'); }
        if ($route !== 'Light' && !$this->HasAction($id, $type)) { return null; }
        $options = $this->RoomOptions($id, $type);
        if ($options) { return ['kind' => 'enum', 'options' => $options, 'route' => $route]; }
        if ($type === 0) {
            $options = [];
            foreach ([true, false] as $value) {
                try { $caption = trim(strip_tags(GetValueFormattedEx($id, $value))); }
                catch (Throwable $e) { $caption = $value ? 'Ein' : 'Aus'; }
                $options[] = ['value' => $value, 'name' => $caption !== '' ? $this->RoomLabel($caption) : ($value ? 'Ein' : 'Aus')];
            }
            return ['kind' => 'bool', 'options' => $options, 'route' => $route];
        }
        $p = $this->VariablePresentation($id); $kind = $p['PRESENTATION'] ?? '';
        $profile = $p['PROFILE'] ?? ($v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile']);
        if ($type === 1 && (($kind === '{05CC3CC2-A0B2-5837-A4A7-A07EA0B9DDFB}'
            && (int)($p['ENCODING'] ?? -1) === 0 && (int)($p['COLOR_CURVE'] ?? 0) === 0)
            || (in_array($kind, ['', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}'], true) && $profile === '~HexColor'))) {
            return ['kind' => 'color', 'route' => $route];
        }
        if (in_array($type, [1, 2], true)) {
            try { return ['kind' => 'slider', 'route' => $route] + $this->SliderPlan($id); }
            catch (Throwable $e) { /* Preserve the actual native control for other presentations. */ }
        }
        return ['kind' => 'native', 'id' => $id];
    }

    private function RoomSections(): array
    {
        $sections = [];
        foreach ($this->SelectedRoomEntries() as $entry) {
            $id = $entry['id']; $control = null; $note = '';
            $value = $entry['type'] === 2 ? ($this->RoomWebContent($id) ? ['raw' => null, 'text' => 'HTML-Ansicht'] : $this->Read($id))
                : ['raw' => null, 'text' => IPS_ObjectExists($id) ? 'Native Bedienung' : 'Quelle fehlt'];
            if ($entry['type'] === -1) { $note = 'Quelle ' . $id . ' ist nicht vorhanden.'; }
            try { $control = $this->RoomControl($entry); }
            catch (Throwable $e) { $note = $e->getMessage(); }
            $sections[$entry['room']][$entry['group']][] = ['id' => $id, 'name' => $entry['name'],
                'value' => $value, 'control' => $control, 'note' => $note];
        }
        $result = [];
        foreach ($sections as $room => $groups) {
            $list = [];
            foreach ($groups as $name => $items) { $list[] = ['name' => $name, 'items' => $items]; }
            $result[] = ['name' => $room, 'groups' => $list];
        }
        return $result;
    }

    private function SetRoomValue(mixed $payload): void
    {
        if (!is_array($payload) || !isset($payload['id']) || !is_int($payload['id']) || !array_key_exists('value', $payload)) {
            throw new RuntimeException('Ungültiger Raumbefehl.');
        }
        $entry = null;
        foreach ($this->SelectedRoomEntries() as $candidate) {
            if ($candidate['id'] === $payload['id']) { $entry = $candidate; break; }
        }
        if ($entry === null) { throw new RuntimeException('Die Variable gehört nicht zu dieser Raumansicht.'); }
        $control = $this->RoomControl($entry);
        if ($control === null || $control['kind'] === 'native') { throw new RuntimeException('Diese Quelle ist keine direkte HTML-Bedienung.'); }
        $value = $payload['value']; $id = $entry['id']; $type = IPS_GetVariable($id)['VariableType'];
        if ($control['kind'] === 'bool') {
            if (!is_bool($value)) { throw new RuntimeException('Der Schalter erwartet Boolean.'); }
        } elseif ($control['kind'] === 'enum') {
            if ($type === 2 && (is_int($value) || is_float($value))) { $value = (float)$value; }
            if (!in_array($value, array_column($control['options'], 'value'), true)) { throw new RuntimeException('Auswahlwert ist nicht hinterlegt.'); }
        } elseif ($control['kind'] === 'color') {
            if (!is_int($value) || $value < 0 || $value > 16777215) { throw new RuntimeException('RGB-Farbe ist ungültig.'); }
        } elseif ($control['kind'] === 'slider') {
            if (($control['route'] ?? '') !== 'Brightness') { $this->SetSlider($id, $value, $control); return; }
            if (!is_int($value) || $value < 1 || $value > 100) { throw new RuntimeException('Helligkeit muss von 1 bis 100 sein.'); }
        }
        $route = $control['route'] ?? '';
        if (in_array($route, ['Light', 'Brightness'], true)) {
            if (!IPS_RunScriptEx($this->ConfigInteger('LightCommandScript'), ['COMMAND' => $route, 'VALUE' => $value, 'SOURCE' => 'HausstatusBedienung'])) {
                throw new RuntimeException('Licht-Bedienskript konnte nicht gestartet werden.');
            }
            return;
        }
        if ($route === 'Cinema') {
            $id = $this->ConfigInteger('CinemaControl'); $this->ValidateAction($id, 0);
        }
        if (!RequestAction($id, $value)) { throw new RuntimeException('Die vorhandene Variablenaktion ist fehlgeschlagen.'); }
    }
}
