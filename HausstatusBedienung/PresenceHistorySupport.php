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
                'avatar' => $this->PresenceAvatar($source),
                'current' => $this->Read($this->ConfigInteger($source)),
                'history' => $cache['history'][$source] ?? ['entries' => [], 'previous' => null, 'note' => 'Noch keine Archivdaten.']
            ];
        }
        return ['start' => $start, 'end' => $now, 'people' => $people];
    }
}
