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
        if ($view === 9) { return $this->TemperatureEntries(); }
        if (!in_array($view, [7, 12], true)) { return []; }
        $filter = $view === 7 ? 'PV-Anlage' : $this->ReadPropertyString('RoomFilter');
        $result = $this->ExpandedRoomEntries($filter);
        if ($view === 12) {
            $extra = $this->TemperatureEntries();
            if ($filter === '' || $filter === 'Wohnzimmer') { $extra = array_merge($extra, $this->DiningEntries()); }
            foreach ($extra as $entry) {
                if (!array_filter($result, static fn(array $row): bool => $row['room'] === $entry['room'] && $row['id'] === $entry['id'])) { $result[] = $entry; }
            }
        }
        return $result;
    }

    private function ExpandedRoomEntries(string $filter): array
    {
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
        $parent = $exists ? IPS_GetParent($target) : 0;
        $device = $type === 1 ? $target : (IPS_InstanceExists($parent) ? $parent : $target);
        $parts = explode(' · ', $prefix);
        $deviceName = $prefix !== '' ? $this->RoomLabel((string)end($parts)) : ($device !== $target ? $this->RoomLabel(IPS_GetName($device)) : $entry['Name']);
        if (in_array($target, [10950, 45754, 45376, 16889, 38002], true)) { $deviceName = 'Cinema 40'; }
        $result[] = ['room' => $entry['Room'], 'group' => $entry['Group'], 'name' => $name,
            'id' => $target, 'type' => $type, 'operate' => ($entry['Operate'] ?? false) === true,
            'deviceKey' => 'device:' . $device, 'deviceName' => $deviceName];
    }

    private function RoomVariableName(int $id): string
    {
        if (!IPS_ObjectExists($id)) { return 'Quelle fehlt'; }
        $name = IPS_GetName($id);
        $name = ['ACTUAL_TEMPERATURE' => 'Temperatur', 'SET_POINT_TEMPERATURE' => 'Solltemperatur',
            'HUMIDITY' => 'Luftfeuchte', 'MOTION' => 'Bewegung', 'PRESENCE_DETECTION_STATE' => 'Präsenz',
            'ILLUMINATION' => 'Umgebungshelligkeit', 'LEVEL' => 'Position', 'STATE' => 'Status', 'POWER' => 'Ein/Aus',
            'CURRENT_ILLUMINATION' => 'Weiterer Helligkeitswert', 'ILLUMINATION_STATUS' => 'Helligkeitssensor – Status',
            'CURRENT_ILLUMINATION_STATUS' => 'Weiterer Helligkeitssensor – Status', 'MOTION_DETECTION_ACTIVE' => 'Bewegungserkennung aktiv',
            'PRESENCE_DETECTION_ACTIVE' => 'Präsenzerkennung aktiv', 'Master Volume' => 'Lautstärke',
            'MainZone Power' => 'Ein/Aus', 'Input Source' => 'Quelle', 'Main Mute' => 'Stumm',
            'Surround Mode' => 'Klangmodus', 'Surround Mode Display' => 'Aktueller Klangmodus'][$name] ?? $name;
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
        try {
            $dining = $this->DiningSources();
            if ($id === $dining['state']) { return 'Dining'; }
            if ($id > 0 && $id === $dining['brightness']) { return 'DiningBrightness'; }
        } catch (Throwable $e) { /* Dining is optional outside its own card. */ }
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
        if (($entry['temperature'] ?? false) && !in_array($type, [1, 2], true)) { throw new RuntimeException('Solltemperatur benötigt eine Integer- oder Float-Variable.'); }
        if (in_array($route, ['Dining', 'DiningBrightness'], true) && !$this->ConfigBoolean('DiningEnabled')) {
            throw new RuntimeException('Esstisch-Bedienung ist deaktiviert.');
        }
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
        if (!in_array($this->ReadPropertyInteger('View'), [7, 12], true)) { return []; }
        $sections = [];
        $climateIDs = [];
        foreach ($this->TemperatureSources() as $row) {
            $climateIDs[] = $row['actualID']; foreach ($row['setpoints'] as $entry) { $climateIDs[] = $entry['id']; }
        }
        foreach ($this->SelectedRoomEntries() as $entry) {
            if (in_array($entry['id'], $climateIDs, true)) { continue; }
            $sections[$entry['room']][$entry['group']][] = $this->RoomItem($entry);
        }
        $result = [];
        foreach ($sections as $room => $groups) {
            $list = [];
            foreach ($groups as $name => $items) { $list[] = ['name' => $name, 'items' => $items]; }
            $result[] = ['name' => $room, 'groups' => $list];
        }
        return $result;
    }

    private function RoomItem(array $entry): array
    {
        $id = $entry['id']; $control = null; $note = '';
        $value = $entry['type'] === 2 ? ($this->RoomWebContent($id) ? ['raw' => null, 'text' => 'HTML-Ansicht'] : $this->Read($id))
            : ['raw' => null, 'text' => IPS_ObjectExists($id) ? 'Native Bedienung' : 'Quelle fehlt'];
        if ($entry['type'] === -1) { $note = 'Quelle ' . $id . ' ist nicht vorhanden.'; }
        try { $control = $this->RoomControl($entry); } catch (Throwable $e) { $note = $e->getMessage(); }
        $label = explode(' · ', $entry['name']); $label = end($label);
        $primary = $control === null || in_array($control['kind'], ['bool', 'slider'], true);
        if (preg_match('/(?:übergang|farbtemperatur|^farbe$|batter|voltage|_status|detection_active|channel volume|minimum|maximum|kosten|energie|verbrauch|^min\b|^max\b)/iu', $label) === 1) { $primary = false; }
        if (($control['kind'] ?? '') === 'enum' && in_array($id, [16889], true)) { $primary = true; }
        if ($entry['type'] !== 2) { $primary = false; }
        $role = $this->RoomItemRole($entry, $control);
        if (in_array($this->ReadPropertyInteger('View'), [9, 12], true)) {
            $primary = in_array($role, ['switch', 'brightness', 'setpoint', 'source', 'volume', 'temperature',
                'humidity', 'motion', 'presence', 'illuminance', 'position', 'power', 'control'], true);
        }
        return ['id' => $id, 'name' => $entry['name'], 'label' => $label, 'value' => $value, 'control' => $control, 'note' => $note,
            'primary' => $primary, 'role' => $role, 'deviceKey' => $entry['deviceKey'] ?? ('source:' . $id), 'deviceName' => $entry['deviceName'] ?? $entry['name']];
    }

    private function RoomItemRole(array $entry, ?array $control): string
    {
        if ($entry['type'] !== 2 || !IPS_VariableExists($entry['id'])) { return 'other'; }
        $id = $entry['id']; $type = IPS_GetVariable($id)['VariableType'];
        $name = strtolower(IPS_GetName($id)); $ident = strtolower(IPS_GetObject($id)['ObjectIdent']);
        $text = $name . ' ' . $ident . ' ' . strtolower($entry['name']);
        $route = $control['route'] ?? '';
        $roles = ['Light' => 'switch', 'Dining' => 'switch', 'Cinema' => 'switch', 'Brightness' => 'brightness',
            'DiningBrightness' => 'brightness', 'CinemaVolume' => 'volume', 'CinemaSource' => 'source'];
        if (isset($roles[$route])) { return $roles[$route]; }
        if (in_array($id, [10950, 45754], true)) { return 'switch'; }
        if (($entry['temperature'] ?? false) || $this->IsRoomSetpoint($entry)) { return 'setpoint'; }
        if (preg_match('/(?:_status|detection_active|current_illumination|übergang|transition|farbtemperatur|colou?r.?temperature|channel volume|batter|voltage|minimum|maximum|\bmin\b|\bmax\b|kosten|energie|verbrauch)/iu', $text)) { return 'other'; }
        if (preg_match('/(?:presence_detection_state|präsenz|praesenz)/iu', $text)) { return 'presence'; }
        if (preg_match('/(?:\bmotion\b|bewegung)/iu', $text)) { return 'motion'; }
        if (preg_match('/(?:illumination|umgebungshelligkeit)/iu', $text)) { return 'illuminance'; }
        if (preg_match('/(?:brightness|helligkeit)/iu', $text)) { return 'brightness'; }
        if (preg_match('/(?:actual_temperature|temperatur|temperature)/iu', $text)) { return 'temperature'; }
        if (preg_match('/(?:humidity|luftfeuchte)/iu', $text)) { return 'humidity'; }
        if (preg_match('/(?:master volume|lautstärke)/iu', $text)) { return 'volume'; }
        if (preg_match('/(?:input source|\bquelle\b)/iu', $text)) { return 'source'; }
        if (preg_match('/(?:position|\blevel\b)/iu', $text)) { return 'position'; }
        if (preg_match('/(?:leistung|\bpower\b)/iu', $text) && in_array($type, [1, 2], true)) { return 'power'; }
        if ($type === 0 && (in_array($name, ['status', 'state', 'on', 'power'], true)
            || in_array($control['kind'] ?? '', ['bool', 'enum'], true))) { return 'switch'; }
        return ($control['kind'] ?? '') === 'slider' ? 'control' : 'other';
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
        if ($route === 'Dining') { $this->SetDining($route, $value); return; }
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
