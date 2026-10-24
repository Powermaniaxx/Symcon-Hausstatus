<?php
declare(strict_types=1);

/** Read-only archive series. Never changes logging or device actions. */
trait HausstatusWaterbedHistorySupport
{
    /** Time-weighted watts from raw readings, independent of counter aggregation. */
    private function WaterbedRawPower(int $archive, int $id, DateTimeImmutable $start, int $now, int $level): array
    {
        $from = $start->getTimestamp();
        $rows = AC_GetLoggedValues($archive, $id, $from, $now, 10000);
        $rows = is_array($rows) ? $rows : [];
        $limited = count($rows) >= 10000;
        $baseline = $limited ? [] : AC_GetLoggedValues($archive, $id, max(1, $from - 172800), $from - 1, 1);
        $points = [];
        foreach (array_merge(is_array($baseline) ? $baseline : [], $rows) as $row) {
            $t = (int)($row['TimeStamp'] ?? 0); $v = $row['Value'] ?? null;
            if ($t > 0 && $t <= $now) {
                $points[$t] = is_numeric($v) && is_finite((float)$v) && (float)$v >= 0 ? (float)$v : null;
            }
        }
        ksort($points, SORT_NUMERIC);
        $times = array_keys($points); $index = 0; $value = null; $result = [];
        while ($index < count($times) && $times[$index] <= $from) { $value = $points[$times[$index++]]; }
        $cursor = $start;
        while ($cursor->getTimestamp() < $now) {
            $a = $cursor->getTimestamp();
            $next = $level === 0 ? $cursor->setTimestamp($a + 3600)
                : $cursor->modify($level === 3 ? 'first day of next month' : '+1 day');
            $b = min($now, $next->getTimestamp()); $position = $a; $sum = 0.0; $complete = true;
            while ($position < $b) {
                $end = $index < count($times) ? min($b, $times[$index]) : $b;
                if ($end > $position) {
                    if ($value === null) { $complete = false; }
                    else { $sum += $value * ($end - $position); }
                    $position = $end;
                }
                if ($index < count($times) && $times[$index] <= $position) { $value = $points[$times[$index++]]; }
            }
            $result[] = ['t' => $a, 'v' => $complete ? round($sum / max(1, $b - $a), 3) : null];
            $cursor = $next;
        }
        return ['points' => $result, 'limited' => $limited];
    }

    private function WaterbedChartHistory(): array
    {
        $archive = $this->WaterbedArchiveID();
        $ids = ['w' => 16905, 'kwh' => 17732,
            'sven' => $this->ConfigInteger('WaterbedSvenTemperature'),
            'susi' => $this->ConfigInteger('WaterbedSusiTemperature')];
        $now = time();
        $key = json_encode(['raw-power-v3', $archive, $ids, $this->ConfigString('WaterbedEnergyUnit')]);
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
                        $raw = $this->WaterbedRawPower($archive, $id, $start, $now, $level);
                        $series[$name] = $raw['points'];
                        $notes[] = 'Leistungsmittelwerte aus Rohdaten; Zähler-Summen werden nicht als Watt verwendet.';
                        if ($raw['limited']) { $notes[] = 'Rohdatenlimit erreicht; unvollständige ältere Leistungsintervalle bleiben leer.'; }
                        continue;
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
