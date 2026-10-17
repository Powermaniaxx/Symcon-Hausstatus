<?php
declare(strict_types=1);

trait HausstatusNavigationSupport
{
    // Keep the version shown in Symcon tied to library.json, never to a
    // hardcoded caption. Version labels do not change object IDs or idents.
    private function HausstatusReleaseVersion(): string
    {
        $json = @file_get_contents(__DIR__ . '/../library.json');
        $library = is_string($json) ? json_decode($json, true) : null;
        $version = is_array($library) ? (string)($library['version'] ?? '') : '';
        return preg_match('/^[0-9]+\\.[0-9]+(?:\\.[0-9]+)?$/D', $version) ? $version : 'unbekannt';
    }

    private function PresentationBaseName(string $name): string
    {
        $name = trim($name);
        return preg_replace('/\\s*·\\s*v[0-9]+\\.[0-9]+(?:\\.[0-9]+)?$/u', '', $name) ?? $name;
    }

    private function VersionedPresentationName(string $name): string
    {
        return $this->PresentationBaseName($name) . ' · v' . $this->HausstatusReleaseVersion();
    }
    private function HideTileMaximize(): void
    {
        // The native tile header is outside the HTML frame. This API exists from 9.1.
        if (!function_exists('IPS_SetHiddenMaximize') || version_compare(IPS_GetKernelVersion(), '9.1', '<')) { return; }
        // Only the combined homepage tile (View 0) should lose its native title.
        // Keep all other tile titles visible for orientation.
        $hideTitle = $this->ReadPropertyInteger('View') === 0 && function_exists('IPS_SetHiddenTitle');
        IPS_SetHiddenMaximize($this->InstanceID, true);
        if ($hideTitle) { IPS_SetHiddenTitle($this->InstanceID, true); }
        foreach (IPS_GetObjectList() as $id) {
            if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID) {
                IPS_SetHiddenMaximize($id, true);
                if ($hideTitle) { IPS_SetHiddenTitle($id, true); }
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
        // A single PV detail view inside the PV category needs no duplicate link.
        if (IPS_GetParent($pv) === $category) { return $category; }
        $link = @IPS_GetObjectIDByIdent('SVHSPVPageLink', $category);
        return $link !== false && IPS_LinkExists($link) && IPS_GetLink($link)['TargetID'] === $pv ? $category : $pv;
    }

    private function PVDetailsInstance(): int
    {
        $owner = $this->NavigationOwner();
        if (!IPS_InstanceExists($owner)) { return 0; }
        $root = IPS_GetParent($owner);
        if ($root <= 0) { return 0; }
        $pvCategory = @IPS_GetObjectIDByIdent('SVHSPVPage', $root);
        // New layout: the detail instance belongs INSIDE the PV category.
        // Legacy installs may still keep SVHSTile_7 directly under the root.
        $parents = [];
        if ($pvCategory !== false && (int)IPS_GetObject($pvCategory)['ObjectType'] === 0) {
            $parents[] = (int)$pvCategory;
        }
        $parents[] = $root;
        foreach ($parents as $parent) {
            $id = @IPS_GetObjectIDByIdent('SVHSTile_7', $parent);
            if ($id === false || !IPS_InstanceExists($id)
                || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
                || (int)IPS_GetProperty($id, 'ConfigSource') !== $owner
                || (int)IPS_GetProperty($id, 'View') !== 7
                || !IPS_GetProperty($id, 'ActiveView')) { continue; }
            return (int)$id;
        }
        return 0;
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
        if ($rooms > 0 && in_array($this->PresentationBaseName(IPS_GetName($rooms)), ['Räume und Geräte', 'Raumsteuerung', 'Räume'], true)) {
            $name = 'Räume';
            if (IPS_GetName($rooms) !== $name) { IPS_SetName($rooms, $name); }
        }
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
            if ($this->PresentationBaseName(IPS_GetName($candidate['object'])) === 'Räume' || IPS_GetProperty($candidate['target'], 'RoomFilter') === '') {
                $keeper = $candidate['object']; break;
            }
        }
        foreach ($candidates as $candidate) {
            $id = $candidate['object']; $hidden = $id !== $keeper;
            if ((IPS_GetObject($id)['ObjectIsHidden'] ?? false) !== $hidden) { IPS_SetHidden($id, $hidden); }
        }
        $roomName = (IPS_LinkExists($keeper) || IPS_VariableExists($keeper))
            ? $this->VersionedPresentationName('Räume') : 'Räume';
        if (IPS_GetName($keeper) !== $roomName) { IPS_SetName($keeper, $roomName); }
    }

    private function EnsureLegacyPVPage(int $pv): void
    {
        $root = IPS_GetParent($this->InstanceID);
        $category = @IPS_GetObjectIDByIdent('SVHSPVPage', $root);
        if ($category !== false && IPS_GetObject($category)['ObjectType'] !== 0) {
            $this->SendDebug('PV-Navigation', 'SVHSPVPage ist anders belegt; nichts verändert.', 0); return;
        }
        $link = $category !== false ? @IPS_GetObjectIDByIdent('SVHSPVPageLink', $category) : false;
        if ($category !== false && IPS_GetParent($pv) === $category) {
            // The direct detail tile replaces the old presentation link.
            if ($link !== false && IPS_LinkExists($link) && IPS_GetLink($link)['TargetID'] === $pv) {
                IPS_SetHidden($link, true);
            }
            return;
        }
        if ($link !== false && (!IPS_LinkExists($link) || IPS_GetLink($link)['TargetID'] !== $pv)) {
            $this->SendDebug('PV-Navigation', 'SVHSPVPageLink ist anders belegt; nichts verändert.', 0); return;
        }
        if ($category === false) {
            $category = IPS_CreateCategory(); IPS_SetParent($category, $root); IPS_SetIdent($category, 'SVHSPVPage');
            IPS_SetName($category, 'PV-Details'); IPS_SetHidden($category, true);
        }
        if ($link === false) {
            $link = IPS_CreateLink(); IPS_SetParent($link, $category); IPS_SetIdent($link, 'SVHSPVPageLink');
            IPS_SetName($link, $this->VersionedPresentationName('PV-Details')); IPS_SetLinkTargetID($link, $pv); IPS_SetHidden($link, false);
        } elseif (IPS_GetName($link) !== $this->VersionedPresentationName('PV-Details')) {
            IPS_SetName($link, $this->VersionedPresentationName('PV-Details'));
        }
    }
}
