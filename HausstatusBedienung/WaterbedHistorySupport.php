<?php
declare(strict_types=1);

/** Read-only archive series. Never changes logging or device actions. */
trait HausstatusWaterbedHistorySupport
{
    private function WaterbedChartHistory(): array
    {
        $archive = $this->WaterbedArchiveID();
        $ids = ['w' => 16905, 'kwh' => 17732,
            'sven' => $this->ConfigInteger('WaterbedSvenTemperature'),
            'susi' => $this->ConfigInteger('WaterbedSusiTemperature')];
        $now = time();
        $key = json_encode(['standard-power-v2', $archive, $ids, $this->ConfigString('WaterbedEnergyUnit')]);
        $cache = json_decode($this->GetBuffer('WaterbedChartCache'), true);
        if (($cache['key'] ?? '') === $key && ($cache['until'] ?? 0) > $now) { return $cache['data']; }
        $local = (new DateTimeImmutable('@' . $now))->setTimezone(new DateTimeZone('Europe/Berlin'));
        $today = $local->setTime(0, 0);
        $ranges = ['day' => [$today, 0], 'week' => [$today->modify('monday this week'), 1],
            'month' => [$today->modify('first day of this month'), 1],
            'year' => [$today->setDate((int)$local->format('Y'), 1, 1), 3]];
        $result = [];
        foreach ($ranges as $range => [$start, $level]) {
            $series = []; $notes = [];
            foreach ($ids as $name => $id) {
                $series[$name] = [];
                try {
                    if ($archive <= 0 || !IPS_VariableExists($id) || !AC_GetLoggingStatus($archive, $id)) {
                        $notes[] = 'Archivdaten fehlen für ID ' . $id; continue;
                    }
                    if ($name === 'kwh' && AC_GetAggregationType($archive, $id) !== 1) {
                        $notes[] = 'Verbrauch ID 17732 benötigt im Archiv den Aggregationstyp Zähler.'; continue;
                    }
                    if ($name === 'w' && AC_GetAggregationType($archive, $id) !== 0) {
                        $notes[] = 'Leistung ID 16905 benötigt im Archiv den Aggregationstyp Standard (Mittelwert), nicht Zähler. Danach die Archivwerte neu aggregieren.'; continue;
                    }
                    $factor = $name === 'kwh' && $this->ConfigString('WaterbedEnergyUnit') === 'Wh' ? 0.001 : 1.0;
                    $rows = AC_GetAggregatedValues($archive, $id, $level, $start->getTimestamp(), $now, 0);
                    foreach (is_array($rows) ? $rows : [] as $row) {
                        $value = $row['Avg'] ?? null; $timestamp = (int)($row['TimeStamp'] ?? 0);
                        if ($timestamp < $start->getTimestamp() || $timestamp > $now) { continue; }
                        $valid = is_numeric($value) && is_finite((float)$value)
                            && ($name !== 'kwh' || (float)$value >= 0);
                        $series[$name][] = ['t' => $timestamp, 'v' => $valid ? round((float)$value * $factor, 3) : null];
                    }
                    usort($series[$name], static fn(array $a, array $b): int => $a['t'] <=> $b['t']);
                } catch (Throwable $e) { $notes[] = 'Archivdaten für ID ' . $id . ' konnten nicht gelesen werden.'; }
            }
            $result[$range] = ['start' => $start->getTimestamp(), 'end' => $now,
                'series' => $series, 'note' => implode(' ', array_unique($notes))];
        }
        $this->SetBuffer('WaterbedChartCache', json_encode(['key' => $key, 'until' => $now + 300, 'data' => $result], JSON_THROW_ON_ERROR));
        return $result;
    }
}
