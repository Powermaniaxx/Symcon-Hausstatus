<?php
declare(strict_types=1);

trait HausstatusNetworkSupport
{
    private function WirelessNetworks(): array
    {
        $rows = json_decode($this->ConfigString('WirelessNetworks'), true);
        $result = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (!is_array($row) || !is_string($row['Name'] ?? null)) { continue; }
            $item = ['Name' => $row['Name']];
            foreach (['State', 'SSID', 'Devices', 'QRCode'] as $key) {
                $item[$key] = is_int($row[$key] ?? null) && $row[$key] >= 0 ? $row[$key] : 0;
            }
            $result[] = $item;
        }
        return $result;
    }

    private function WirelessQRCode(int $id): array
    {
        if ($id <= 0) { return ['image' => '', 'note' => 'Kein QR-Code ausgewählt.']; }
        try {
            if (!IPS_MediaExists($id) || IPS_GetMedia($id)['MediaType'] !== 1) {
                throw new RuntimeException('Ein vorhandenes Bild-Medienobjekt auswählen.');
            }
            $encoded = @IPS_GetMediaContent($id);
            if (strlen($encoded) > 1400000) { throw new RuntimeException('Das QR-Code-Bild ist zu groß.'); }
            $bytes = base64_decode($encoded, true);
            $info = $bytes !== false ? @getimagesizefromstring($bytes) : false;
            if ($info === false || !in_array($info['mime'] ?? '', ['image/png', 'image/jpeg'], true)) {
                throw new RuntimeException('Der QR-Code muss ein PNG- oder JPEG-Bild sein.');
            }
            return ['image' => 'data:' . $info['mime'] . ';base64,' . base64_encode($bytes), 'note' => ''];
        } catch (Throwable $e) { return ['image' => '', 'note' => $e->getMessage()]; }
    }

    // Optional, one-time mapping of the user's known FritzBox object tree.
    // Safeguards: no existing selection is replaced; every candidate must have
    // the expected name, variable type and parent instance.
    public function AssignKnownFritzBoxSources(): string
    {
        $owner = (int)$this->ReadPropertyInteger('ConfigSource');
        if ($owner === 0) { $owner = $this->InstanceID; }
        if (!IPS_InstanceExists($owner)
            || IPS_GetInstance($owner)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
            || (int)IPS_GetProperty($owner, 'ConfigSource') !== 0) {
            return 'Bitte die zentrale Hausstatus-Instanz als gemeinsame Konfiguration auswählen.';
        }
        // Field => [variable id, expected parent, Symcon name, type]
        $mapping = [
            'NetworkConnection'     => [25185, 58215, 'Status der physischen Verbindung', 3],
            'NetworkDownload'       => [10835, 58215, 'Empfangsrate', 2],
            'NetworkUpload'         => [19406, 58215, 'Senderate', 2],
            'NetworkDownloadUsage'  => [23405, 58215, 'Auslastung Download', 2],
            'NetworkUploadUsage'    => [33426, 58215, 'Auslastung Upload', 2],
            'NetworkActiveDevices'  => [30908, 16705, 'Anzahl der aktiven Netzwerkgeräte', 1],
            'NetworkDevices'        => [15438, 16705, 'Anzahl der Netzwerkgeräte', 1],
            'NetworkUptime'         => [29894, 35157, 'Laufzeit', 3],
            'NetworkModel'          => [55331, 35157, 'Modell', 3],
            'NetworkFirmware'       => [53729, 35157, 'Software-Version', 3],
            'NetworkManufacturer'   => [24045, 35157, 'Hersteller', 3],
            'NetworkWanType'        => [16657, 58215, 'WAN Zugangsart', 3],
            'NetworkUPnP'           => [44023, 58215, 'Automatische Portweiterleitung per UPnP erlauben', 0],
            'NetworkDNS1'           => [13318, 58215, 'DNS-Server 1', 3],
            'NetworkDNS2'           => [26834, 58215, 'DNS-Server 2', 3],
            'NetworkDownstreamMax'  => [26792, 58215, 'Downstream Max kBitrate', 1],
            'NetworkUpstreamMax'    => [12922, 58215, 'Upstream Max kBitrate', 1],
            'NetworkWirelessState'  => [40818, 27744, 'WLAN state', 0],
            'NetworkLastRestart'    => [30468, 35157, 'Letzter Neustart', 3],
            'NetworkTotalReceived'   => [51481, 58215, 'Empfangen seit verbunden', 2],
            'NetworkTotalSent'       => [11621, 58215, 'Gesendet seit verbunden', 2],
            'NetworkVoipDNS1'        => [50614, 58215, 'VoIP DNS-Server 1', 3],
            'NetworkVoipDNS2'        => [14914, 58215, 'VoIP DNS-Server 2', 3]
        ];

        $assigned = 0;
        $skipped = 0;
        foreach ($mapping as $property => [$id, $parent, $name, $type]) {
            if ((int)IPS_GetProperty($owner, $property) > 0) {
                $skipped++;
                continue;
            }
            if (!IPS_VariableExists($id) || IPS_GetParent($id) !== $parent
                || IPS_GetName($id) !== $name
                || (int)IPS_GetVariable($id)['VariableType'] !== $type) {
                $skipped++;
                continue;
            }
            IPS_SetProperty($owner, $property, $id);
            $assigned++;
        }
        if ($assigned > 0) {
            IPS_ApplyChanges($owner);
        }
        return $assigned . ' FritzBox-Variablen zugeordnet; ' . $skipped
            . ' bereits vergeben oder nicht eindeutig erkannt. '
            . 'Die vorhandenen Zuordnungen wurden nicht verändert.';
    }

    // Show only relevant sources for each FritzBox tile variant.
    private function NetworkViewFields(int $view): array
    {
        $compact = ['NetworkConnection', 'NetworkDownload', 'NetworkUpload', 'NetworkActiveDevices'];
        $internet = ['NetworkConnection', 'NetworkDownload', 'NetworkUpload',
            'NetworkDownloadUsage', 'NetworkUploadUsage', 'NetworkWanType',
            'NetworkDownstreamMax', 'NetworkUpstreamMax', 'NetworkUPnP',
            'NetworkDNS1', 'NetworkDNS2', 'NetworkTotalReceived', 'NetworkTotalSent',
            'NetworkVoipDNS1', 'NetworkVoipDNS2'];
        $router = ['NetworkManufacturer', 'NetworkModel', 'NetworkFirmware',
            'NetworkUptime', 'NetworkLastRestart', 'NetworkWanType'];
        $devices = ['NetworkActiveDevices', 'NetworkDevices', 'NetworkWirelessState'];
        $legacy = ['NetworkConnection', 'NetworkDownload', 'NetworkUpload',
            'NetworkDownloadUsage', 'NetworkUploadUsage', 'NetworkActiveDevices',
            'NetworkDevices', 'NetworkUptime', 'NetworkModel', 'NetworkFirmware'];
        return match ($view) {
            13 => $legacy,
            20 => $compact,
            21 => $internet,
            22 => $router,
            23 => $devices,
            24 => array_values(array_unique(array_merge($internet, $router, $devices))),
            default => []
        };
    }

    private function NetworkState(): ?array
    {
        $view = $this->CurrentView();
        if (!in_array($view, [13, 20, 21, 22, 23, 24], true)) { return null; }

        $values = [];
        foreach ($this->NetworkViewFields($view) as $key) {
            $id = $this->ConfigInteger($key);
            $values[$key] = $id > 0 ? $this->Read($id)
                : ['raw' => null, 'text' => 'Nicht eingerichtet'];
        }

        // Keep the original detail page backwards-compatible.
        $metrics = [];
        if ($view === 13) {
            foreach (['NetworkConnection' => 'Verbindung', 'NetworkDownload' => 'Download',
                'NetworkUpload' => 'Upload', 'NetworkDownloadUsage' => 'Download-Auslastung',
                'NetworkUploadUsage' => 'Upload-Auslastung', 'NetworkActiveDevices' => 'Aktive Geräte',
                'NetworkDevices' => 'Geräte insgesamt', 'NetworkUptime' => 'Laufzeit',
                'NetworkModel' => 'Modell', 'NetworkFirmware' => 'Software-Version'] as $key => $name) {
                if ($this->ConfigInteger($key) > 0) {
                    $metrics[] = ['name' => $name, 'value' => $values[$key]['text']];
                }
            }
        }

        $wifi = [];
        if (in_array($view, [13, 24], true)) {
            foreach ($this->WirelessNetworks() as $row) {
                $wifi[] = ['name' => $row['Name'], 'state' => $this->Read($row['State']),
                    'ssid' => $this->Read($row['SSID']), 'devices' => $this->Read($row['Devices']),
                    'qr' => $this->WirelessQRCode($row['QRCode'])];
            }
        }
        return ['metrics' => $metrics, 'values' => $values, 'wifi' => $wifi];
    }
}
