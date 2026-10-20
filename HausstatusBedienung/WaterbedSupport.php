<?php
declare(strict_types=1);

/**
 * Schlafzimmer: Wasserbett und lesbarer Stromverbrauchsverlauf.
 * Keine eigenen Schaltwege: Bedienung nur über vorhandene Symcon-Aktionen.
 * Historische Tageswerte stammen aus dem archivierten Gesamtzaehler.
 */
trait HausstatusWaterbedSupport
{
    private const WATERBED_SOURCES = [
        'WaterbedPowerSwitch' => [14711, 'Ein/Aus', true, 'Wasserbett'],
        'WaterbedSvenTemperature' => [30213, 'Temperatur Sven', true, 'Sven'],
        'WaterbedSusiTemperature' => [43240, 'Temperatur Susi', true, 'Susi'],
        'WaterbedHoliday' => [19840, 'Urlaubsmodus', true, 'Wasserbett'],
        'WaterbedPower' => [33520, 'Aktuelle Leistung', false, 'Stromverbrauch'],
        'WaterbedToday' => [41104, 'Heute', false, 'Stromverbrauch'],
        'WaterbedWeek' => [39312, 'Diese Woche', false, 'Stromverbrauch'],
        'WaterbedMonth' => [13879, 'Dieser Monat', false, 'Stromverbrauch'],
        'WaterbedTotal' => [23622, 'Gesamt', false, 'Stromverbrauch']
    ];

    private function CreateWaterbed(): void
    {
        foreach (self::WATERBED_SOURCES as $property => [$id]) {
            $this->RegisterPropertyInteger($property, $id);
        }
        $this->RegisterPropertyInteger('WaterbedArchive', 0);
        $this->RegisterPropertyString('WaterbedEnergyUnit', 'kWh');
    }

    private function WaterbedVisible(): bool
    {
        return $this->CurrentView() === 12
            && mb_strtolower(trim($this->CurrentRoom()), 'UTF-8') === 'schlafzimmer';
    }

    private function WaterbedRoomEntries(): array
    {
        if (!$this->WaterbedVisible()) { return []; }
        $result = [];
        foreach (self::WATERBED_SOURCES as $property => [$default, $label, $operate, $device]) {
            // Energy data are rendered as a dedicated table, not duplicated as room switches.
            if (!$operate) { continue; }
            $id = $this->ConfigInteger($property);
            if ($id <= 0 || !IPS_VariableExists($id)) { continue; }
            $result[] = [
                'room' => 'Schlafzimmer', 'group' => 'Wasserbett',
                'name' => $label, 'id' => $id, 'type' => 2,
                'operate' => true, 'deviceKey' => 'waterbed:' . $device,
                'deviceName' => $device
            ];
        }
        return $result;
    }

    private function WaterbedArchiveID(): int
    {
        $configured = $this->ConfigInteger('WaterbedArchive');
        if ($configured > 0) { return IPS_InstanceExists($configured) ? $configured : 0; }
        $archives = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($archives) === 1 ? (int)$archives[0] : 0;
    }

    private function WaterbedEnergyState(): ?array
    {
        if (!$this->WaterbedVisible()) { return null; }
        $sources = [];
        foreach ([
            'power' => 'WaterbedPower', 'today' => 'WaterbedToday',
            'week' => 'WaterbedWeek', 'month' => 'WaterbedMonth',
            'total' => 'WaterbedTotal'
        ] as $key => $property) {
            $id = $this->ConfigInteger($property);
            $sources[$key] = $id > 0 && IPS_VariableExists($id)
                ? $this->Read($id) : ['raw' => null, 'text' => 'Nicht eingerichtet'];
        }
        $result = ['values' => $sources, 'days' => [], 'note' => ''];
        $archive = $this->WaterbedArchiveID();
        $totalID = $this->ConfigInteger('WaterbedTotal');
        if ($archive <= 0 || $totalID <= 0 || !IPS_VariableExists($totalID)) {
            $result['note'] = 'Für den Tagesverlauf muss der Gesamtverbrauchszähler im Symcon-Archiv verfügbar sein.';
            return $result;
        }
        try {
            if (!AC_GetLoggingStatus($archive, $totalID)) {
                $result['note'] = 'Archivierung für den Gesamtverbrauchszähler (ID ' . $totalID . ') aktivieren. Vergangene Werte lassen sich nicht nachträglich herstellen.';
                return $result;
            }
            $zone = new DateTimeZone('Europe/Berlin');
            $today = (new DateTimeImmutable('now', $zone))->setTime(0, 0);
            $now = time();
            $cache = json_decode($this->GetBuffer('WaterbedEnergyHistoryCache'), true);
            if (is_array($cache) && ($cache['until'] ?? 0) > $now
                && ($cache['today'] ?? 0) === $today->getTimestamp()
                && ($cache['source'] ?? 0) === $totalID && ($cache['archive'] ?? 0) === $archive
                && ($cache['unit'] ?? '') === $this->ConfigString('WaterbedEnergyUnit')) {
                $result['days'] = $cache['days'] ?? [];
                $result['note'] = $cache['note'] ?? '';
                return $result;
            }
            $unit = $this->ConfigString('WaterbedEnergyUnit');
            $factor = $unit === 'Wh' ? 0.001 : 1.0;
            $boundaries = [];
            for ($ago = 8; $ago >= 0; $ago--) {
                $boundary = $today->modify('-' . $ago . ' days')->getTimestamp();
                // One archived reading before each local day boundary; DST is handled by DateTime.
                $values = AC_GetLoggedValues($archive, $totalID, 0, $boundary - 1, 1);
                $reading = $values[0] ?? null;
                $time = (int)($reading['TimeStamp'] ?? 0);
                $raw = $reading['Value'] ?? null;
                // Stale readings are not treated as measured consumption.
                $boundaries[$ago] = $time > 0 && $boundary - $time <= 129600
                    && is_numeric($raw) && is_finite((float)$raw)
                    ? (float)$raw : null;
            }
            $days = [];
            for ($ago = 7; $ago >= 1; $ago--) {
                $start = $boundaries[$ago];
                $end = $boundaries[$ago - 1];
                $amount = $start === null || $end === null ? null : ($end - $start) * $factor;
                if ($amount !== null && ($amount < -0.00001 || !is_finite($amount))) { $amount = null; }
                $days[] = [
                    'date' => $today->modify('-' . $ago . ' days')->format('d.m.Y'),
                    'kwh' => $amount === null ? null : round(max(0.0, $amount), 3)
                ];
            }
            $days = array_reverse($days); // Yesterday first.
            $note = 'Abgeschlossene Tage, aus archivierten Zählerständen berechnet. Fehlende Tage bleiben leer.';
            if (!array_filter($days, static fn(array $day): bool => $day['kwh'] !== null)) {
                $note = 'Noch nicht genug archivierte Zählerstände für Tageswerte. Mindestens zwei Tagesgrenzen abwarten.';
            }
            $this->SetBuffer('WaterbedEnergyHistoryCache', json_encode([
                'until' => $now + 900, 'today' => $today->getTimestamp(),
                'source' => $totalID, 'archive' => $archive, 'unit' => $unit,
                'days' => $days, 'note' => $note
            ], JSON_THROW_ON_ERROR));
            $result['days'] = $days;
            $result['note'] = $note;
        } catch (Throwable $e) {
            $result['note'] = 'Stromverlauf konnte nicht aus dem Archiv gelesen werden: ' . $e->getMessage();
        }
        return $result;
    }
}
