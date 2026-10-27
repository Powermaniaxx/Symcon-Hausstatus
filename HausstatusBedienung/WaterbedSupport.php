<?php
declare(strict_types=1);

/**
 * Schlafzimmer: Wasserbett und lesbarer Stromverbrauchsverlauf.
 * Keine eigenen Schaltwege: Bedienung nur über vorhandene Symcon-Aktionen.
 * Historische Tageswerte stammen aus dem archivierten Gesamtzaehler.
 */
require_once __DIR__ . '/WaterbedHistorySupport.php';

trait HausstatusWaterbedSupport
{
    use HausstatusWaterbedHistorySupport;
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

    /**
     * Current year vs. previous year, by calendar month in Europe/Berlin.
     * Uses counter archive monthly consumption, including resets handled by the archive.
     * A missing month yields null, not an invented zero.
     */
    private function WaterbedYearComparison(int $archive, int $totalID, int $now): array
    {
        $zone = new DateTimeZone('Europe/Berlin');
        $localNow = (new DateTimeImmutable('@' . $now))->setTimezone($zone);
        $year = (int)$localNow->format('Y');
        $previousYear = $year - 1;
        $unit = $this->ConfigString('WaterbedEnergyUnit');
        $factor = $unit === 'Wh' ? 0.001 : 1.0;

        $cached = json_decode($this->GetBuffer('WaterbedYearComparisonCache'), true);
        if (is_array($cached)
            && ($cached['until'] ?? 0) > $now
            && ($cached['year'] ?? 0) === $year
            && ($cached['archive'] ?? 0) === $archive
            && ($cached['source'] ?? 0) === $totalID
            && ($cached['unit'] ?? '') === $unit
            && isset($cached['result']) && is_array($cached['result'])) {
            return $cached['result'];
        }

        $valuesByYear = []; $allMonthsComplete = [];
        if (AC_GetAggregationType($archive, $totalID) !== 1) {
            return ['currentYear'=>$year,'previousYear'=>$previousYear,'months'=>[],
                'currentTotal'=>null,'previousTotal'=>null,
                'note'=>'Verbrauch ID 17732 benötigt im Archiv den Aggregationstyp Zähler.'];
        }
        foreach ([$previousYear, $year] as $whichYear) {
            $valuesByYear[$whichYear] = array_fill(1, 12, null);
            $start = new DateTimeImmutable($whichYear . '-01-01 00:00:00', $zone);
            $end = min($now, $start->modify('+1 year')->getTimestamp() - 1);
            $rows = AC_GetAggregatedValues($archive, $totalID, 3, $start->getTimestamp(), $end, 0);
            foreach (is_array($rows) ? $rows : [] as $row) {
                $timestamp = (int)($row['TimeStamp'] ?? 0);
                if ($timestamp < $start->getTimestamp() || $timestamp > $end) { continue; }
                $month = (int)(new DateTimeImmutable('@' . $timestamp))->setTimezone($zone)->format('n');
                $value = $row['Avg'] ?? null;
                if (is_numeric($value) && is_finite((float)$value) && (float)$value >= 0) {
                    $valuesByYear[$whichYear][$month] = round((float)$value * $factor, 3);
                }
            }
            $lastMonth = $whichYear === $year ? (int)$localNow->format('n') : 12;
            $allMonthsComplete[$whichYear] = !in_array(null, array_slice($valuesByYear[$whichYear], 0, $lastMonth), true);
        }

        $names = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $months = [];
        for ($month = 1; $month <= 12; $month++) {
            $months[] = [
                'name' => $names[$month - 1],
                'current' => $valuesByYear[$year][$month],
                'previous' => $valuesByYear[$previousYear][$month],
                'partial' => $month === (int)$localNow->format('n')
            ];
        }

        $result = [
            'currentYear' => $year,
            'previousYear' => $previousYear,
            'currentTotal' => $allMonthsComplete[$year] ? round(array_sum($valuesByYear[$year]), 3) : null,
            'previousTotal' => $allMonthsComplete[$previousYear] ? round(array_sum($valuesByYear[$previousYear]), 3) : null,
            'months' => $months,
            'note' => 'Das aktuelle Jahr und der laufende Monat sind noch nicht abgeschlossen. Einzelne Monatsverbräuche aus Energiezähler ID 17732; Jahressummen sind die Summe dieser Monate. Fehlende Monate bleiben leer; bei Datenlücken wird keine vollständige Jahressumme ausgewiesen.'
        ];

        $this->SetBuffer('WaterbedYearComparisonCache', json_encode([
            'until' => $now + 1800, 'archive' => $archive, 'source' => $totalID,
            'unit' => $unit, 'year' => $year, 'result' => $result
        ], JSON_THROW_ON_ERROR));
        return $result;
    }

    private function WaterbedEnergyState(): ?array
    {
        if (!$this->WaterbedVisible()) { return null; }
        $values = [];
        foreach (['power'=>'WaterbedPower','today'=>'WaterbedToday','week'=>'WaterbedWeek','month'=>'WaterbedMonth','total'=>'WaterbedTotal'] as $name=>$property) {
            $id = $name === 'power' ? 16905 : $this->ConfigInteger($property);
            $values[$name] = $id > 0 && IPS_VariableExists($id) ? $this->Read($id) : ['raw'=>null,'text'=>'Nicht eingerichtet'];
        }
        $key = json_encode([$this->ConfigInteger('WaterbedArchive'), $this->ConfigString('WaterbedEnergyUnit'),
            $this->ConfigInteger('WaterbedSvenTemperature'), $this->ConfigInteger('WaterbedSusiTemperature'),
            (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d')]);
        $cached = json_decode($this->GetBuffer('WaterbedPageCache'), true);
        if (($cached['key'] ?? null) === $key && ($cached['until'] ?? 0) > time() && is_array($cached['data'] ?? null)) {
            return array_replace($cached['data'], ['values'=>$values]);
        }
        $result = ['values'=>$values,'days'=>[],'annual'=>null,'history'=>null,'note'=>'','historyLoaded'=>false];
        // Opening a room must never synchronously read months of archive data.
        // The visible waterbed panel requests its history separately through PageRead.
        if (!($this->pageContext['waterbedHistory'] ?? false)) { return $result; }
        try {
            $result = $this->BuildWaterbedEnergyState();
            $result['historyLoaded'] = true;
            $this->SetBuffer('WaterbedPageCache', json_encode(['key'=>$key,'until'=>time()+300,'data'=>$result], JSON_THROW_ON_ERROR));
        } catch (Throwable $e) {
            $result = ['values'=>$values,'days'=>[],'annual'=>null,'history'=>null,'historyLoaded'=>false,
                'historyError'=>'Wasserbett-Verlauf konnte nicht geladen werden: '.$e->getMessage()];
            $this->SendDebug('Wasserbett-Verlauf', $e->getMessage(), 0);
        }
        return $result;
    }

    private function BuildWaterbedEnergyState(): ?array
    {
        if (!$this->WaterbedVisible()) { return null; }
        $sources = [];
        foreach ([
            'power' => 'WaterbedPower', 'today' => 'WaterbedToday',
            'week' => 'WaterbedWeek', 'month' => 'WaterbedMonth',
            'total' => 'WaterbedTotal'
        ] as $key => $property) {
            $id = $key === 'power' ? 16905 : $this->ConfigInteger($property);
            $sources[$key] = $id > 0 && IPS_VariableExists($id)
                ? $this->Read($id) : ['raw' => null, 'text' => 'Nicht eingerichtet'];
        }
        $result = ['values' => $sources, 'days' => [], 'annual' => null, 'note' => '',
            'history' => $this->WaterbedChartHistory()];
        $archive = $this->WaterbedArchiveID();
        $totalID = 17732;
        if ($archive <= 0 || $totalID <= 0 || !IPS_VariableExists($totalID)) {
            $result['note'] = 'Für den Tagesverlauf muss der Gesamtverbrauchszähler im Symcon-Archiv verfügbar sein.';
            return $result;
        }
        try {
            if (!AC_GetLoggingStatus($archive, $totalID)) {
                $result['note'] = 'Archivierung für den Gesamtverbrauchszähler (ID ' . $totalID . ') aktivieren. Vergangene Werte lassen sich nicht nachträglich herstellen.';
                return $result;
            }
            $now = time();
            try {
                $result['annual'] = $this->WaterbedYearComparison($archive, $totalID, $now);
            } catch (Throwable $e) {
                $this->SendDebug('Wasserbett Jahresverlauf', $e->getMessage(), 0);
            }
            $zone = new DateTimeZone('Europe/Berlin');
            $today = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
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


