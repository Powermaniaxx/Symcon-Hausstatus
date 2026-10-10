<?php
declare(strict_types=1);

trait HausstatusRoomSupport
{
    private function PVInverterSources(): array
    {
        // Reuse the user's configured PV devices; never ship installation-specific IDs.
        $devices = [];
        foreach ($this->ExpandedRoomEntries($this->ConfigString('PVRoom')) as $entry) {
            if ($entry['type'] !== 2) { continue; }
            $label = trim($this->RoomVariableName($entry['id']));
            $key = $entry['deviceKey'];
            if (preg_match('/^(?:Leistung[ _-]*AC|AC[ _-]*Leistung|AC[ _-]*Power)$/iu', $label)) {
                $devices[$key]['power'] = $entry['id'];
                $devices[$key]['name'] = $entry['deviceName'];
            } elseif (preg_match('/^(?:Wechselrichter produziert|Produziert|Producing)$/iu', $label)) {
                $devices[$key]['producing'] = $entry['id'];
            }
        }
        return array_values(array_filter($devices, static fn(array $device): bool => isset($device['power'], $device['producing'])));
    }

    private function PVInverters(): array
    {
        if (!in_array($this->CurrentView(), [0, 7, 15], true)) { return []; }
        $result = [];
        foreach ($this->PVInverterSources() as $device) {
            $production = $this->Read($device['producing']);
            $result[] = ['name' => $device['name'], 'power' => $this->Read($device['power']),
                'producing' => is_bool($production['raw']) ? $production['raw'] : null];
        }
        return $result;
    }

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
        $view = $this->CurrentView();
        if ($this->HasTemperatureView()) {
            $profile = $this->HeatingProfileEntry();
            $entries = $view === 9 ? $this->TemperatureEntries() : [];
            if ($profile !== null) { $entries[] = $profile; }
            return $entries;
        }
        if (!in_array($view, [7, 12], true)) { return []; }
        $filter = $view === 7 ? $this->ConfigString('PVRoom') : $this->CurrentRoom();
        $result = $this->ExpandedRoomEntries($filter);
        if ($view === 12) {
            $extra = $this->TemperatureEntries();
            if ($filter === '' || $filter === $this->ConfigString('DiningRoom')) { $extra = array_merge($extra, $this->DiningEntries()); }
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
        if ($this->IsCinemaVariable($target)) { $deviceName = $this->DisplayText('CinemaState'); }
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
            $this->ConfigInteger('Lock')];
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
        // Power aliases of the configured receiver keep the same enable flag and action route.
        if ($this->IsCinemaVariable($id) && IPS_GetVariable($id)['VariableType'] === 0
            && in_array(strtolower(IPS_GetName($id)), ['power', 'mainzone power'], true)) { return 'Cinema'; }
        return '';
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
            throw new RuntimeException('Bedienung der zusätzlichen Lampe ist deaktiviert.');
        }
        if (in_array($route, ['Light', 'Brightness'], true)) {
            $script = $this->ConfigInteger('LightCommandScript');
            if (!$this->ConfigBoolean('LightEnabled') || ($script > 0 && !IPS_ScriptExists($script))) { throw new RuntimeException('Lichtbedienung und gegebenenfalls Bedienskript in der zentralen Konfiguration einstellen.'); }
            if ($script === 0) { $this->ValidateAction($id, $type); }
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
        if (!in_array($this->CurrentView(), [7, 12], true)) { return []; }
        $sections = [];
        $climateIDs = [];
        foreach ($this->TemperatureSources() as $row) {
            $climateIDs[] = $row['actualID']; foreach ($row['setpoints'] as $entry) { $climateIDs[] = $entry['id']; }
        }
        $entries = $this->SelectedRoomEntries();
        $receivers = [];
        foreach ($entries as $entry) {
            if ($this->IsCinemaVariable($entry['id'])) { $receivers[$entry['room']] = $entry; }
        }
        $statusID = $this->ConfigInteger('HeosStatus');
        if ($statusID > 0 && IPS_VariableExists($statusID)) {
            foreach ($receivers as $room => $receiver) {
                if ($this->IsHeosSelection($statusID) || !array_filter($entries, static fn(array $entry): bool => $entry['id'] === $statusID && $entry['room'] === $room)) {
                    $entries[] = ['room' => $room, 'group' => $receiver['group'], 'name' => 'HEOS aktuell',
                        'id' => $statusID, 'type' => 2, 'operate' => false, 'heosStatusOnly' => true,
                        'deviceKey' => $receiver['deviceKey'], 'deviceName' => $this->DisplayText('CinemaState')];
                }
            }
        }
        foreach ($entries as $entry) {
            if (($this->IsHeosSelection($entry['id']) || ($statusID > 0 && $entry['id'] === $statusID)) && isset($receivers[$entry['room']])) {
                $receiver = $receivers[$entry['room']];
                $entry['deviceKey'] = $receiver['deviceKey']; $entry['deviceName'] = $this->DisplayText('CinemaState');
                $entry['group'] = $receiver['group'];
            }
            if (in_array($entry['id'], $climateIDs, true)) { continue; }
            $item = $this->RoomItem($entry);
            if ($statusID > 0 && $entry['id'] === $statusID && (($entry['heosStatusOnly'] ?? false) || !$this->IsHeosSelection($statusID))) {
                $item['label'] = $item['name'] = 'HEOS aktuell'; $item['control'] = null;
                $item['role'] = 'heosStatus'; $item['primary'] = true;
            }
            $sections[$entry['room']][$entry['group']][] = $item;
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
        if (($control['kind'] ?? '') === 'enum' && $id === $this->ConfigInteger('CinemaSource')) { $primary = true; }
        if ($entry['type'] !== 2) { $primary = false; }
        $role = $this->RoomItemRole($entry, $control);
        if (in_array($this->CurrentView(), [9, 12], true)) {
            $primary = in_array($role, ['switch', 'brightness', 'setpoint', 'source', 'volume', 'temperature',
                'humidity', 'motion', 'presence', 'illuminance', 'position', 'power', 'control', 'heos'], true);
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
        if (in_array($id, [$this->ConfigInteger('CinemaState'), $this->ConfigInteger('CinemaControl')], true)) { return 'switch'; }
        if ($this->IsHeosSelection($id)) { return 'heos'; }
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
            $this->SetLight($route, $value); return;
        }
        if ($route === 'Cinema') {
            $id = $this->ConfigInteger('CinemaControl'); $this->ValidateAction($id, 0);
        }
        if (!RequestAction($id, $value)) { throw new RuntimeException('Die vorhandene Variablenaktion ist fehlgeschlagen.'); }
    }
    private function IsHeosSelection(int $id): bool
    {
        return $id > 0 && IPS_VariableExists($id) && ($id === $this->ConfigInteger('HeosSelection')
            || preg_match('/heos.*(?:radio|playlist|auswahl)|(?:radio|playlist).*heos/iu', IPS_GetName($id)) === 1);
    }

    private function HeosEntry(): ?array
    {
        $id = $this->ConfigInteger('HeosSelection');
        if ($id > 0) {
            if (!IPS_VariableExists($id)) { throw new RuntimeException('Die gewählte HEOS-Auswahlvariable fehlt.'); }
            return ['id' => $id, 'type' => IPS_ObjectExists($id) ? (int)IPS_GetObject($id)['ObjectType'] : -1,
                'operate' => true, 'name' => 'HEOS Radio / Playlist', 'room' => '', 'group' => 'Medien'];
        }
        $matches = [];
        foreach ($this->ExpandedRoomEntries('') as $entry) {
            if ($this->IsHeosSelection($entry['id'])) { $matches[$entry['id']] = $entry; }
        }
        if (count($matches) > 1) { throw new RuntimeException('Mehrere HEOS-Auswahlen vorhanden. Die gewünschte Variable unter Mediengerät auswählen.'); }
        return $matches === [] ? null : array_values($matches)[0];
    }

    private function HeosItem(): ?array
    {
        try {
            $entry = $this->HeosEntry();
            if ($entry === null) { return null; }
            $item = $this->RoomItem($entry);
            if (!$this->ConfigBoolean('CinemaEnabled')) { $item['control'] = null; $item['note'] = 'Mediengerät-Bedienung ist deaktiviert.'; }
            return $item;
        } catch (Throwable $e) { return ['name' => 'HEOS Radio / Playlist', 'label' => 'HEOS Radio / Playlist', 'value' => ['raw' => null, 'text' => 'Nicht verfügbar'], 'control' => null, 'note' => $e->getMessage()]; }
    }

    // 0.34: Radio und NAS besitzen je eine eigene, dynamische Auswahlliste.
    // Optionennamen und Werte werden aus der Variablen-Praesentation gelesen.
    // Ohne eigene Quelle bleibt die bisherige HEOS-Auswahl als Rueckfall erhalten.
    private function HeosSplitItem(string $source): ?array
    {
        if (!in_array($source, ['HeosRadio', 'HeosNAS'], true)) { return null; }
        $label = $source === 'HeosRadio' ? 'Radio' : 'NAS-Playlisten';
        $id = $this->ConfigInteger($source);

        if ($id > 0) {
            try {
                if (!IPS_VariableExists($id)) {
                    throw new RuntimeException('Die konfigurierte Auswahlvariable fehlt.');
                }
                $entry = ['id' => $id, 'type' => 2, 'operate' => true,
                    'name' => $label, 'room' => '', 'group' => 'Medien'];
                $item = $this->RoomItem($entry);
                $item['name'] = $item['label'] = $label;
                $item['command'] = $source;
                if (!$this->ConfigBoolean('CinemaEnabled')) {
                    $item['control'] = null;
                    $item['note'] = 'Mediengeraet-Bedienung ist deaktiviert.';
                } elseif (($item['control']['kind'] ?? '') !== 'enum') {
                    $item['control'] = null;
                    $item['note'] = 'Auswahlvariable benoetigt eine Bedienaktion und Auswahloptionen.';
                }
                return $item;
            } catch (Throwable $e) {
                return ['name' => $label, 'label' => $label,
                    'value' => ['raw' => null, 'text' => 'Nicht verfuegbar'],
                    'control' => null, 'note' => $e->getMessage(),
                    'command' => $source];
            }
        }

        $item = $this->HeosItem();
        if ($item === null) { return null; }
        $item['name'] = $item['label'] = $label;
        $item['command'] = 'HeosSelection';
        if (($item['control']['kind'] ?? '') !== 'enum') { return $item; }

        // Kompatibilitaet mit der bisherigen Belegung:
        // 0 = Aus, 1-3 = Radio, ab 4 = NAS.
        // Neue Radiosender ohne feste Begrenzung: separate
        // Radio-Auswahlvariable in den Einstellungen hinterlegen.
        $item['control']['options'] = array_values(array_filter(
            $item['control']['options'],
            static function (array $option) use ($source): bool {
                $v = $option['value'] ?? null;
                if (!is_int($v)) { return false; }
                if ($v === 0) { return true; }
                return $source === 'HeosRadio' ? $v >= 1 && $v <= 3 : $v >= 4;
            }
        ));
        if ($item['control']['options'] === []) {
            $item['control'] = null;
            $item['note'] = 'Keine Eintraege in der bisherigen HEOS-Auswahl vorhanden.';
        }
        return $item;
    }

    private function SetHeosSplitSelection(string $source, mixed $value): void
    {
        if (!in_array($source, ['HeosRadio', 'HeosNAS'], true)) {
            throw new InvalidArgumentException('Unbekannte HEOS-Liste.');
        }
        if (!$this->ConfigBoolean('CinemaEnabled')) {
            throw new RuntimeException('Mediengeraet-Bedienung ist deaktiviert.');
        }
        $id = $this->ConfigInteger($source);
        if ($id <= 0 || !IPS_VariableExists($id)) {
            throw new RuntimeException('Auswahlvariable fuer ' . $source . ' fehlt.');
        }
        $entry = ['id' => $id, 'type' => 2, 'operate' => true,
            'name' => $source, 'room' => '', 'group' => 'Medien'];
        $control = $this->RoomControl($entry);
        if (($control['kind'] ?? '') !== 'enum') {
            throw new RuntimeException('HEOS benoetigt eine schaltbare Auswahlliste.');
        }
        $type = IPS_GetVariable($id)['VariableType'];
        if ($type === 2 && (is_int($value) || is_float($value))) {
            $value = (float)$value;
        }
        if (!in_array($value, array_column($control['options'], 'value'), true)) {
            throw new InvalidArgumentException('HEOS-Auswahlwert oder Datentyp ist ungueltig.');
        }
        if (!RequestAction($id, $value)) {
            throw new RuntimeException('Die HEOS-Variablenaktion ist fehlgeschlagen.');
        }
    }

    private function SetHeosSelection(mixed $value): void
    {
        if (!$this->ConfigBoolean('CinemaEnabled')) { throw new RuntimeException('Mediengerät-Bedienung ist deaktiviert.'); }
        $entry = $this->HeosEntry();
        if ($entry === null) { throw new RuntimeException('HEOS-Auswahl ist nicht eingerichtet.'); }
        $control = $this->RoomControl($entry);
        if (($control['kind'] ?? '') !== 'enum') { throw new RuntimeException('HEOS benötigt eine schaltbare Auswahlvariable mit hinterlegten Optionen.'); }
        $type = IPS_GetVariable($entry['id'])['VariableType'];
        if ($type === 2 && (is_int($value) || is_float($value))) { $value = (float)$value; }
        if (!in_array($value, array_column($control['options'], 'value'), true)) { throw new RuntimeException('HEOS-Auswahlwert oder Datentyp ist ungültig.'); }
        if (!RequestAction($entry['id'], $value)) { throw new RuntimeException('Die vorhandene HEOS-Variablenaktion ist fehlgeschlagen.'); }
    }

    public function CheckCommands(): string
    {
        $version = json_decode((string)file_get_contents(__DIR__ . '/../library.json'), true)['version'] ?? '?';
        $lines = ['Hausstatus ' . $version . ' | Instanz ' . $this->InstanceID,
            'Letzte empfangene Aktion: ' . ($this->GetBuffer('LastCommandReceipt') ?: 'Noch keine Aktion angekommen.'),
            'Letzter Bedienfehler: ' . ($this->GetBuffer('LastCommandError') ?: 'Kein Fehler gespeichert.')];
        try {
            $entry = $this->HeosEntry();
            if ($entry === null) { $lines[] = 'HEOS: Keine eindeutige Quelle eingerichtet.'; }
            else {
                $id = $entry['id']; $v = IPS_GetVariable($id); $control = $this->RoomControl($entry);
                $lines[] = 'HEOS-Ziel: ' . $id . ' | Variablentyp: ' . $v['VariableType']
                    . ' | Aktion: ' . ($v['VariableCustomAction'] > 1 ? $v['VariableCustomAction'] : $v['VariableAction'])
                    . ' | Darstellung: ' . ($control['kind'] ?? 'nur Anzeige');
                $types = array_unique(array_map(static fn(array $option): string => get_debug_type($option['value']), $control['options'] ?? []));
                $lines[] = 'Option-Datentypen: ' . implode(', ', $types);
            }
        } catch (Throwable $e) { $lines[] = 'HEOS-Prüfung: ' . get_class($e) . ': ' . $e->getMessage(); }
        $lines[] = 'Keine Gerätebefehle gesendet.';
        return implode(PHP_EOL, $lines);
    }

}
