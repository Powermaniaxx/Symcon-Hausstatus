<?php
declare(strict_types=1);

trait HausstatusComfortSupport
{
    private function HeaterIDs(): array
    {
        $ids = [];
        foreach (['HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'] as $name) { $ids[$name] = $this->ConfigInteger($name); }
        $instance = $this->ConfigInteger('HeaterInstance');
        if ($ids['HeaterSwitch'] === 0 && $instance > 0 && IPS_InstanceExists($instance)) {
            try { $ids['HeaterSwitch'] = $this->DiningChild($instance, ['state', 'status', 'on'], [0]); }
            catch (Throwable $e) { /* An explicit switch source resolves missing or ambiguous channels. */ }
        }
        return $ids;
    }

    private function HeaterState(): ?array
    {
        $ids = $this->HeaterIDs();
        if (!array_filter($ids) && $this->ConfigInteger('HeaterInstance') === 0) { return null; }
        $result = ['title' => $this->ConfigString('HeaterTitle'), 'items' => []];
        foreach (['HeaterSwitch' => 'Elektroheizkörper', 'HeaterMinimum' => 'Minimaltemperatur', 'HeaterMaximum' => 'Maximaltemperatur',
            'HeaterManual' => 'Heizung Manuell', 'HeaterWeekplan' => 'Wochenplan'] as $key => $name) {
            $id = $ids[$key];
            if ($id === 0) { continue; }
            if (!IPS_VariableExists($id)) { $result['items'][$key] = ['name' => $name, 'value' => ['raw' => null, 'text' => 'Quelle fehlt'], 'control' => null]; continue; }
            $item = $this->RoomItem(['id' => $id, 'type' => 2, 'name' => $name, 'operate' => $this->ConfigBoolean('HeaterEnabled')]);
            $item['command'] = $key; $result['items'][$key] = $item;
        }
        if ($ids['HeaterSwitch'] === 0) { $result['note'] = 'Schaltvariable der Zusatzheizung in der Konfiguration auswählen.'; }
        return $result;
    }

    private function SetHeater(string $command, mixed $value): void
    {
        if ($this->CurrentView() !== 0 || !$this->ConfigBoolean('HeaterEnabled') || !$this->ConfigBoolean('RoomControlsEnabled')) {
            throw new RuntimeException('Zusatzheizung ist in dieser Ansicht nicht bedienbar.');
        }
        $ids = $this->HeaterIDs(); $id = $ids[$command] ?? 0;
        if ($id <= 0 || !IPS_VariableExists($id) || $this->ProtectedDoorTarget($id)) { throw new RuntimeException('Ungültige Heizungsquelle.'); }
        $type = IPS_GetVariable($id)['VariableType'];
        $this->ValidateAction($id, $type);
        if (in_array($command, ['HeaterMinimum', 'HeaterMaximum'], true)) {
            if (!in_array($type, [1, 2], true)) { throw new RuntimeException('Temperaturgrenze benötigt eine Zahl.'); }
            $other = $ids[$command === 'HeaterMinimum' ? 'HeaterMaximum' : 'HeaterMinimum'];
            $otherValue = $this->Read($other)['raw'];
            if (is_numeric($value) && is_numeric($otherValue) && ($command === 'HeaterMinimum' ? $value > $otherValue : $value < $otherValue)) {
                throw new RuntimeException('Minimaltemperatur darf nicht über der Maximaltemperatur liegen.');
            }
            $this->SetSlider($id, $value, $this->SliderPlan($id)); return;
        }
        if ($type !== 0 || !is_bool($value)) { throw new RuntimeException('Heizungsschalter erwartet Boolean.'); }
        if (!RequestAction($id, $value)) { throw new RuntimeException('Heizungsaktion fehlgeschlagen.'); }
    }

    private function DiningSources(): array
    {
        $instance = $this->ConfigInteger('DiningInstance');
        $state = $this->ConfigInteger('DiningState');
        $brightness = $this->ConfigInteger('DiningBrightness');
        if ($state === 0 && $instance === 0) {
            throw new RuntimeException('Quelle für die zusätzliche Lampe in der zentralen Konfiguration auswählen.');
        }
        if ($state === 0) {
            if (!IPS_InstanceExists($instance)) { throw new RuntimeException('Die gewählte zusätzliche Lichtinstanz fehlt.'); }
            $state = $this->DiningChild($instance, ['status', 'state', 'on'], [0]);
        }
        if ($state <= 0 || !IPS_VariableExists($state) || IPS_GetVariable($state)['VariableType'] !== 0
            || $state === $this->ConfigInteger('LightState') || $this->ProtectedDoorTarget($state)) {
            throw new RuntimeException('Die zusätzliche Lampe benötigt eine eigene Boolean-Statusvariable.');
        }
        if ($brightness === 0) {
            $parent = IPS_GetParent($state);
            if (IPS_InstanceExists($parent)) { $brightness = $this->DiningChild($parent, ['helligkeit', 'brightness'], [1, 2]); }
        }
        if ($brightness > 0 && (!IPS_VariableExists($brightness) || !in_array(IPS_GetVariable($brightness)['VariableType'], [1, 2], true)
            || $this->ProtectedDoorTarget($brightness))) { throw new RuntimeException('Die Helligkeitsquelle der zusätzlichen Lampe ist ungültig.'); }
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
            if (!$this->ConfigBoolean('DiningEnabled')) { throw new RuntimeException('Bedienung der zusätzlichen Lampe ist deaktiviert.'); }
            $result['canSwitch'] = $this->HasAction($ids['state'], 0);
            if (!$result['canSwitch']) { $result['reason'] = 'Die zusätzliche Statusvariable hat keine aktive Bedienaktion.'; }
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
        $title = $this->DisplayText('Dining');
        foreach (['state' => $title . ' · Status', 'brightness' => $title . ' · Helligkeit'] as $key => $name) {
            if ($ids[$key] > 0) { $entries[] = ['room' => $this->ConfigString('DiningRoom'), 'group' => 'Licht', 'name' => $name,
                'id' => $ids[$key], 'type' => 2, 'operate' => true, 'deviceKey' => 'device:' . IPS_GetParent($ids[$key]), 'deviceName' => $title]; }
        }
        return $entries;
    }

    private function SetDining(string $command, mixed $value): void
    {
        if (!$this->ConfigBoolean('DiningEnabled')) { throw new RuntimeException('Bedienung der zusätzlichen Lampe ist deaktiviert.'); }
        $ids = $this->DiningSources();
        if ($command === 'DiningBrightness') {
            $this->SetSlider($ids['brightness'], $value, $this->SliderPlan($ids['brightness']));
        } else {
            if (!is_bool($value)) { throw new RuntimeException('Die zusätzliche Lampe erwartet Ein/Aus.'); }
            $this->ValidateAction($ids['state'], 0);
            if (!RequestAction($ids['state'], $value)) { throw new RuntimeException('Aktion der zusätzlichen Lampe fehlgeschlagen.'); }
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
        $view = $this->CurrentView();
        if (!in_array($view, [9, 12], true)) { return []; }
        $filter = $view === 12 ? $this->CurrentRoom() : '';
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
