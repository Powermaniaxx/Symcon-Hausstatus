<?php
declare(strict_types=1);

require_once __DIR__ . '/BufferSupport.php';

/** Read-only waterbed archive data: raw heating phases, daily means and energy intervals. */
trait HausstatusWaterbedHistorySupport
{
    use HausstatusBufferSupport;
    private function WaterbedPowerEvents(int $archive, int $id, int $from, int $until, int $maxPages = 5): array
    {
        $values = []; $cursor = $until; $limited = false;
        for ($page = 0; $page < $maxPages; $page++) {
            $rows = AC_GetLoggedValues($archive, $id, $from, $cursor, 10000);
            if (!is_array($rows)) { throw new RuntimeException('Leistungsarchiv nicht verfügbar.'); }
            $oldest = $cursor + 1;
            foreach ($rows as $row) {
                $t = (int)($row['TimeStamp'] ?? 0); $v = $row['Value'] ?? null;
                if ($t < $from || $t > $cursor) { continue; }
                $oldest = min($oldest, $t);
                $values[$t] = is_numeric($v) && is_finite((float)$v) && (float)$v >= 0 ? (float)$v : null;
            }
            if (count($rows) < 10000 || $oldest <= $from) { break; }
            if ($oldest > $cursor || $page === $maxPages - 1) { $limited = true; break; }
            $cursor = $oldest - 1;
        }
        // A change-only archive may legitimately have an unchanged value before this period.
        if (!$limited) {
            $before = AC_GetLoggedValues($archive, $id, 0, $from - 1, 1);
            if (is_array($before) && isset($before[0])) {
                $t = (int)($before[0]['TimeStamp'] ?? 0); $v = $before[0]['Value'] ?? null;
                if ($t > 0 && $t < $from) {
                    $values[$t] = is_numeric($v) && is_finite((float)$v) && (float)$v >= 0 ? (float)$v : null;
                }
            }
        }
        ksort($values, SORT_NUMERIC);
        $points = [];
        foreach ($values as $t => $v) { $points[] = ['t' => $t, 'v' => $v]; }
        return ['points' => $this->WaterbedPowerSlice($points, $from, $until), 'limited' => $limited];
    }

    private function WaterbedPowerSlice(array $points, int $from, int $until): array
    {
        $value = null;
        foreach ($points as $point) { if ($point['t'] <= $from) { $value = $point['v']; } else { break; } }
        $result = [['t' => $from, 'v' => $value]];
        foreach ($points as $point) {
            if ($point['t'] <= $from || $point['t'] >= $until) { continue; }
            // Only redundant equal readings are removed. Every on/off/level transition survives.
            if ($point['v'] !== $value) { $result[] = $point; $value = $point['v']; }
        }
        if ($until > $from) { $result[] = ['t' => $until, 'v' => $value]; }
        return $result;
    }

    private function WaterbedDailyPower(array $points, DateTimeImmutable $start, int $until): array
    {
        $index = 0; $value = null; $result = []; $cursor = $start;
        while ($index < count($points) && $points[$index]['t'] <= $start->getTimestamp()) { $value = $points[$index++]['v']; }
        while ($cursor->getTimestamp() < $until) {
            $a = $cursor->getTimestamp(); $next = $cursor->modify('+1 day');
            $b = min($until, $next->getTimestamp()); $position = $a; $sum = 0.0; $complete = true;
            while ($position < $b) {
                $end = $index < count($points) ? min($b, $points[$index]['t']) : $b;
                if ($end > $position) {
                    if ($value === null) { $complete = false; } else { $sum += $value * ($end - $position); }
                    $position = $end;
                }
                if ($index < count($points) && $points[$index]['t'] <= $position) { $value = $points[$index++]['v']; }
            }
            $result[] = ['t' => $a, 'v' => $complete ? round($sum / max(1, $b - $a), 3) : null];
            $cursor = $next;
        }
        return $result;
    }

    private function WaterbedChartHistory(): array
    {
        $archive = $this->WaterbedArchiveID();
        $ids = ['w' => 16905, 'kwh' => 17732,
            'sven' => $this->ConfigInteger('WaterbedSvenTemperature'),
            'susi' => $this->ConfigInteger('WaterbedSusiTemperature')];
        $now = time(); $zone = new DateTimeZone('Europe/Berlin');
        $today = (new DateTimeImmutable('@' . $now))->setTimezone($zone)->setTime(0, 0);
        $key = json_encode(['heating-phases-daily-v7', $archive, $ids, $this->ConfigString('WaterbedEnergyUnit'), $today->format('Y-m-d')]);
        $cache = json_decode($this->ReadLargeBuffer('WaterbedChartCache'), true);
        if (($cache['key'] ?? '') === $key && ($cache['until'] ?? 0) > $now) { return $cache['data']; }
        $ranges = ['day' => [$today, 0, '+1 day'], 'week' => [$today->modify('monday this week'), 1, '+1 week'],
            'month' => [$today->modify('first day of this month'), 1, 'first day of next month'],
            'year' => [$today->setDate((int)$today->format('Y'), 1, 1), 3, '+1 year'],
            'previousYear' => [$today->setDate((int)$today->format('Y') - 1, 1, 1), 3, '+1 year']];
        $recentFrom = min($ranges['week'][0]->getTimestamp(), $ranges['month'][0]->getTimestamp());
        $recentPower = null; $result = [];
        foreach ($ranges as $range => [$start, $level, $advance]) {
            $periodEnd = $start->modify($advance)->getTimestamp();
            $rangeEnd = min($now, $periodEnd); $queryEnd = min($now, $periodEnd - 1);
            $isYear = in_array($range, ['year', 'previousYear'], true);
            $series = []; $notes = []; $powerNote = '';
            foreach ($ids as $name => $id) {
                $series[$name] = [];
                try {
                    if ($archive <= 0 || !IPS_VariableExists($id) || !AC_GetLoggingStatus($archive, $id)) {
                        $notes[] = 'Archivdaten fehlen für ID ' . $id; continue;
                    }
                    if ($name === 'kwh' && AC_GetAggregationType($archive, $id) !== 1) {
                        $notes[] = 'Verbrauch ID 17732 benötigt im Archiv den Aggregationstyp Zähler.'; continue;
                    }
                    if ($name === 'w' && !$isYear) {
                        if ($recentPower === null) { $recentPower = $this->WaterbedPowerEvents($archive, $id, $recentFrom, $now); }
                        $series[$name] = $this->WaterbedPowerSlice($recentPower['points'], $start->getTimestamp(), $rangeEnd);
                        if ($recentPower['limited']) { $powerNote = 'Nur die jüngsten 50.000 Leistungsaufzeichnungen sind verfügbar; der ältere Verlauf bleibt gegebenenfalls leer.'; }
                        continue;
                    }
                    if ($name === 'w' && AC_GetAggregationType($archive, $id) !== 0) {
                        $raw = $this->WaterbedPowerEvents($archive, $id, $start->getTimestamp(), $queryEnd);
                        $series[$name] = $this->WaterbedDailyPower($raw['points'], $start, $rangeEnd);
                        if ($raw['limited']) { $powerNote = 'Ältere Tagesmittel bleiben leer, wenn das Limit von 50.000 Leistungsaufzeichnungen erreicht ist.'; }
                        continue;
                    }
                    $factor = $name === 'kwh' && $this->ConfigString('WaterbedEnergyUnit') === 'Wh' ? 0.001 : 1.0;
                    $aggregation = $name === 'w' && $isYear ? 1 : $level;
                    $rows = AC_GetAggregatedValues($archive, $id, $aggregation, $start->getTimestamp(), $queryEnd, 0);
                    foreach (is_array($rows) ? $rows : [] as $row) {
                        $value = $row['Avg'] ?? null; $timestamp = (int)($row['TimeStamp'] ?? 0);
                        if ($timestamp < $start->getTimestamp() || $timestamp > $queryEnd) { continue; }
                        $valid = is_numeric($value) && is_finite((float)$value)
                            && (!in_array($name, ['w', 'kwh'], true) || (float)$value >= 0);
                        $series[$name][] = ['t' => $timestamp, 'v' => $valid ? round((float)$value * $factor, 3) : null];
                    }
                    usort($series[$name], static fn(array $a, array $b): int => $a['t'] <=> $b['t']);
                } catch (Throwable $e) { $notes[] = 'Archivdaten für ID ' . $id . ' konnten nicht gelesen werden.'; }
            }
            // Table uses daily intervals even when the chart displays hourly or monthly energy.
            $dailyPower = $isYear ? $series['w'] : $this->WaterbedDailyPower($series['w'], $start, $rangeEnd);
            $dailyEnergy = $series['kwh'];
            if ($isYear || $range === 'day') {
                $dailyEnergy = [];
                try {
                    if ($archive > 0 && IPS_VariableExists($ids['kwh']) && AC_GetLoggingStatus($archive, $ids['kwh'])
                        && AC_GetAggregationType($archive, $ids['kwh']) === 1) {
                        $factor = $this->ConfigString('WaterbedEnergyUnit') === 'Wh' ? 0.001 : 1.0;
                        $rows = AC_GetAggregatedValues($archive, $ids['kwh'], 1, $start->getTimestamp(), $queryEnd, 0);
                        foreach (is_array($rows) ? $rows : [] as $row) {
                            $v = $row['Avg'] ?? null; $t = (int)($row['TimeStamp'] ?? 0);
                            if ($t >= $start->getTimestamp() && $t <= $queryEnd) {
                                $dailyEnergy[] = ['t' => $t, 'v' => is_numeric($v) && is_finite((float)$v) && (float)$v >= 0 ? round((float)$v * $factor, 3) : null];
                            }
                        }
                    }
                } catch (Throwable $e) { $notes[] = 'Tagesverbrauch konnte nicht gelesen werden.'; }
            }
            $powerByDay = array_column($dailyPower, 'v', 't'); $energyByDay = array_column($dailyEnergy, 'v', 't'); $daily = [];
            for ($day = $start; $day->getTimestamp() < $rangeEnd; $day = $day->modify('+1 day')) {
                $timestamp = $day->getTimestamp();
                $daily[] = ['t' => $timestamp, 'w' => $powerByDay[$timestamp] ?? null,
                    'kwh' => $energyByDay[$timestamp] ?? null, 'partial' => $day->modify('+1 day')->getTimestamp() > $rangeEnd];
            }
            $result[$range] = ['start' => $start->getTimestamp(), 'end' => $rangeEnd, 'periodEnd' => $periodEnd, 'daily' => $daily,
                'powerMode' => $isYear ? 'dailyMean' : 'steps', 'powerNote' => $powerNote,
                'series' => $series, 'note' => implode(' ', array_unique($notes))];
        }
        $this->WriteLargeBuffer('WaterbedChartCache', json_encode(['key' => $key, 'until' => $now + 300, 'data' => $result], JSON_THROW_ON_ERROR));
        return $result;
    }
}
