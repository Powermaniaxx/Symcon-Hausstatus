<?php
declare(strict_types=1);

trait HausstatusHomepageSupport
{
    private function OwnedHomepageTile(int $id, int $view): bool
    {
        return IPS_InstanceExists($id)
            && IPS_GetInstance($id)['ModuleInfo']['ModuleID'] === '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
            && IPS_GetProperty($id, 'ConfigSource') === $this->InstanceID
            && IPS_GetProperty($id, 'View') === $view;
    }

    private function HomepageObjects(): array
    {
        $result = [];
        foreach (IPS_GetObjectList() as $id) {
            if (!preg_match('/^SVHSHome(?:Instance|Tile)_([0-9]+)$/D', IPS_GetObject($id)['ObjectIdent'] ?? '', $match)) { continue; }
            $target = IPS_LinkExists($id) ? (int)IPS_GetLink($id)['TargetID'] : $id;
            if ($this->OwnedHomepageTile($target, (int)$match[1])) { $result[] = $id; }
        }
        return $result;
    }

    private function HomepageCategory(): int
    {
        $root = $this->ReadPropertyInteger('HomepageCategory');
        if ($root === 0) {
            // The master may live outside the visualization; use its existing presentation link.
            $candidates = [];
            foreach (IPS_GetObjectList() as $id) {
                if (!IPS_LinkExists($id) || IPS_GetLink($id)['TargetID'] !== $this->InstanceID) { continue; }
                $parent = IPS_GetParent($id);
                if ($parent > 0 && IPS_GetObject($parent)['ObjectType'] === 0) { $candidates[$parent] = true; }
            }
            if (count($candidates) !== 1) { throw new RuntimeException('Zielkategorie der Startseite auswählen. Ohne eindeutiges Ziel bleibt die gemeinsame Kachel sichtbar.'); }
            $root = (int)array_key_first($candidates);
        }
        if (!IPS_ObjectExists($root) || IPS_GetObject($root)['ObjectType'] !== 0) { throw new RuntimeException('Die Zielkategorie existiert nicht oder ist keine Kategorie.'); }
        for ($parent = $root; $parent > 0; $parent = IPS_GetParent($parent)) {
            if ($parent === $this->InstanceID) { throw new RuntimeException('Die Zielkategorie darf nicht unter der zentralen Instanz liegen.'); }
            if (IPS_GetObject($parent)['ObjectIsHidden'] ?? false) { throw new RuntimeException('Die Zielkategorie oder eine übergeordnete Kategorie ist ausgeblendet.'); }
        }
        return $root;
    }

    private function HomepageSavedLayout(): array
    {
        $saved = json_decode($this->ReadAttributeString('HomepageLayout'), true);
        return is_array($saved) ? $saved : [];
    }

    private function RestoreCombinedHomepage(): void
    {
        $saved = $this->HomepageSavedLayout();
        $previous = $saved['visibility'] ?? json_decode($this->GetBuffer('HomepageVisibility'), true);
        $split = $this->HomepageObjects();
        if (!($saved['active'] ?? false) && $this->GetBuffer('CombinedHomepageRestored') === '1' && !$previous) { return; }
        if (!$split && !$previous && !($saved['active'] ?? false)) { return; }
        foreach (is_array($previous) ? $previous : [] as $id => $hidden) {
            $id = (int)$id;
            if (!is_bool($hidden) || !IPS_ObjectExists($id)) { continue; }
            $target = IPS_LinkExists($id) ? (int)IPS_GetLink($id)['TargetID'] : $id;
            $owned = $target === $this->InstanceID || (IPS_InstanceExists($target)
                && $this->OwnedHomepageTile($target, (int)IPS_GetProperty($target, 'View')));
            if ($owned) { IPS_SetHidden($id, $hidden); }
        }
        // Recover the reachable combined view even when buffers from 0.27/0.28 were lost.
        IPS_SetHidden($this->InstanceID, false);
        foreach (IPS_GetObjectList() as $id) {
            if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID) { IPS_SetHidden($id, false); }
        }
        foreach ($split as $id) { IPS_SetHidden($id, true); }
        $this->WriteAttributeString('HomepageLayout', json_encode(['active' => false], JSON_THROW_ON_ERROR));
        $this->SetBuffer('HomepageVisibility', '');
        $this->SetBuffer('CombinedHomepageRestored', '1');
    }

    private function OrganizeSeparateHomepage(): void
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0 || $this->ReadPropertyInteger('View') !== 0) { return; }
        $this->SetBuffer('HomepageLayoutError', '');
        if (!$this->ReadPropertyBoolean('SeparateHomepageTiles')) { $this->RestoreCombinedHomepage(); return; }
        try { $this->BuildSeparateHomepage(); }
        catch (Throwable $e) {
            $this->RestoreCombinedHomepage();
            $this->SetBuffer('HomepageLayoutError', $e->getMessage());
            $this->SendDebug('Startseite', $e->getMessage(), 0);
        }
    }

    private function BuildSeparateHomepage(): void
    {
        $root = $this->HomepageCategory();
        $this->RegisterReference($root);
        $has = function (array $names): bool { foreach ($names as $name) { if ($this->ConfigInteger($name) > 0) { return true; } } return false; };
        $tiles = [
            1 => [$this->DisplayText('StatusTitle'), $has(['Presence', 'Alarm'])],
            2 => [$this->DisplayText('DoorTitle'), $has(['Lock', 'DoorContact', 'DoorControl', 'DoorPermission'])],
            5 => [$this->DisplayText('LightState'), $has(['LightState', 'Brightness'])],
            17 => [$this->DisplayText('Dining'), $has(['DiningInstance', 'DiningState'])],
            6 => [$this->DisplayText('CinemaState'), $has(['CinemaState', 'CinemaControl'])],
            14 => [$this->ConfigString('HeaterTitle'), $has(['HeaterInstance', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'])],
            4 => [$this->DisplayText('BatteryWarnings'), $has(['BatteryWarnings'])],
            15 => [$this->DisplayText('PVPower'), $has(['PVPower', 'PVEnergy']) || count($this->PVInverterSources()) > 0],
            11 => [$this->DisplayText('OutdoorTitle'), $has(['AwningPosition', 'AwningStatus', 'RoofPosition', 'RoofStatus'])],
            16 => [$this->DisplayText('WeatherTitle'), $has(['Weather', 'Wind', 'Rain', 'Raining'])],
            8 => [$this->DisplayText('MotionTitle'), !$this->ConfigBoolean('SeparateDetails') && count($this->MotionSensors()) > 0],
            9 => [$this->DisplayText('TemperatureTitle'), !$this->ConfigBoolean('SeparateDetails') && count($this->Rooms()) > 0]
        ];
        if (!array_filter($tiles, static fn(array $tile): bool => $tile[1])) { $this->RestoreCombinedHomepage(); return; }
        $objects = $this->HomepageObjects();
        $plan = [];
        // Validate every destination before changing the visibility of the existing homepage.
        foreach ($tiles as $view => [$title, $configured]) {
            if (!$configured) { continue; }
            $instances = [];
            foreach ($objects as $id) {
                $target = IPS_LinkExists($id) ? (int)IPS_GetLink($id)['TargetID'] : $id;
                if ($this->OwnedHomepageTile($target, $view)) { $instances[$target] = true; }
            }
            if (count($instances) > 1) { throw new RuntimeException('Mehrere eigene Kacheln für Ansicht ' . $view . ' gefunden. Keine weitere Kachel angelegt.'); }
            $instance = $instances ? (int)array_key_first($instances) : 0;
            $collision = @IPS_GetObjectIDByIdent('SVHSHomeInstance_' . $view, $root);
            if ($collision !== false && $collision !== $instance) { throw new RuntimeException('Eine fremde Kachel belegt die Kennung für Ansicht ' . $view . '.'); }
            $plan[$view] = ['id' => $instance, 'title' => $title];
        }
        $saved = $this->HomepageSavedLayout();
        $previous = $saved['visibility'] ?? json_decode($this->GetBuffer('HomepageVisibility'), true) ?? [];
        if (!is_array($previous)) { $previous = []; }
        $hide = [$this->InstanceID];
        foreach (IPS_GetObjectList() as $id) {
            if (!IPS_LinkExists($id)) { continue; }
            $target = (int)IPS_GetLink($id)['TargetID'];
            $legacy = preg_match('/^SVHSTileLink_(1|2|4|5|6|11)$/D', IPS_GetObject($id)['ObjectIdent'] ?? '')
                && IPS_InstanceExists($target) && $this->OwnedHomepageTile($target, (int)IPS_GetProperty($target, 'View'));
            if ($target === $this->InstanceID || $legacy) { $hide[] = $id; }
        }
        foreach ($hide as $id) {
            if (!array_key_exists((string)$id, $previous)) { $previous[(string)$id] = (bool)(IPS_GetObject($id)['ObjectIsHidden'] ?? false); }
        }
        // Save before mutations and persist across restarts and switching to rollback-0.26.
        $this->WriteAttributeString('HomepageLayout', json_encode(['active' => true, 'root' => $root, 'visibility' => $previous], JSON_THROW_ON_ERROR));
        $this->SetBuffer('HomepageVisibility', json_encode($previous, JSON_THROW_ON_ERROR));
        $this->SetBuffer('CombinedHomepageRestored', '');
        $position = 0;
        foreach ($plan as $view => $entry) {
            $position += 10;
            $id = $entry['id'];
            if ($id === 0) {
                $id = IPS_CreateInstance('{9E33E109-4881-4E78-9906-38CAC2F1E210}');
                IPS_SetParent($id, $root); IPS_SetIdent($id, 'SVHSHomeInstance_' . $view);
                IPS_SetProperty($id, 'ConfigSource', $this->InstanceID); IPS_SetProperty($id, 'View', $view);
            } elseif (IPS_GetParent($id) !== $root) { IPS_SetParent($id, $root); }
            // Keep existing split tiles in the configured order as well. Older versions
            // only assigned a position when a tile was first created, so later ordering
            // changes did not affect already existing homepage tiles.
            IPS_SetName($id, $entry['title']);
            IPS_SetPosition($id, $position);
            if (!IPS_GetProperty($id, 'ActiveView')) { IPS_SetProperty($id, 'ActiveView', true); }
            IPS_ApplyChanges($id);
            IPS_SetHidden($id, false);
            $plan[$view]['id'] = $id;
        }
        foreach ($objects as $id) {
            $view = (int)IPS_GetProperty(IPS_LinkExists($id) ? IPS_GetLink($id)['TargetID'] : $id, 'View');
            if (IPS_LinkExists($id) || !isset($plan[$view])) { IPS_SetHidden($id, true); }
        }
        foreach ($hide as $id) { IPS_SetHidden($id, true); }
    }

    public function GetHomepageLayoutDiagnostics(): string
    {
        $rows = [];
        foreach ($this->HomepageObjects() as $id) {
            $target = IPS_LinkExists($id) ? IPS_GetLink($id)['TargetID'] : $id;
            $rows[] = ['id' => $id, 'name' => IPS_GetName($id), 'parent' => IPS_GetParent($id), 'view' => IPS_GetProperty($target, 'View'), 'hidden' => IPS_GetObject($id)['ObjectIsHidden']];
        }
        return json_encode(['version' => (json_decode((string)file_get_contents(__DIR__ . '/../library.json'), true)['version'] ?? 'unbekannt'), 'category' => $this->ReadPropertyInteger('HomepageCategory'), 'lastError' => $this->GetBuffer('HomepageLayoutError'), 'tiles' => $rows], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
