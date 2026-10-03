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

    private function NetworkState(): ?array
    {
        if ($this->ReadPropertyInteger('View') !== 13) { return null; }
        $metrics = [];
        foreach (['NetworkConnection' => 'Verbindung', 'NetworkDownload' => 'Download', 'NetworkUpload' => 'Upload',
            'NetworkDownloadUsage' => 'Download-Auslastung', 'NetworkUploadUsage' => 'Upload-Auslastung',
            'NetworkActiveDevices' => 'Aktive Geräte', 'NetworkDevices' => 'Geräte insgesamt',
            'NetworkUptime' => 'Laufzeit', 'NetworkModel' => 'Modell', 'NetworkFirmware' => 'Software-Version'] as $key => $name) {
            if ($this->ConfigInteger($key) > 0) { $metrics[] = ['name' => $name, 'value' => $this->Read($this->ConfigInteger($key))['text']]; }
        }
        $wifi = [];
        foreach ($this->WirelessNetworks() as $row) {
            $wifi[] = ['name' => $row['Name'], 'state' => $this->Read($row['State']), 'ssid' => $this->Read($row['SSID']),
                'devices' => $this->Read($row['Devices']), 'qr' => $this->WirelessQRCode($row['QRCode'])];
        }
        return ['metrics' => $metrics, 'wifi' => $wifi];
    }
}
