<?php
declare(strict_types=1);

// Read-only 24-hour display plus expandable seven-day history for each status.
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

    // Reads a selected image from the local Symcon media pool.
    // No external URLs or user-supplied HTML are accepted.
    private function PresenceAvatar(string $source): array
    {
        $property = $source === 'SvenPresence' ? 'SvenPresenceImage' : 'SusiPresenceImage';
        $mediaID = $this->ConfigInteger($property);
        if ($mediaID <= 0) {
            return ['src' => null, 'note' => ''];
        }
        try {
            if (!IPS_MediaExists($mediaID)) {
                throw new RuntimeException('Das ausgewaehlte Profilbild ist nicht vorhanden.');
            }
            $media = IPS_GetMedia($mediaID);
            if ((int)($media['MediaType'] ?? -1) !== 1) {
                throw new RuntimeException('Bitte ein Bild aus der Symcon-Mediathek auswaehlen.');
            }
            $bytes = (int)($media['MediaSize'] ?? 0);
            if ($bytes > 1048576) {
                throw new RuntimeException('Profilbild ist groesser als 1 MB. Bitte ein kleines JPG/PNG/WebP verwenden.');
            }
            if (($media['MediaIsAvailable'] ?? true) === false) {
                throw new RuntimeException('Bilddatei ist derzeit nicht verfuegbar.');
            }

            $stamp = implode(':', [$mediaID, (int)($media['MediaUpdated'] ?? 0),
                (string)($media['MediaCRC'] ?? ''), $bytes]);
            $bufferName = $source === 'SvenPresence' ? 'PresenceAvatarSven' : 'PresenceAvatarSusi';
            $cache = json_decode($this->GetBuffer($bufferName), true);
            if (is_array($cache) && ($cache['stamp'] ?? '') === $stamp
                && is_string($cache['src'] ?? null)) {
                return ['src' => $cache['src'], 'note' => ''];
            }

            $base64 = IPS_GetMediaContent($mediaID);
            // Hard payload limit even if MediaSize is inaccurate.
            if ($base64 === '' || strlen($base64) > 1398104) {
                throw new RuntimeException('Profilbild ist leer oder zu gross (maximal 1 MB).');
            }

            // Detect MIME from the decoded binary header, not the filename.
            $header = base64_decode(substr($base64, 0, 64), true);
            if ($header === false) {
                throw new RuntimeException('Profilbild ist nicht korrekt kodiert.');
            }
            $mime = null;
            if (str_starts_with($header, "\xFF\xD8\xFF")) {
                $mime = 'image/jpeg';
            } elseif (str_starts_with($header, "\x89PNG\r\n\x1a\n")) {
                $mime = 'image/png';
            } elseif (str_starts_with($header, 'RIFF') && substr($header, 8, 4) === 'WEBP') {
                $mime = 'image/webp';
            } elseif (str_starts_with($header, 'GIF87a') || str_starts_with($header, 'GIF89a')) {
                $mime = 'image/gif';
            }
            if ($mime === null) {
                throw new RuntimeException('Nur JPEG, PNG, WebP oder GIF werden als Profilbild unterstuetzt.');
            }

            $src = 'data:' . $mime . ';base64,' . $base64;
            $this->SetBuffer($bufferName, json_encode(['stamp' => $stamp, 'src' => $src],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            return ['src' => $src, 'note' => ''];
        } catch (Throwable $e) {
            return ['src' => null, 'note' => $e->getMessage()];
        }
    }

    // Optional extra Boolean alarm variables, selected in the central Hausstatus instance.
    private function ExtraAlarmSources(): array
    {
        $list = json_decode($this->ConfigString('AdditionalAlarmVariables'), true);
        if (!is_array($list)) { return []; }
        $result = [];
        $seen = [
            $this->ConfigInteger('Presence') => true,
            52726 => true, // Richtige Google-Home-Quelle, nicht als zusaetzlicher Alarm anzeigen.
            12936 => true, // Veraltete Hausstatus-Quelle nicht doppelt anzeigen.
            $this->ConfigInteger('Alarm') => true,
            $this->ConfigInteger('SvenPresence') => true,
            $this->ConfigInteger('SusiPresence') => true
        ];
        foreach ($list as $item) {
            if (!is_array($item)) { continue; }
            $id = (int)($item['Variable'] ?? 0);
            $name = trim((string)($item['Name'] ?? ''));
            if ($id <= 0 || $name === '' || isset($seen[$id])) { continue; }
            $seen[$id] = true;
            $result[] = ['source' => 'ExtraAlarm:' . $id, 'id' => $id,
                'name' => mb_substr($name, 0, 60), 'kind' => 'alarm'];
            if (count($result) >= 8) { break; }
        }
        return $result;
    }

    private function PresenceHistorySources(): array
    {
        // House and alarm reuse the existing sources from the standard dashboard.
        $presenceID = $this->ConfigInteger('Presence');
        $rows = [
            ['source' => 'Presence', 'id' => $presenceID,
                'name' => $presenceID === 52726 ? 'Allgemeine Anwesenheit' : 'Hausstatus',
                'kind' => 'house'],
            ['source' => 'SvenPresence', 'id' => $this->ConfigInteger('SvenPresence'),
                'name' => 'Sven', 'kind' => 'person'],
            ['source' => 'SusiPresence', 'id' => $this->ConfigInteger('SusiPresence'),
                'name' => 'Susi', 'kind' => 'person'],
            ['source' => 'Alarm', 'id' => $this->ConfigInteger('Alarm'),
                'name' => 'Alarm innen', 'kind' => 'alarm']
        ];
        // Two deliberately separate visual cards: Gesamtanwesenheit and Google Home.
        // Both may read 52726; this does not create or modify any Symcon variable.
        // Their archive history is reused below to avoid querying the same source twice.
        array_splice($rows, 1, 0, [[
            'source' => 'GoogleHomePresence', 'id' => 52726,
            'name' => 'Google Home', 'kind' => 'house'
        ]]);
        foreach ($this->ExtraAlarmSources() as $source) { $rows[] = $source; }
        return $rows;
    }

    private function PresenceHistoryState(): array
    {
        $now = time();
        $start = $now - 86400;
        $today = (new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('Europe/Berlin'))->setTime(0,0);
        $weekStart = $today->modify('-6 days')->getTimestamp();
        $days = [];
        for ($day=$today->modify('-6 days'); $day<=$today; $day=$day->modify('+1 day')) {
            $days[] = ['start'=>$day->getTimestamp(),'end'=>$day->modify('+1 day')->getTimestamp(),
                'until'=>min($now,$day->modify('+1 day')->getTimestamp()),'label'=>$day->format('d.m.'),'today'=>$day==$today];
        }
        $cache = json_decode($this->GetBuffer('PresenceHistoryCache'), true);
        $sources = $this->PresenceHistorySources();
        $archive = $this->PresenceHistoryArchive();
        $cacheKey = json_encode(['presence-week-v1',$weekStart,$archive,array_column($sources,'id')]);
        // Bound total history payload as well as archive work; shared sources are read once.
        $uniqueCount=max(1,count(array_unique(array_filter(array_column($sources,'id')))));
        $limit=max(100,min(2000,intdiv(2000,$uniqueCount)));
        if (!is_array($cache) || ($cache['key']??'')!==$cacheKey || (int)($cache['time'] ?? 0) < $now - 45) {
            $history = [];
            $historyByVariable = [];

            foreach ($sources as $source) {
                $id = (int)$source['id'];
                $key = $source['source'];
                // Two labels for the same source need only one archive read.
                if ($id > 0 && array_key_exists($id, $historyByVariable)) {
                    $history[$key] = $historyByVariable[$id];
                    continue;
                }
                $item = ['entries' => [], 'previous' => null, 'note' => ''];
                try {
                    if ($id <= 0 || !IPS_VariableExists($id)
                        || (int)IPS_GetVariable($id)['VariableType'] !== 0) {
                        throw new RuntimeException('Vorhandene Boolean-Statusvariable auswählen.');
                    }
                    if ($archive <= 0) {
                        throw new RuntimeException('Kein eindeutiges Archiv gefunden. Archivinstanz auswählen.');
                    }
                    if (!AC_GetLoggingStatus($archive, $id)) {
                        throw new RuntimeException('Archivierung dieser Variable ist nicht aktiviert.');
                    }

                    $values = AC_GetLoggedValues($archive, $id, $weekStart, $now, $limit);
                    foreach ($values as $value) {
                        $stamp = (int)($value['TimeStamp'] ?? 0);
                        if ($stamp < $weekStart || $stamp > $now) { continue; }
                        $item['entries'][] = ['time' => $stamp, 'active' => (bool)$value['Value']];
                    }
                    usort($item['entries'], static fn(array $a, array $b): int => $a['time'] <=> $b['time']);
                    $previous = $weekStart > 0 ? AC_GetLoggedValues($archive, $id, 0, $weekStart - 1, 1) : [];
                    if ($previous !== []) { $item['previous'] = (bool)$previous[0]['Value']; }

                    if (count($values) >= $limit) {
                        $item['previous'] = null;
                        $item['note'] = 'Viele Statuswechsel: Ältere Abschnitte vor dem ersten geladenen Wert bleiben unbekannt.';
                    } elseif ($item['entries'] === [] && $previous === []) {
                        $item['note'] = 'Noch keine Archivdaten. Der Verlauf beginnt mit der ersten Aufzeichnung.';
                    } elseif ($item['previous'] === null && $item['entries'] !== []
                        && $item['entries'][0]['time'] > $weekStart) {
                        $item['note'] = 'Vor dem ersten Archivwert ist der Zustand unbekannt.';
                    }
                } catch (Throwable $e) {
                    $item['note'] = $e->getMessage();
                }
                $history[$key] = $item;
                if ($id > 0) { $historyByVariable[$id] = $item; }
            }
            $cache = ['key'=>$cacheKey,'time' => $now, 'history' => $history];
            $encoded=json_encode($cache, JSON_THROW_ON_ERROR);
            $this->SetBuffer('PresenceHistoryCache', strlen($encoded)<=196608 ? $encoded : '');
        }

        $people = [];
        foreach ($sources as $source) {
            $key = $source['source'];
            $isPerson = $source['kind'] === 'person';
            $weekHistory = $cache['history'][$key] ?? ['entries'=>[],'previous'=>null,'note'=>'Noch keine Archivdaten.'];
            $recent = ['entries'=>[],'previous'=>$weekHistory['previous'],'note'=>$weekHistory['note']];
            foreach ($weekHistory['entries'] as $event) {
                if ($event['time'] < $start) { $recent['previous']=$event['active']; }
                else { $recent['entries'][]=$event; }
            }
            $people[] = [
                'source' => $key,
                'id' => (int)$source['id'],
                'name' => $source['name'],
                'kind' => $source['kind'],
                'avatar' => $isPerson ? $this->PresenceAvatar($key) : ['src' => null, 'note' => ''],
                'current' => $this->Read((int)$source['id']),
                'history' => $recent,
                'weekHistory' => $weekHistory
            ];
        }
        return ['start' => $start, 'end' => $now, 'week'=>['start'=>$weekStart,'end'=>$now,'days'=>$days], 'people' => $people];
    }
}

