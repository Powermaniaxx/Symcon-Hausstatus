<?php
declare(strict_types=1);

trait HausstatusRainSupport
{
    private function HasRainView(): bool
    {
        return in_array($this->CurrentView(), [0, 10], true);
    }

    private function RainArchive(): int
    {
        $id = $this->ConfigInteger('RainArchive');
        if ($id > 0) { return IPS_InstanceExists($id) ? $id : 0; }
        $ids = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($ids) === 1 ? $ids[0] : 0;
    }

    private function RainHistoryState(): array
    {
        $now = time(); $start = $now - 86400;
        $id = $this->ConfigInteger('Raining');
        $current = $this->Read($id);
        $cache = json_decode($this->GetBuffer('RainCache'), true);
        if (!is_array($cache) || ($cache['time'] ?? 0) < $now - 30) {
            $history = ['entries' => [], 'previous' => null, 'note' => ''];
            try {
                if ($id <= 0 || !IPS_VariableExists($id) || IPS_GetVariable($id)['VariableType'] !== 0) {
                    throw new RuntimeException('RAINING als Boolean-Regenvariable auswählen.');
                }
                $archive = $this->RainArchive();
                if ($archive <= 0) { throw new RuntimeException('Regenarchiv auswählen: kein eindeutiges Archiv gefunden.'); }
                if (!AC_GetLoggingStatus($archive, $id)) { throw new RuntimeException('Regenarchivierung ist nicht aktiviert.'); }
                $values = AC_GetLoggedValues($archive, $id, $start, $now, 10000);
                foreach ($values as $value) {
                    $history['entries'][] = ['time' => (int)$value['TimeStamp'], 'active' => (bool)$value['Value']];
                }
                $previous = AC_GetLoggedValues($archive, $id, 0, $start - 1, 1);
                if ($previous) { $history['previous'] = (bool)$previous[0]['Value']; }
                if (count($values) >= 10000) {
                    $history['previous'] = null;
                    $history['note'] = 'Abfragelimit erreicht: Regenverlauf möglicherweise unvollständig.';
                }
                elseif (!$values && !$previous) { $history['note'] = 'Noch keine Archivdaten. Regenzeiten werden ab Beginn der Aufzeichnung angezeigt.'; }
                elseif (!$previous && $values && min(array_column($history['entries'], 'time')) > $start) {
                    $history['note'] = 'Vor dem ersten Archivwert ist der Regenzustand unbekannt.';
                }
            } catch (Throwable $e) { $history['note'] = $e->getMessage(); }
            $cache = ['time' => $now, 'history' => $history];
            $this->SetBuffer('RainCache', json_encode($cache, JSON_THROW_ON_ERROR));
        }
        return ['start' => $start, 'end' => $now, 'current' => $current, 'history' => $cache['history']];
    }
}

