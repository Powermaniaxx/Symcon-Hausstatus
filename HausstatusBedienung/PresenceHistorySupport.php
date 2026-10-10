<?php
declare(strict_types=1);

// 0.35: Read-only 24-hour history for existing personal presence variables.
// The original variables, their actions and archive configuration remain untouched.
trait HausstatusPresenceHistorySupport
{
    private function PresenceHistoryArchive(): int
    {
        $id = $this->ConfigInteger('PresenceArchive');
        if ($id > 0) { return IPS_InstanceExists($id) ? $id : 0; }
        $ids = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($ids) === 1 ? (int)$ids[0] : 0;
    }

    private function PresenceHistoryState(): array
    {
        $now = time();
        $start = $now - 86400;
        $cache = json_decode($this->GetBuffer('PresenceHistoryCache'), true);
        if (!is_array($cache) || (int)($cache['time'] ?? 0) < $now - 45) {
            $history = [];
            $archive = $this->PresenceHistoryArchive();

            foreach (['SvenPresence', 'SusiPresence'] as $source) {
                $id = $this->ConfigInteger($source);
                $item = ['entries' => [], 'previous' => null, 'note' => ''];
                try {
                    if ($id <= 0 || !IPS_VariableExists($id)
                        || (int)IPS_GetVariable($id)['VariableType'] !== 0) {
                        throw new RuntimeException('Eine vorhandene Boolean-Anwesenheitsvariable auswählen.');
                    }
                    if ($archive <= 0) {
                        throw new RuntimeException('Kein eindeutiges Archiv gefunden. Archivinstanz in den Einstellungen auswählen.');
                    }
                    if (!AC_GetLoggingStatus($archive, $id)) {
                        throw new RuntimeException('Die Archivierung dieser Variable ist nicht aktiviert.');
                    }

                    $values = AC_GetLoggedValues($archive, $id, $start, $now, 10000);
                    foreach ($values as $value) {
                        $stamp = (int)($value['TimeStamp'] ?? 0);
                        if ($stamp < $start || $stamp > $now) { continue; }
                        $item['entries'][] = ['time' => $stamp, 'active' => (bool)$value['Value']];
                    }
                    usort($item['entries'], static fn(array $a, array $b): int => $a['time'] <=> $b['time']);

                    $previous = $start > 0 ? AC_GetLoggedValues($archive, $id, 0, $start - 1, 1) : [];
                    if ($previous !== []) { $item['previous'] = (bool)$previous[0]['Value']; }

                    if (count($values) >= 10000) {
                        $item['note'] = 'Abfragelimit erreicht: Verlauf möglicherweise unvollständig.';
                    } elseif ($item['entries'] === [] && $previous === []) {
                        $item['note'] = 'Noch keine Archivdaten. Der Verlauf beginnt mit der ersten Aufzeichnung.';
                    } elseif ($item['previous'] === null && $item['entries'] !== []
                        && $item['entries'][0]['time'] > $start) {
                        $item['note'] = 'Vor dem ersten Archivwert ist die Anwesenheit unbekannt.';
                    }
                } catch (Throwable $e) {
                    $item['note'] = $e->getMessage();
                }
                $history[$source] = $item;
            }
            $cache = ['time' => $now, 'history' => $history];
            $this->SetBuffer('PresenceHistoryCache', json_encode($cache, JSON_THROW_ON_ERROR));
        }

        $people = [];
        foreach (['SvenPresence' => 'Sven', 'SusiPresence' => 'Susi'] as $source => $name) {
            $people[] = [
                'name' => $name,
                'current' => $this->Read($this->ConfigInteger($source)),
                'history' => $cache['history'][$source] ?? ['entries' => [], 'previous' => null, 'note' => 'Noch keine Archivdaten.']
            ];
        }
        return ['start' => $start, 'end' => $now, 'people' => $people];
    }
}
