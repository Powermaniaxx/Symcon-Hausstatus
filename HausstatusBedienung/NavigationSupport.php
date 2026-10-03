<?php
declare(strict_types=1);

trait HausstatusNavigationSupport
{
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
        $pv = $this->PVDetailsTarget();
        if ($pv > 0) {
            $link = @IPS_GetObjectIDByIdent('SVHSTileLink_7', IPS_GetParent($this->InstanceID));
            if ($link !== false && IPS_LinkExists($link) && IPS_GetLink($link)['TargetID'] === $pv) {
                // The full PV view remains active and opens from the compact power card.
                IPS_SetHidden($link, true);
            }
        }
        $rooms = $this->RoomNavigationTarget();
        if ($rooms > 0 && in_array(IPS_GetName($rooms), ['Räume und Geräte', 'Raumsteuerung'], true)) {
            IPS_SetName($rooms, 'Räume');
        }
    }
}
