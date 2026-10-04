<?php
declare(strict_types=1);

trait HausstatusNavigationSupport
{
    private function HideTileMaximize(): void
    {
        // The native tile header is outside the HTML frame. This API exists from 9.1.
        if (!function_exists('IPS_SetHiddenMaximize') || version_compare(IPS_GetKernelVersion(), '9.1', '<')) { return; }
        IPS_SetHiddenMaximize($this->InstanceID, true);
        foreach (IPS_GetObjectList() as $id) {
            if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID) {
                IPS_SetHiddenMaximize($id, true);
            }
        }
    }

    private function SupportsHtmlFullscreen(): bool
    {
        return version_compare(IPS_GetKernelVersion(), '9.1', '>=')
            && defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN')
            && constant('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') === 2;
    }

    private function HtmlVisualizationType(): int
    {
        return $this->ReadPropertyInteger('View') !== 0 && $this->SupportsHtmlFullscreen() ? 2 : 1;
    }

    public function GetVisualizationDiagnostics(): string
    {
        $html = $this->GetVisualizationTile();
        $library = json_decode((string)@file_get_contents(__DIR__ . '/../library.json'), true);
        $data = ['moduleVersion' => $library['version'] ?? 'unbekannt', 'instance' => $this->InstanceID, 'kernel' => IPS_GetKernelVersion(),
            'view' => $this->ReadPropertyInteger('View'), 'room' => $this->ReadPropertyString('RoomFilter'),
            'source' => $this->ReadPropertyInteger('ConfigSource'), 'active' => $this->ReadPropertyBoolean('ActiveView'),
            'inlinePages' => $this->UsesInlinePages(),
            'fullscreenSupported' => $this->SupportsHtmlFullscreen(), 'expectedType' => $this->HtmlVisualizationType(),
            'actualType' => IPS_GetInstance($this->InstanceID)['InstanceVisualizationType'],
            'htmlBytes' => strlen($html), 'initialStateInserted' => str_contains($html, 'let state=') && !str_contains($html, '/*INITIAL_STATE*/null'),
            'htmlError' => str_contains($html, 'data-svhs-error="true"')];
        if ($data['htmlError']) { $data['error'] = trim(strip_tags($html)); }
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE);
    }

    public function GetNavigationTargets(): string
    {
        return json_encode(['pv' => $this->PVDetailsTarget(), 'rooms' => $this->RoomNavigationTarget()], JSON_THROW_ON_ERROR);
    }

    private function NavigationOwner(): int
    {
        $source = $this->ReadPropertyInteger('ConfigSource');
        return $source > 0 ? $source : $this->InstanceID;
    }

    private function PVDetailsTarget(): int
    {
        $pv = $this->PVDetailsInstance();
        if ($pv === 0 || $this->SupportsHtmlFullscreen()) { return $pv; }
        // openObject(instance) maximizes a tile; older SDKs cannot show its HTML there.
        // Navigate to a category containing the existing normal HTML tile instead.
        $root = IPS_GetParent($this->NavigationOwner());
        $category = @IPS_GetObjectIDByIdent('SVHSPVPage', $root);
        if ($category === false || IPS_GetObject($category)['ObjectType'] !== 0) { return $pv; }
        $link = @IPS_GetObjectIDByIdent('SVHSPVPageLink', $category);
        return $link !== false && IPS_LinkExists($link) && IPS_GetLink($link)['TargetID'] === $pv ? $category : $pv;
    }

    private function PVDetailsInstance(): int
    {
        $owner = $this->NavigationOwner();
        if (!IPS_InstanceExists($owner)) { return 0; }
        $root = IPS_GetParent($owner);
        if ($root <= 0) { return 0; }
        $id = @IPS_GetObjectIDByIdent('SVHSTile_7', $root);
        if ($id === false || !IPS_InstanceExists($id)
            || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
            || IPS_GetProperty($id, 'ConfigSource') !== $owner || IPS_GetProperty($id, 'View') !== 7
            || !IPS_GetProperty($id, 'ActiveView')) { return 0; }
        return $id;
    }

    private function RoomNavigationTarget(): int
    {
        $owner = $this->NavigationOwner();
        if (!IPS_InstanceExists($owner) || IPS_GetParent($owner) <= 0) { return 0; }
        $id = @IPS_GetObjectIDByIdent('SVHSRoomsHTML', IPS_GetParent($owner));
        return $id !== false && IPS_GetObject($id)['ObjectType'] === 0 ? $id : 0;
    }

    private function OrganizeHomepageDetails(): void
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0 || $this->ReadPropertyInteger('View') !== 0) { return; }
        $pv = $this->PVDetailsInstance();
        if ($pv > 0) {
            $link = @IPS_GetObjectIDByIdent('SVHSTileLink_7', IPS_GetParent($this->InstanceID));
            if ($link !== false && IPS_LinkExists($link) && IPS_GetLink($link)['TargetID'] === $pv) {
                // The full PV view remains active and opens from the compact power card.
                IPS_SetHidden($link, true);
            }
            if (!$this->SupportsHtmlFullscreen()) { $this->EnsureLegacyPVPage($pv); }
        }
        $rooms = $this->RoomNavigationTarget();
        if ($rooms > 0) { $this->OrganizeRoomSelector($rooms); }
        if ($rooms > 0 && in_array(IPS_GetName($rooms), ['Räume und Geräte', 'Raumsteuerung'], true)) {
            IPS_SetName($rooms, 'Räume');
        }
    }

    private function OrganizeSeparateHomepage(): void
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0 || $this->ReadPropertyInteger('View') !== 0) { return; }
        $root = IPS_GetParent($this->InstanceID);
        if ($root <= 0 || IPS_GetObject($root)['ObjectType'] !== 0) { return; }
        $enabled = $this->ConfigBoolean('SeparateHomepageTiles');
        $has = function (array $names): bool { foreach ($names as $name) { if ($this->ConfigInteger($name) > 0) { return true; } } return false; };
        $tiles = [
            1 => [$this->DisplayText('StatusTitle'), $has(['Presence', 'Alarm'])],
            2 => [$this->DisplayText('DoorTitle'), $has(['Lock', 'DoorContact', 'DoorControl', 'DoorPermission'])],
            5 => [$this->DisplayText('LightState'), $has(['LightState', 'Brightness'])],
            17 => [$this->DisplayText('Dining'), $has(['DiningInstance', 'DiningState'])],
            6 => [$this->DisplayText('CinemaState'), $has(['CinemaState', 'CinemaControl'])],
            14 => [$this->ConfigString('HeaterTitle'), $has(['HeaterInstance', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'])],
            4 => [$this->DisplayText('BatteryWarnings'), $has(['BatteryWarnings'])],
            15 => [$this->DisplayText('PVPower'), $has(['PVPower', 'PVEnergy'])],
            11 => [$this->DisplayText('OutdoorTitle'), $has(['AwningPosition', 'AwningStatus', 'RoofPosition', 'RoofStatus'])],
            16 => [$this->DisplayText('WeatherTitle'), $has(['Weather', 'Wind', 'Rain', 'Raining'])],
            8 => [$this->DisplayText('MotionTitle'), !$this->ConfigBoolean('SeparateDetails') && count($this->MotionSensors()) > 0],
            9 => [$this->DisplayText('TemperatureTitle'), !$this->ConfigBoolean('SeparateDetails') && count($this->Rooms()) > 0]
        ];
        $active = $enabled && count(array_filter($tiles, static fn(array $tile): bool => $tile[1])) > 0;
        $position = 0;
        $visibleTiles = 0;
        foreach ($tiles as $view => [$title, $configured]) {
            $position += 10;
            $link = @IPS_GetObjectIDByIdent('SVHSHomeTile_' . $view, $root);
            if (!$active || !$configured) {
                if ($link !== false && IPS_LinkExists($link)
                    && $this->OwnedHomepageTile(IPS_GetLink($link)['TargetID'], $view)) { IPS_SetHidden($link, true); }
                $instance = @IPS_GetObjectIDByIdent('SVHSHomeInstance_' . $view, $root);
                if ($instance !== false && $this->OwnedHomepageTile($instance, $view)) { IPS_SetHidden($instance, true); }
                continue;
            }
            $instance = @IPS_GetObjectIDByIdent('SVHSHomeInstance_' . $view, $root);
            if ($instance !== false && !$this->OwnedHomepageTile($instance, $view)) { $this->SendDebug('Startseite', 'Kachelkennung ist anders belegt; nichts überschrieben.', 0); continue; }
            if ($link !== false && (!IPS_LinkExists($link) || !$this->OwnedHomepageTile(IPS_GetLink($link)['TargetID'], $view) || ($instance !== false && IPS_GetLink($link)['TargetID'] !== $instance))) {
                $this->SendDebug('Startseite', 'Linkkennung ist anders belegt; nichts überschrieben.', 0); continue;
            }
            if ($instance === false && $link !== false) { $instance = IPS_GetLink($link)['TargetID']; }
            if ($instance === false) {
                $instance = IPS_CreateInstance('{9E33E109-4881-4E78-9906-38CAC2F1E210}');
                IPS_SetParent($instance, $root); IPS_SetIdent($instance, 'SVHSHomeInstance_' . $view);
                IPS_SetName($instance, $title); IPS_SetPosition($instance, $position); IPS_SetHidden($instance, false);
                IPS_SetProperty($instance, 'ConfigSource', $this->InstanceID); IPS_SetProperty($instance, 'View', $view);
                IPS_ApplyChanges($instance);
            }
            // Native visualization must use the instance itself. Linked hidden HTML instances
            // are not rendered reliably as independent tiles by all supported clients.
            if ($link !== false) {
                if ((IPS_GetObject($instance)['ObjectIsHidden'] ?? false) && !(IPS_GetObject($link)['ObjectIsHidden'] ?? false)) {
                    IPS_SetPosition($instance, (int)IPS_GetObject($link)['ObjectPosition']);
                    IPS_SetName($instance, IPS_GetName($link));
                }
                if (!(IPS_GetObject($link)['ObjectIsHidden'] ?? false)) { IPS_SetHidden($link, true); }
            }
            $visibleTiles++;
            if (IPS_GetObject($instance)['ObjectIsHidden'] ?? false) { IPS_SetHidden($instance, false); }
            if (function_exists('IPS_SetHiddenMaximize') && version_compare(IPS_GetKernelVersion(), '9.1', '>=')) { IPS_SetHiddenMaximize($instance, true); }
        }
        // Save only the visibility flags changed by this feature. Never remove the master or legacy tiles.
        $previous = json_decode($this->GetBuffer('HomepageVisibility'), true) ?: [];
        if ($active && $visibleTiles > 0) {
            foreach (array_merge([$this->InstanceID], IPS_GetChildrenIDs($root)) as $id) {
                $master = $id === $this->InstanceID || (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID);
                $legacy = IPS_LinkExists($id) && preg_match('/^SVHSTileLink_(1|2|4|5|6|11)$/D', IPS_GetObject($id)['ObjectIdent'] ?? '')
                    && IPS_InstanceExists(IPS_GetLink($id)['TargetID'])
                    && $this->OwnedHomepageTile(IPS_GetLink($id)['TargetID'], (int)IPS_GetProperty(IPS_GetLink($id)['TargetID'], 'View'));
                if (!$master && !$legacy) { continue; }
                if (!array_key_exists((string)$id, $previous)) { $previous[(string)$id] = (bool)(IPS_GetObject($id)['ObjectIsHidden'] ?? false); }
                if (!(IPS_GetObject($id)['ObjectIsHidden'] ?? false)) { IPS_SetHidden($id, true); }
            }
            $this->SetBuffer('HomepageVisibility', json_encode($previous, JSON_THROW_ON_ERROR));
        } else {
            foreach ($previous as $id => $hidden) { if (IPS_ObjectExists((int)$id)) { IPS_SetHidden((int)$id, (bool)$hidden); } }
            $this->SetBuffer('HomepageVisibility', '');
        }
    }

    private function OwnedHomepageTile(int $id, int $view): bool
    {
        return IPS_InstanceExists($id) && IPS_GetInstance($id)['ModuleInfo']['ModuleID'] === '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
            && IPS_GetProperty($id, 'ConfigSource') === $this->InstanceID && IPS_GetProperty($id, 'View') === $view;
    }

    private function OrganizeRoomSelector(int $category): void
    {
        // Consolidate only this dashboard's room tiles inside its existing room menu.
        // Keep the instances and their configuration available for the selector and other links.
        $candidates = [];
        foreach (IPS_GetChildrenIDs($category) as $object) {
            $target = IPS_LinkExists($object) ? IPS_GetLink($object)['TargetID'] : $object;
            if (!IPS_InstanceExists($target)
                || IPS_GetInstance($target)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
                || IPS_GetProperty($target, 'ConfigSource') !== $this->InstanceID
                || IPS_GetProperty($target, 'View') !== 12 || !IPS_GetProperty($target, 'ActiveView')) { continue; }
            $candidates[] = ['object' => $object, 'target' => $target];
        }
        if (!$candidates) { return; }
        $visible = array_values(array_filter($candidates, static fn(array $c): bool => !(IPS_GetObject($c['object'])['ObjectIsHidden'] ?? false)));
        $pool = $visible ?: $candidates;
        $keeper = $pool[0]['object'];
        foreach ($pool as $candidate) {
            if (IPS_GetName($candidate['object']) === 'Räume' || IPS_GetProperty($candidate['target'], 'RoomFilter') === '') {
                $keeper = $candidate['object']; break;
            }
        }
        foreach ($candidates as $candidate) {
            $id = $candidate['object']; $hidden = $id !== $keeper;
            if ((IPS_GetObject($id)['ObjectIsHidden'] ?? false) !== $hidden) { IPS_SetHidden($id, $hidden); }
        }
        if (IPS_GetName($keeper) !== 'Räume') { IPS_SetName($keeper, 'Räume'); }
    }

    private function EnsureLegacyPVPage(int $pv): void
    {
        $root = IPS_GetParent($this->InstanceID);
        $category = @IPS_GetObjectIDByIdent('SVHSPVPage', $root);
        if ($category !== false && IPS_GetObject($category)['ObjectType'] !== 0) {
            $this->SendDebug('PV-Navigation', 'SVHSPVPage ist anders belegt; nichts verändert.', 0); return;
        }
        $link = $category !== false ? @IPS_GetObjectIDByIdent('SVHSPVPageLink', $category) : false;
        if ($link !== false && (!IPS_LinkExists($link) || IPS_GetLink($link)['TargetID'] !== $pv)) {
            $this->SendDebug('PV-Navigation', 'SVHSPVPageLink ist anders belegt; nichts verändert.', 0); return;
        }
        if ($category === false) {
            $category = IPS_CreateCategory(); IPS_SetParent($category, $root); IPS_SetIdent($category, 'SVHSPVPage');
            IPS_SetName($category, 'PV-Details'); IPS_SetHidden($category, true);
        }
        if ($link === false) {
            $link = IPS_CreateLink(); IPS_SetParent($link, $category); IPS_SetIdent($link, 'SVHSPVPageLink');
            IPS_SetName($link, 'PV-Details'); IPS_SetLinkTargetID($link, $pv); IPS_SetHidden($link, false);
        }
    }
}
