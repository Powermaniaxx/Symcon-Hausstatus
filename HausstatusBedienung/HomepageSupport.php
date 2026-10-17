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
            $ident = IPS_GetObject($id)['ObjectIdent'] ?? '';
            if (preg_match('/^SVHSHomePart_([0-9]+)$/D', $ident)) {
                if ($this->OwnedHomepageTile($id, 0)) { $result[] = $id; }
                continue;
            }
            if (!preg_match('/^SVHSHome(?:Instance|Tile)_([0-9]+)$/D', $ident, $match)) { continue; }
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

        // Always normalize the combined presentation. Older builds returned here
        // after the first restore, which allowed a later-visible instance/link pair
        // to remain on the start page and render the complete dashboard twice.
        foreach (is_array($previous) ? $previous : [] as $id => $hidden) {
            $id = (int)$id;
            if (!is_bool($hidden) || !IPS_ObjectExists($id)) { continue; }
            $target = IPS_LinkExists($id) ? (int)IPS_GetLink($id)['TargetID'] : $id;
            $owned = $target === $this->InstanceID || (IPS_InstanceExists($target)
                && $this->OwnedHomepageTile($target, (int)IPS_GetProperty($target, 'View')));
            if ($owned) { IPS_SetHidden($id, $hidden); }
        }
        // Recover exactly one reachable combined homepage tile. The master instance
        // and a presentation link to the same instance must never both be visible,
        // otherwise Symcon renders the complete Hausstatus twice.
        $root = $this->ReadPropertyInteger('HomepageCategory');
        if ($root <= 0 || !IPS_ObjectExists($root) || IPS_GetObject($root)['ObjectType'] !== 0) {
            $root = IPS_GetParent($this->InstanceID);
        }
        $links = [];
        foreach (IPS_GetChildrenIDs($root) as $id) {
            if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID) {
                $links[] = $id;
            }
        }
        if ($links) {
            usort($links, static function (int $a, int $b): int {
                $pa = IPS_GetObject($a)['ObjectPosition'] ?? 0;
                $pb = IPS_GetObject($b)['ObjectPosition'] ?? 0;
                return $pa <=> $pb ?: $a <=> $b;
            });
            $keeper = $links[0];
            foreach ($links as $id) {
                if (($previous[(string)$id] ?? true) === false) { $keeper = $id; break; }
            }
            IPS_SetHidden($this->InstanceID, true);
            foreach ($links as $id) { IPS_SetHidden($id, $id !== $keeper); }
        } else {
            IPS_SetHidden($this->InstanceID, false);
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
        try {
            $this->BuildSeparateHomepage();
            $this->HideHomepageQuickLinks();
            // Cleanup only Susi's mirrored overview if individual tiles exist.
            try { $this->HideSusiMirroredCombinedHomepage(); }
            catch (Throwable $e) { $this->SendDebug('Susi-Startseite', $e->getMessage(), 0); }
        }
        catch (Throwable $e) {
            $this->RestoreCombinedHomepage();
            $this->SetBuffer('HomepageLayoutError', $e->getMessage());
            $this->SendDebug('Startseite', $e->getMessage(), 0);
        }
    }

    /**
     * Only hide duplicate links placed directly on the homepage.
     * The target categories stay visible for native sidebar navigation.
     */
    private function HideHomepageQuickLinks(): void
    {
        $root = $this->HomepageCategory();
        $names = ['Anwesenheit', 'Heizung · Pläne und Wetter', 'PV-Details', 'System', 'Räume'];
        $hidden = [];
        foreach (IPS_GetChildrenIDs($root) as $id) {
            if (!IPS_LinkExists($id)) { continue; }
            $name = $this->PresentationBaseName(IPS_GetName($id));
            if (!in_array($name, $names, true)) { continue; }
            if (!IPS_ObjectExists((int)IPS_GetLink($id)['TargetID'])) { continue; }
            if (!(IPS_GetObject($id)['ObjectIsHidden'] ?? false)) { IPS_SetHidden($id, true); }
            $hidden[] = $id;
        }
        $this->SetBuffer('HiddenHomepageQuickLinks', json_encode($hidden, JSON_THROW_ON_ERROR));
    }

    /**
     * The mirroring script used to copy the combined View=0/HomepagePart=0
     * alongside Susi's individual tiles. Hide only this duplicate in Susi's
     * mirrored category, leaving the shared master, Sven and Sophie intact.
     * Preserve all objects for a deliberate later cleanup.
     */
    private function HideSusiMirroredCombinedHomepage(): void
    {
        $masterCategory = 55503;
        if (!IPS_ObjectExists($masterCategory) || (int)IPS_GetObject($masterCategory)['ObjectType'] !== 0) { return; }
        $parent = IPS_GetParent($masterCategory);
        if ($parent <= 0) { return; }
        $root = @IPS_GetObjectIDByIdent('MirrorVisualisierungSusi', $parent);
        if ($root === false || (int)IPS_GetObject($root)['ObjectType'] !== 0) { return; }

        $queue = [[$root, 0]];
        $combined = [];
        $individual = 0;
        while ($queue) {
            [$category, $depth] = array_shift($queue);
            foreach (IPS_GetChildrenIDs($category) as $id) {
                $object = IPS_GetObject($id);
                if ((int)$object['ObjectType'] === 0) {
                    if ($depth < 3 && !($object['ObjectIsHidden'] ?? false)) { $queue[] = [$id, $depth + 1]; }
                    continue;
                }
                $target = IPS_LinkExists($id) ? (int)IPS_GetLink($id)['TargetID'] : $id;
                if (!IPS_InstanceExists($target)
                    || IPS_GetInstance($target)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}') { continue; }
                if ($target !== $this->InstanceID && (int)IPS_GetProperty($target, 'ConfigSource') !== $this->InstanceID) { continue; }
                if ($object['ObjectIsHidden'] ?? false) { continue; }
                $view = (int)IPS_GetProperty($target, 'View');
                $part = (int)IPS_GetProperty($target, 'HomepagePart');
                if ($view === 0 && $part === 0) { $combined[] = $id; }
                else { $individual++; }
            }
        }
        // Never hide the only working tile in Susi's visualization.
        if ($individual === 0) { return; }
        foreach ($combined as $id) { IPS_SetHidden($id, true); }
    }

    private function BuildSeparateHomepage(): void
    {
        if ($this->ReadPropertyBoolean('PreserveHomepageStyle')) { $this->BuildPreservedHomepage(); return; }
        $root = $this->HomepageCategory();
        $this->RegisterReference($root);
        $has = function (array $names): bool { foreach ($names as $name) { if ($this->ConfigInteger($name) > 0) { return true; } } return false; };
        $tiles = [
            28 => [$this->DisplayText('StatusTitle') . ' · ' . $this->DisplayText('DoorTitle'), $has(['Presence', 'Alarm', 'Lock', 'DoorContact', 'DoorControl', 'DoorPermission'])],
            29 => [$this->DisplayText('PVPower') . ' · Batteriewarnung', $has(['PVPower', 'PVEnergy', 'BatteryWarnings']) || count($this->PVInverterSources()) > 0],
            27 => [$this->DisplayText('LaundryTitle'), $has(['WasherPower', 'DryerPower', 'WasherRunning', 'DryerRunning'])],
            5 => [$this->DisplayText('LightState'), $has(['LightState', 'Brightness'])],
            17 => [$this->DisplayText('Dining'), $has(['DiningInstance', 'DiningState'])],
            6 => [$this->DisplayText('CinemaState'), $has(['CinemaState', 'CinemaControl'])],
            14 => [$this->ConfigString('HeaterTitle'), $has(['HeaterInstance', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'])],
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
            IPS_SetName($id, $this->VersionedPresentationName($entry['title']));
            IPS_SetPosition($id, $position);
            if (!IPS_GetProperty($id, 'ActiveView')) { IPS_SetProperty($id, 'ActiveView', true); }
            IPS_ApplyChanges($id);
            IPS_SetHidden($id, false);
            $plan[$view]['id'] = $id;
        }
        foreach ($objects as $id) {
            $view = (int)IPS_GetProperty(IPS_LinkExists($id) ? IPS_GetLink($id)['TargetID'] : $id, 'View');
            if (IPS_LinkExists($id) || !isset($plan[$view]) || preg_match('/^SVHSHomePart_/', IPS_GetObject($id)['ObjectIdent'] ?? '')) { IPS_SetHidden($id, true); }
        }
        foreach ($hide as $id) { IPS_SetHidden($id, true); }
    }

    private function BuildPreservedHomepage(): void
    {
        $root = $this->HomepageCategory();
        $this->RegisterReference($root);
        $tiles = [1 => 'Anwesenheit, Alarm und Haustür', 2 => 'PV und Batteriewarnung',
            3 => 'Waschmaschine und Trockner', 4 => 'Wohnzimmerlicht',
            5 => 'Medien Wohnzimmer', 6 => 'Medien Schlafzimmer', 7 => 'Medien Ankleidezimmer',
            8 => 'Wetter, Markise und Dachfenster', 9 => 'Räume und Zusatzheizung',
            10 => 'Bewegung', 11 => 'Raumgeräte', 12 => 'Zusätzliche Variablen'];
        $plan = [];
        // Preflight every identifier before hiding or moving an existing presentation.
        foreach ($tiles as $part => $title) {
            $id = @IPS_GetObjectIDByIdent('SVHSHomePart_' . $part, $root);
            if ($id !== false && !$this->OwnedHomepageTile($id, 0)) {
                throw new RuntimeException('Kennung für Hausstatus-Bereich ' . $part . ' ist bereits belegt.');
            }
            $plan[$part] = ['id' => $id === false ? 0 : $id, 'title' => $title];
        }
        $saved = $this->HomepageSavedLayout();
        $previous = $saved['visibility'] ?? [];
        $hide = [$this->InstanceID];
        // Only replace the selected start page; other visualizations keep their master links.
        foreach (IPS_GetChildrenIDs($root) as $id) {
            if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID) { $hide[] = $id; }
        }
        foreach ($hide as $id) {
            if (!array_key_exists((string)$id, $previous)) { $previous[(string)$id] = (bool)IPS_GetObject($id)['ObjectIsHidden']; }
        }
        $this->WriteAttributeString('HomepageLayout', json_encode(['active' => true, 'root' => $root, 'visibility' => $previous], JSON_THROW_ON_ERROR));
        $enabled = function (int $part): bool {
            return match ($part) {
                3 => $this->ConfigInteger('WasherPower') > 0 || $this->ConfigInteger('DryerPower') > 0,
                6, 7 => $this->ConfigBoolean('ShieldEnabled'),
                10 => !$this->ConfigBoolean('SeparateDetails') && count($this->MotionSensors()) > 0,
                11 => count($this->RoomSections()) > 0,
                12 => count($this->ExtraTileVariables()) > 0,
                default => true
            };
        };
        $active = [];
        foreach ($plan as $part => $entry) {
            if (!$enabled($part)) { continue; }
            $id = $entry['id'];
            if ($id === 0) {
                $id = IPS_CreateInstance('{9E33E109-4881-4E78-9906-38CAC2F1E210}');
                IPS_SetParent($id, $root); IPS_SetIdent($id, 'SVHSHomePart_' . $part);
                IPS_SetProperty($id, 'ConfigSource', $this->InstanceID);
            }
            // Also repair reused tiles: the stable SVHSHomePart_N ident defines the part.
            IPS_SetProperty($id, 'ConfigSource', $this->InstanceID);
            IPS_SetProperty($id, 'View', 0);
            IPS_SetProperty($id, 'HomepagePart', $part);
            IPS_SetName($id, $this->VersionedPresentationName($entry['title'])); IPS_SetPosition($id, $part * 10);
            IPS_SetProperty($id, 'ActiveView', true);
            IPS_SetProperty($id, 'ShieldCompact', $this->ReadPropertyBoolean('ShieldCompact'));
            if ($part === 12) { IPS_SetProperty($id, 'ExtraTileVariables', $this->ReadPropertyString('ExtraTileVariables')); }
            IPS_ApplyChanges($id); IPS_SetHidden($id, false); $active[] = $id;
        }
        foreach ($this->HomepageObjects() as $id) {
            if (!in_array($id, $active, true)) { IPS_SetHidden($id, true); }
        }
        foreach ($hide as $id) { IPS_SetHidden($id, true); }
    }

    public function GetHomepageLayoutDiagnostics(): string
    {
        $rows = [];
        foreach ($this->HomepageObjects() as $id) {
            $target = IPS_LinkExists($id) ? (int) IPS_GetLink($id)['TargetID'] : $id;
            $rows[] = [
                'id' => $id,
                'name' => IPS_GetName($id),
                'ident' => IPS_GetObject($id)['ObjectIdent'],
                'parent' => IPS_GetParent($id),
                'target' => $target,
                'view' => IPS_GetProperty($target, 'View'),
                'part' => IPS_GetProperty($target, 'HomepagePart'),
                'hidden' => IPS_GetObject($id)['ObjectIsHidden']
            ];
        }

        // Report duplicate candidates without touching objects. Compare instance
        // targets, View, HomepagePart and RoomFilter, not names alone.
        $root = $this->ReadPropertyInteger('HomepageCategory');
        $roots = [];
        if ($root > 0 && IPS_ObjectExists($root) && (int) IPS_GetObject($root)['ObjectType'] === 0) {
            $roots['Startseite'] = $root;
        }
        if (IPS_ObjectExists(55503) && (int) IPS_GetObject(55503)['ObjectType'] === 0) {
            $parent = IPS_GetParent(55503);
            foreach (IPS_GetChildrenIDs($parent) as $id) {
                $obj = IPS_GetObject($id);
                if ((int) $obj['ObjectType'] !== 0) { continue; }
                if ($obj['ObjectIdent'] === 'MirrorVisualisierungSusi' || $this->PresentationBaseName((string) $obj['ObjectName']) === 'Visualisierung Susi') {
                    $roots['Susi'] = $id;
                    break;
                }
            }
        }
        $duplicates = [];
        foreach ($roots as $label => $category) {
            $groups = [];
            $queue = [[$category, 0]];
            while ($queue) {
                [$parent, $depth] = array_shift($queue);
                foreach (IPS_GetChildrenIDs($parent) as $id) {
                    $obj = IPS_GetObject($id);
                    if ($obj['ObjectIsHidden']) { continue; }
                    if ((int) $obj['ObjectType'] === 0) {
                        if ($depth < 2) { $queue[] = [$id, $depth + 1]; }
                        continue;
                    }
                    $target = IPS_LinkExists($id) ? (int) IPS_GetLink($id)['TargetID'] : $id;
                    if (!IPS_InstanceExists($target)
                        || IPS_GetInstance($target)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}') { continue; }
                    if ($target !== $this->InstanceID && (int) IPS_GetProperty($target, 'ConfigSource') !== $this->InstanceID) { continue; }
                    $key = implode(':', [
                        (int) IPS_GetProperty($target, 'View'),
                        (int) IPS_GetProperty($target, 'HomepagePart'),
                        (string) IPS_GetProperty($target, 'RoomFilter')
                    ]);
                    $groups[$key][] = [
                        'id' => $id, 'target' => $target,
                        'parent' => $parent, 'name' => IPS_GetName($id),
                        'ident' => $obj['ObjectIdent']
                    ];
                }
            }
            foreach ($groups as $key => $items) {
                if (count($items) > 1) {
                    $duplicates[] = ['visualization' => $label, 'viewPartRoom' => $key, 'objects' => $items];
                }
            }
        }

        return json_encode([
            'version' => $this->HausstatusReleaseVersion(),
            'category' => $root,
            'lastError' => $this->GetBuffer('HomepageLayoutError'),
            'tiles' => $rows,
            'duplicateCandidates' => $duplicates
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
