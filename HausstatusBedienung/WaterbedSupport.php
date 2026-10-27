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
        // Same direct state response as the original room page. No second request,
        // page-context flag or duplicated page cache is needed to display the chart.
        $result = ['values'=>$values,'history'=>null,'annual'=>null,'days'=>[],'note'=>''];
        try { $result['history'] = $this->WaterbedChartHistory(); }
        catch (Throwable $e) {
            $result['note'] = 'Wasserbett-Verlauf: '.$e->getMessage();
            $this->SendDebug('Wasserbett-Verlauf', $e->getMessage(), 0);
        }
        try {
            $archive = $this->WaterbedArchiveID();
            if ($archive > 0 && IPS_VariableExists(17732) && AC_GetLoggingStatus($archive,17732)) {
                $result['annual'] = $this->WaterbedYearComparison($archive,17732,time());
            }
        } catch (Throwable $e) {
            $result['note'] .= ($result['note'] === '' ? '' : ' ').'Jahresvergleich: '.$e->getMessage();
            $this->SendDebug('Wasserbett-Jahresvergleich', $e->getMessage(), 0);
        }
        return $result;
    }
}
