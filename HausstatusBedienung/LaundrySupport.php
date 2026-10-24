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
            if ($source['key'] === 'Washer') { $ids[] = $this->ConfigInteger('WasherEnergyTotal'); }
        }
        return array_values(array_unique(array_filter($ids)));
    }

    // The old meter is a fixed handover balance, never a second live counter.
    private function WasherTotalEnergy(): array
    {
        $id = $this->ConfigInteger('WasherEnergyTotal');
        $offset = (float)$this->ConfigValue('WasherEnergyOffset');
        $result = ['total' => null, 'totalNote' => 'Gesamtzähler nicht verfügbar.'];
        if (!is_finite($offset) || $offset < 0 || $id <= 0 || !IPS_VariableExists($id)) { return $result; }
        try {
            $value = GetValue($id);
            if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0) { return $result; }
            $result['total'] = round($offset + (float)$value, 3);
            $result['totalNote'] = 'Gesamt inkl. ' . number_format($offset, 2, ',', '.') . ' kWh Altbestand (alter Zähler 44294).';
        } catch (Throwable $e) { /* Keep unknown instead of inventing a zero reading. */ }
        return $result;
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
            else {
                foreach ($starts as $period => $periodStart) {
                    if ($coverage[$period] < ($now - $periodStart) * 0.95) {
                        $result['note'] = 'Ca.-Werte; Archivlücken möglich (fehlende Messstunden werden nicht geschätzt).';
                        break;
                    }
                }
            }
            $this->SetBuffer($cacheKey, json_encode(['until' => $now + 900, 'dayStart' => $starts['today'], 'yearStart' => $starts['year'], 'value' => $result], JSON_THROW_ON_ERROR));
            return $result;
        } catch (Throwable $e) {
            $empty['note'] = 'Verbrauchsarchiv derzeit nicht lesbar.';
            return $empty;
        }
    }

    /**
     * A run flag can remain TRUE if its external announcement script misses the
     * switch-off event. Show a run only while its power reading is plausible.
     * A short zero-watt pause during a wash cycle is normal, so allow 15 minutes.
     *
     * Display-only protection: never modify the real run flag, the power source,
     * or the existing announcement script.
     *
     * @return array{running: ?bool, since: ?int, note: string}
     */
    private function LaundryCheckedRunState(string $key, int $powerID, ?bool $running, ?int $since, ?float $watts, int $now): array
    {
        $cacheKey = 'LaundryLowPower_' . $key . '_' . $powerID;
        $result = ['running' => $running, 'since' => $since, 'note' => ''];

        if ($running !== true || $watts === null || $powerID <= 0 || !IPS_VariableExists($powerID)) {
            // No validated low-power measurement -> no reason to override the source.
            if ($running === false || ($watts !== null && $watts > 2.0)) {
                $this->SetBuffer($cacheKey, '');
            }
            return $result;
        }

        if ($watts > 2.0) {
            $this->SetBuffer($cacheKey, '');
            return $result;
        }

        // Power is <= 2 W. Use the last actual power transition if we have not
        // monitored this idle period before. Never predate the current run flag.
        $metadata = IPS_GetVariable($powerID);
        $changed = (int)($metadata['VariableChanged'] ?? 0);
        $initialLow = ($changed > 0 && $changed <= $now) ? $changed : $now;
        $lowSince = (int)$this->GetBuffer($cacheKey);
        if ($lowSince <= 0 || $lowSince > $now) {
            $lowSince = $initialLow;
        }
        if ($since !== null && $since > 0 && $since <= $now) {
            $lowSince = max($lowSince, $since);
        }
        $this->SetBuffer($cacheKey, (string)$lowSince);

        if ($now - $lowSince < 900) {
            return $result;
        }

        $result['running'] = false;
        $result['since'] = null;
        $result['note'] = 'Laufstatusquelle meldet weiterhin „Läuft“, aber die Leistung liegt seit mindestens 15 Minuten bei höchstens 2 W. Ansageskript/Laufstatus prüfen.';
        return $result;
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
                $checked = $this->LaundryCheckedRunState(
                    $source['key'], $source['powerID'], $running, $since, $watts, $now
                );
                $running = $checked['running'];
                $since = $checked['since'];
                if ($checked['note'] !== '') { $notes[] = $checked['note']; }
            } catch (Throwable $e) { $notes[] = 'Quelle momentan nicht lesbar.'; }
            $energy = $this->LaundryEnergy($source['powerID'], $now);
            if ($source['key'] === 'Washer') { $energy = array_merge($energy, $this->WasherTotalEnergy()); }
            $devices[] = ['key' => $source['key'], 'name' => $source['name'], 'running' => $running,
                'watts' => $watts, 'since' => $since, 'energy' => $energy, 'note' => implode(' ', $notes)];
        }
        return ['now' => $now, 'devices' => $devices];
    }
}
