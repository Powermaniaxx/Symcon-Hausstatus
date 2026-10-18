<?php
declare(strict_types=1);

trait HausstatusLaundrySupport
{
    private function HasLaundryView(): bool
    {
        return in_array($this->CurrentView(), [0, 27], true);
    }

    private function LaundrySources(): array
    {
        $rows = [];
        foreach (['Washer' => ['Waschmaschine', 'WaschmaschineLaeuft'], 'Dryer' => ['Trockner', 'TrocknerLaeuft']] as $key => [$name, $ident]) {
            $power = $this->ConfigInteger($key . 'Power');
            $running = $this->ConfigInteger($key . 'Running');
            $note = '';
            if ($running === 0) {
                $matches = [];
                // Require the exact ident AND an active power event on the same script.
                // Copies, disabled scripts and ambiguous matches must not supply a false status.
                foreach (IPS_GetScriptList() as $script) {
                    $candidate = @IPS_GetObjectIDByIdent($ident, $script);
                    if ($candidate === false || !IPS_VariableExists($candidate)
                        || IPS_GetVariable($candidate)['VariableType'] !== 0) { continue; }
                    foreach (IPS_GetChildrenIDs($script) as $child) {
                        if (!IPS_EventExists($child)) { continue; }
                        $event = IPS_GetEvent($child);
                        if ($power > 0 && ($event['EventType'] ?? -1) === 0
                            && ($event['EventActive'] ?? false)
                            && (int)($event['TriggerVariableID'] ?? 0) === $power) {
                            $matches[$candidate] = true;
                            break;
                        }
                    }
                }
                if (count($matches) === 1) { $running = (int)array_key_first($matches); }
                else { $note = count($matches) > 1 ? 'Mehrere Laufstatus-Quellen gefunden. Quelle in den Einstellungen auswählen.'
                    : 'Laufstatus noch nicht zugeordnet. Ansageskript einmal einrichten oder Quelle auswählen.'; }
            }
            $rows[] = ['key' => $key, 'name' => $name, 'powerID' => $power, 'runningID' => $running, 'note' => $note];
        }
        return $rows;
    }

    private function LaundrySourceIDs(): array
    {
        if (!$this->HasLaundryView()) { return []; }
        $ids = [];
        foreach ($this->LaundrySources() as $source) {
            $ids[] = $source['powerID'];
            $ids[] = $source['runningID'];
        }
        return array_values(array_unique(array_filter($ids)));
    }

    private function LaundryEnergy(int $powerID, int $now): array
    {
        $empty = ['today' => null, 'week' => null, 'month' => null, 'year' => null, 'note' => 'Keine Verbrauchsdaten im Symcon-Archiv.'];
        if ($powerID <= 0 || !IPS_VariableExists($powerID)) { return $empty; }
        $archives = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (count($archives) !== 1) { $empty['note'] = 'Archiv nicht eindeutig zugeordnet.'; return $empty; }
        $archive = (int)$archives[0];
        try {
            if (!AC_GetLoggingStatus($archive, $powerID)) { return $empty; }
            $timezone = new DateTimeZone('Europe/Berlin');
            $today = (new DateTimeImmutable('@' . $now))->setTimezone($timezone);
            $starts = [
                'today' => $today->setTime(0, 0)->getTimestamp(),
                'week' => $today->modify('monday this week')->setTime(0, 0)->getTimestamp(),
                'month' => $today->modify('first day of this month')->setTime(0, 0)->getTimestamp(),
                'year' => $today->setDate((int)$today->format('Y'), 1, 1)->setTime(0, 0)->getTimestamp()
            ];
            $cacheKey = 'LaundryEnergy_' . $powerID;
            $cache = json_decode($this->GetBuffer($cacheKey), true);
            if (is_array($cache) && ($cache['until'] ?? 0) > $now
                && ($cache['dayStart'] ?? null) === $starts['today']
                && ($cache['yearStart'] ?? null) === $starts['year']) {
                return $cache['value'];
            }
            // Stündliche Archivmittelwerte als Näherung integrieren; fehlende Stunden nicht als Nullverbrauch behaupten.
            $hourStart = (int)(floor($starts['year'] / 3600) * 3600);
            $values = AC_GetAggregatedValues($archive, $powerID, 0, $hourStart, $now, 10000);
            $result = ['today' => 0.0, 'week' => 0.0, 'month' => 0.0, 'year' => 0.0, 'note' => 'Ca.-Werte aus archivierten Stundenmittelwerten.'];
            $coverage = ['today' => 0, 'week' => 0, 'month' => 0, 'year' => 0];
            foreach ($values as $entry) {
                $start = (int)($entry['TimeStamp'] ?? 0);
                $average = $entry['Avg'] ?? null;
                if ($start < $hourStart || !is_numeric($average) || !is_finite((float)$average) || (float)$average < 0) { continue; }
                $end = min($start + 3600, $now);
                foreach ($starts as $period => $periodStart) {
                    $seconds = max(0, $end - max($start, $periodStart));
                    if ($seconds === 0) { continue; }
                    $result[$period] += (float)$average * $seconds / 3600000;
                    $coverage[$period] += $seconds;
                }
            }
            foreach ($starts as $period => $periodStart) {
                if ($coverage[$period] === 0) { $result[$period] = null; }
                else { $result[$period] = round($result[$period], 3); }
            }
            if (count($values) >= 10000) { $result['note'] = 'Archivabfrage begrenzt: Verbrauch möglicherweise unvollständig.'; }
            elseif (min($coverage) < ($now - $starts['year']) * 0.95) {
                $result['note'] = 'Ca.-Werte; Archivlücken möglich (fehlende Messstunden werden nicht geschätzt).';
            }
            $this->SetBuffer($cacheKey, json_encode(['until' => $now + 900, 'dayStart' => $starts['today'], 'yearStart' => $starts['year'], 'value' => $result], JSON_THROW_ON_ERROR));
            return $result;
        } catch (Throwable $e) {
            $empty['note'] = 'Verbrauchsarchiv derzeit nicht lesbar.';
            return $empty;
        }
    }

    private function LaundryState(): ?array
    {
        if (!$this->HasLaundryView()) { return null; }
        $now = time();
        $devices = [];
        foreach ($this->LaundrySources() as $source) {
            $running = null; $since = null; $watts = null;
            $notes = $source['note'] !== '' ? [$source['note']] : [];
            try {
                $id = $source['runningID'];
                if ($id > 0 && IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0) {
                    $value = GetValue($id);
                    if (is_bool($value)) {
                        $running = $value;
                        $changed = (int)(IPS_GetVariable($id)['VariableChanged'] ?? 0);
                        if ($running && $changed > 0 && $changed <= $now) { $since = $changed; }
                    }
                }
                if ($running === null && !$notes) { $notes[] = 'Laufstatus-Quelle fehlt oder hat nicht den Typ Boolean.'; }
                $id = $source['powerID'];
                if ($id > 0 && IPS_VariableExists($id) && in_array(IPS_GetVariable($id)['VariableType'], [1, 2], true)) {
                    $value = GetValue($id);
                    if ((is_int($value) || is_float($value)) && is_finite((float)$value) && $value >= 0) {
                        $watts = round((float)$value, 1);
                    }
                }
                if ($watts === null) { $notes[] = 'Aktuelle Leistung nicht verfügbar.'; }
            } catch (Throwable $e) { $notes[] = 'Quelle momentan nicht lesbar.'; }
            $devices[] = ['key' => $source['key'], 'name' => $source['name'], 'running' => $running,
                'watts' => $watts, 'since' => $since, 'energy' => $this->LaundryEnergy($source['powerID'], $now), 'note' => implode(' ', $notes)];
        }
        return ['now' => $now, 'devices' => $devices];
    }
}
