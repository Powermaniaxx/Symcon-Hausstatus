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

    // Show only relevant sources for each FritzBox tile variant.
    private function NetworkViewFields(int $view): array
    {
        $compact = ['NetworkConnection', 'NetworkDownload', 'NetworkUpload', 'NetworkActiveDevices'];
        $internet = ['NetworkConnection', 'NetworkDownload', 'NetworkUpload',
            'NetworkDownloadUsage', 'NetworkUploadUsage', 'NetworkWanType',
            'NetworkDownstreamMax', 'NetworkUpstreamMax', 'NetworkUPnP',
            'NetworkDNS1', 'NetworkDNS2'];
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
