<?php
declare(strict_types=1);

trait HausstatusComfortSupport
{
    private function DiningSources(): array
    {
        $instance = $this->ConfigInteger('DiningInstance');
        $state = $this->ConfigInteger('DiningState');
        $brightness = $this->ConfigInteger('DiningBrightness');
        if ($state === 0 && $instance === 0) {
            $light = $this->ConfigInteger('LightState');
            if (!IPS_VariableExists($light) || !IPS_InstanceExists(IPS_GetParent($light))) {
                throw new RuntimeException('Esstischquelle in 52627 auswählen.');
            }
            $root = IPS_GetParent(IPS_GetParent($light));
            if ($root === 0) { throw new RuntimeException('Esstischquelle in 52627 auswählen.'); }
            $candidates = []; $queue = [[$root, 0]]; $visited = 0;
            while ($queue) {
                [$parent, $depth] = array_shift($queue);
                foreach (IPS_GetChildrenIDs($parent) as $id) {
                    if (++$visited > 300) { throw new RuntimeException('Hue-Bereich zu groß für eine eindeutige Suche. Esstischquelle auswählen.'); }
                    $o = IPS_GetObject($id);
                    if ($o['ObjectIsDisabled']) { continue; }
                    if ($o['ObjectType'] === 0 && $depth < 4) { $queue[] = [$id, $depth + 1]; }
                    if ($o['ObjectType'] !== 1 || preg_match('/esstisch/iu', IPS_GetName($id)) !== 1) { continue; }
                    $moduleName = strtolower((string)IPS_GetInstance($id)['ModuleInfo']['ModuleName']);
                    if ($moduleName === 'hue light' || $moduleName === 'hue grouped light') { $candidates[] = $id; }
                }
            }
            if (count($candidates) !== 1) {
                throw new RuntimeException(count($candidates) > 1 ? 'Mehrere Esstischlampen gefunden. Quelle in 52627 auswählen.' : 'Esstischquelle in 52627 auswählen.');
            }
            $instance = $candidates[0];
        }
        if ($state === 0) {
            if (!IPS_InstanceExists($instance)) { throw new RuntimeException('Die gewählte Esstischinstanz fehlt.'); }
            $state = $this->DiningChild($instance, ['status', 'state', 'on'], [0]);
        }
        if ($state <= 0 || !IPS_VariableExists($state) || IPS_GetVariable($state)['VariableType'] !== 0
            || $state === $this->ConfigInteger('LightState') || $this->ProtectedDoorTarget($state)) {
            throw new RuntimeException('Esstisch benötigt eine eigene Boolean-Statusvariable.');
        }
        if ($brightness === 0) {
            $parent = IPS_GetParent($state);
            if (IPS_InstanceExists($parent)) { $brightness = $this->DiningChild($parent, ['helligkeit', 'brightness'], [1, 2]); }
        }
        if ($brightness > 0 && (!IPS_VariableExists($brightness) || !in_array(IPS_GetVariable($brightness)['VariableType'], [1, 2], true)
            || $this->ProtectedDoorTarget($brightness))) { throw new RuntimeException('Die Esstisch-Helligkeitsquelle ist ungültig.'); }
        return ['state' => $state, 'brightness' => $brightness];
    }

    private function DiningChild(int $parent, array $names, array $types): int
    {
        $matches = [];
        foreach (IPS_GetChildrenIDs($parent) as $id) {
            $o = IPS_GetObject($id);
            if ($o['ObjectType'] !== 2 || $o['ObjectIsDisabled'] || !in_array(IPS_GetVariable($id)['VariableType'], $types, true)) { continue; }
            if (in_array(strtolower($o['ObjectIdent']), $names, true) || in_array(strtolower(IPS_GetName($id)), $names, true)) { $matches[] = $id; }
        }
        return count($matches) === 1 ? $matches[0] : 0;
    }

    private function DiningState(): array
    {
        $result = ['state' => ['raw' => null, 'text' => 'Unbekannt'], 'brightness' => ['raw' => null, 'text' => 'Unbekannt'],
            'canSwitch' => false, 'control' => null, 'reason' => '', 'stateID' => 0];
        try {
            $ids = $this->DiningSources(); $result['stateID'] = $ids['state'];
            $result['state'] = $this->Read($ids['state']); $result['brightness'] = $this->Read($ids['brightness']);
            if (!$this->ConfigBoolean('DiningEnabled')) { throw new RuntimeException('Esstisch-Bedienung ist deaktiviert.'); }
            $result['canSwitch'] = $this->HasAction($ids['state'], 0);
            if (!$result['canSwitch']) { $result['reason'] = 'Die Esstischvariable hat keine aktive Bedienaktion.'; }
            if ($ids['brightness'] > 0) {
                try { $result['control'] = $this->SliderPlan($ids['brightness']); }
                catch (Throwable $e) { /* The independent switch remains usable without a brightness slider. */ }
            }
        } catch (Throwable $e) { $result['reason'] = $e->getMessage(); }
        return $result;
    }

    private function DiningEntries(): array
    {
        try { $ids = $this->DiningSources(); } catch (Throwable $e) { return []; }
        $entries = [];
        foreach (['state' => 'Esstisch · Status', 'brightness' => 'Esstisch · Helligkeit'] as $key => $name) {
            if ($ids[$key] > 0) { $entries[] = ['room' => 'Wohnzimmer', 'group' => 'Licht', 'name' => $name,
                'id' => $ids[$key], 'type' => 2, 'operate' => true, 'deviceKey' => 'device:' . IPS_GetParent($ids[$key]), 'deviceName' => 'Esstisch']; }
        }
        return $entries;
    }

    private function SetDining(string $command, mixed $value): void
    {
        if (!$this->ConfigBoolean('DiningEnabled')) { throw new RuntimeException('Esstisch-Bedienung ist deaktiviert.'); }
        $ids = $this->DiningSources();
        if ($command === 'DiningBrightness') {
            $this->SetSlider($ids['brightness'], $value, $this->SliderPlan($ids['brightness']));
        } else {
            if (!is_bool($value)) { throw new RuntimeException('Esstisch erwartet Ein/Aus.'); }
            $this->ValidateAction($ids['state'], 0);
            if (!RequestAction($ids['state'], $value)) { throw new RuntimeException('Esstischaktion fehlgeschlagen.'); }
        }
    }

    private function IsRoomSetpoint(array $entry): bool
    {
        if ($entry['type'] !== 2 || !IPS_VariableExists($entry['id'])) { return false; }
        if (!in_array(IPS_GetVariable($entry['id'])['VariableType'], [1, 2], true)) { return false; }
        $o = IPS_GetObject($entry['id']);
        $terms = [strtolower($o['ObjectIdent']), strtolower(IPS_GetName($entry['id'])), strtolower($entry['name'])];
        foreach ($terms as $name) {
            if (preg_match('/(?:^| · )(?:set_point_temperature|setpoint|solltemperatur|soll-temperatur|sollwert)$/', $name) === 1) { return true; }
        }
        return false;
    }

    private function TemperatureSources(): array
    {
        $view = $this->ReadPropertyInteger('View');
        if (!in_array($view, [9, 12], true)) { return []; }
        $filter = $view === 12 ? $this->ReadPropertyString('RoomFilter') : '';
        $expanded = $this->ExpandedRoomEntries($filter);
        $rows = [];
        foreach ($this->Rooms() as $room) {
            if ($filter !== '' && $room['Name'] !== $filter) { continue; }
            $setpoints = [];
            if ($room['Setpoint'] > 0) {
                $id = $room['Setpoint'];
                $setpoints[] = ['room' => $room['Name'], 'group' => 'Heizung', 'name' => 'Solltemperatur', 'id' => $id,
                    'type' => IPS_VariableExists($id) ? 2 : -1, 'operate' => true, 'temperature' => true];
            } else {
                foreach ($expanded as $entry) {
                    if ($entry['room'] === $room['Name'] && $this->IsRoomSetpoint($entry)) { $setpoints[] = $entry; }
                }
                // A thermostat may have been imported only through its measured-temperature variable.
                $actual = $room['Variable'];
                $parent = IPS_VariableExists($actual) ? IPS_GetParent($actual) : 0;
                if (!$setpoints && IPS_InstanceExists($parent)) {
                    foreach (IPS_GetChildrenIDs($parent) as $id) {
                        $o = IPS_GetObject($id);
                        if ($o['ObjectType'] !== 2 || $o['ObjectIsHidden'] || $o['ObjectIsDisabled']) { continue; }
                        $entry = ['room' => $room['Name'], 'group' => 'Heizung', 'name' => IPS_GetName($parent) . ' · ' . $this->RoomVariableName($id),
                            'id' => $id, 'type' => 2, 'operate' => true, 'temperature' => true];
                        if ($this->IsRoomSetpoint($entry)) { $setpoints[] = $entry; }
                    }
                }
            }
            $rows[] = ['name' => $room['Name'], 'actualID' => $room['Variable'], 'setpoints' => $setpoints];
        }
        return $rows;
    }

    private function TemperatureEntries(): array
    {
        $entries = [];
        foreach ($this->TemperatureSources() as $row) { foreach ($row['setpoints'] as $entry) { $entries[] = $entry; } }
        return $entries;
    }

    private function TemperatureRows(): array
    {
        $result = [];
        foreach ($this->TemperatureSources() as $row) {
            $setpoints = [];
            foreach ($row['setpoints'] as $entry) { $setpoints[] = $this->RoomItem($entry); }
            $result[] = ['name' => $row['name'], 'actual' => $this->Read($row['actualID']), 'setpoints' => $setpoints];
        }
        return $result;
    }
}
