<?php
declare(strict_types=1);

trait HausstatusPageSupport
{
    // A request-local view. Navigation never writes the instance's View or sources.
    private ?array $pageContext = null;

    private function CurrentView(): int
    {
        return $this->pageContext['view'] ?? $this->ReadPropertyInteger('View');
    }

    private function CurrentRoom(): string
    {
        if ($this->pageContext !== null) { return $this->pageContext['room']; }
        $room = $this->ReadPropertyString('RoomFilter');
        if ($this->CurrentView() === 12 && $this->IsRoomLanding()) { return ''; }
        return $room;
    }

    private function IsRoomLanding(): bool
    {
        if ($this->ReadPropertyString('RoomFilter') === '' || IPS_GetName($this->InstanceID) === 'Räume') { return true; }
        $category = $this->RoomNavigationTarget();
        if ($category > 0) {
            foreach (IPS_GetChildrenIDs($category) as $id) {
                if (IPS_GetName($id) === 'Räume' && IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID
                    && !(IPS_GetObject($id)['ObjectIsHidden'] ?? false)) { return true; }
            }
        }
        return false;
    }

    private function RoomSummary(array $sections, array $climate): array
    {
        $result = [];
        foreach ($this->NavigationRooms() as $name) {
            $metrics = [];
            foreach ($climate as $row) {
                if ($row['name'] === $name && is_numeric($row['actual']['raw'] ?? null)) {
                    $metrics[] = ['label' => 'Temperatur', 'text' => $row['actual']['text']]; break;
                }
            }
            $motion = []; $lights = [];
            foreach ($sections as $room) {
                if ($room['name'] !== $name) { continue; }
                foreach ($room['groups'] as $group) {
                    foreach ($group['items'] as $item) {
                        if (in_array($item['role'], ['motion', 'presence'], true)) { $motion[] = $item['value']['raw']; }
                        if ($item['role'] === 'switch' && preg_match('/licht|light|beleuchtung/iu', $group['name'])) { $lights[] = $item['value']['raw']; }
                    }
                }
            }
            foreach ([['Bewegung', $motion, 'Erkannt', 'Keine'], ['Licht', $lights, 'An', 'Aus']] as [$label, $values, $on, $off]) {
                if (!$values) { continue; }
                $text = in_array(true, $values, true) ? $on : (count(array_filter($values, 'is_bool')) === count($values) ? $off : 'Unbekannt');
                $metrics[] = ['label' => $label, 'text' => $text];
            }
            $result[] = ['name' => $name, 'metrics' => $metrics];
        }
        return $result;
    }

    private function BaseState(): array
    {
        // A synchronous variable callback must not broadcast one client's page to everyone.
        $context = $this->pageContext;
        $this->pageContext = null;
        try { return $this->State(); }
        finally { $this->pageContext = $context; }
    }

    private function UsesInlinePages(): bool
    {
        return $this->ReadPropertyInteger('View') === 12 || ($this->ReadPropertyInteger('View') === 0
            && $this->ReadPropertyInteger('ConfigSource') === 0 && !$this->SupportsHtmlFullscreen());
    }

    private function NavigationRooms(): array
    {
        $names = [];
        $owner = $this->NavigationOwner();
        if ($this->ReadPropertyInteger('View') === 12 && $this->ReadPropertyString('RoomFilter') !== '') {
            $names[$this->ReadPropertyString('RoomFilter')] = true;
        }
        foreach ($this->RoomEntries() as $entry) { $names[$entry['Room']] = true; }
        foreach ($this->Rooms() as $entry) { $names[$entry['Name']] = true; }
        // Keep the already configured room pages reachable, including rooms without devices yet.
        foreach (IPS_GetInstanceListByModuleID('{9E33E109-4881-4E78-9906-38CAC2F1E210}') as $id) {
            if ($id !== $this->InstanceID && IPS_GetProperty($id, 'ConfigSource') === $owner
                && IPS_GetProperty($id, 'View') === 12 && IPS_GetProperty($id, 'ActiveView')) {
                $name = IPS_GetProperty($id, 'RoomFilter');
                if (is_string($name) && $name !== '') { $names[$name] = true; }
            }
        }
        unset($names[$this->ConfigString('PVRoom')], $names['']);
        $result = array_map('strval', array_keys($names));
        natcasesort($result);
        return array_values($result);
    }

    private function HandlePageRequest(string $action, mixed $payload): void
    {
        $request = '';
        $reply = [];
        $locked = false;
        $key = 'SVHSCommand' . $this->InstanceID;
        $this->SetBuffer('LastPageRequest', $action . ' | Empfangener Wert: ' . get_debug_type($payload));
        try {
            // Send a scalar JSON string through the visualization bridge; retain array compatibility.
            if (is_string($payload)) {
                if (strlen($payload) > 32768) { throw new InvalidArgumentException('Die Seitenanfrage ist zu groß.'); }
                $payload = json_decode($payload, true, 32, JSON_THROW_ON_ERROR);
            }
            $request = is_array($payload) && is_string($payload['request'] ?? null) ? $payload['request'] : '';
            if (preg_match('/^page-[a-z0-9-]{1,100}$/D', $request) !== 1) {
                throw new InvalidArgumentException('Ungültige Seitenanfrage.');
            }
            if (!$this->UsesInlinePages()) { throw new RuntimeException('Diese Instanz verwendet keine eingebetteten Unterseiten.'); }
            $view = $payload['view'] ?? null;
            $room = $payload['room'] ?? '';
            if (!is_int($view) || !in_array($view, [7, 12], true) || !is_string($room)
                || ($view === 7 && ($room !== '' || $this->ReadPropertyInteger('View') !== 0)) || ($view === 12 && $room !== '' && !in_array($room, $this->NavigationRooms(), true))) {
                throw new InvalidArgumentException('Diese Seite ist nicht eingerichtet.');
            }
            $this->pageContext = ['view' => $view, 'room' => $room];
            if ($action === 'PageValue') {
                if ($view === 12 && $room === '') { throw new RuntimeException('Bitte zuerst einen Raum auswählen.'); }
                if (!IPS_SemaphoreEnter($key, 1000)) { throw new RuntimeException('Bitte kurz warten und erneut bedienen.'); }
                $locked = true;
                // The existing room validator checks membership, type, bounds and action route.
                // Door actions remain exclusively in the homepage confirmation flow.
                $this->SetRoomValue($payload['value'] ?? null);
            }
            $this->SetBuffer('MotionCache', '');
            $this->SetBuffer('RainCache', '');
            $reply['state'] = $this->State();
            $this->SetBuffer('LastPageError', '');
        } catch (Throwable $e) {
            $reply['error'] = $e->getMessage();
            $this->SetBuffer('LastPageError', get_class($e) . ': ' . $e->getMessage());
            $this->SendDebug('Unterseite', $e->getMessage(), 0);
        } finally {
            $this->pageContext = null;
            if ($locked) { IPS_SemaphoreLeave($key); }
        }
        $reply['pageReply'] = $request;
        try {
            $encoded = json_encode($reply, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        } catch (Throwable $e) {
            $this->SetBuffer('LastPageError', get_class($e) . ': ' . $e->getMessage());
            $encoded = json_encode(['pageReply' => $request, 'error' => 'Seitendaten konnten nicht übertragen werden: ' . $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE);
        }
        try { $this->UpdateVisualizationValue($encoded); }
        catch (Throwable $e) {
            $this->SetBuffer('LastPageError', get_class($e) . ': ' . $e->getMessage());
            $this->SendDebug('Seitenübertragung', $e->getMessage(), 0);
        }
    }

    public function CheckPages(): string
    {
        $info = json_decode($this->GetVisualizationDiagnostics(), true, 512, JSON_THROW_ON_ERROR);
        $lines = ['Hausstatus ' . $info['moduleVersion'] . ' | Symcon ' . $info['kernel'],
            'Letzte Seitenanfrage: ' . ($this->GetBuffer('LastPageRequest') ?: 'Noch keine Anfrage am Modul angekommen.'),
            'Letzter Seitenfehler: ' . ($this->GetBuffer('LastPageError') ?: 'Kein Fehler gespeichert.')];
        if (!$this->UsesInlinePages()) {
            $lines[] = 'Eingebettete Unterseiten werden in dieser Instanz nicht verwendet. Die zentrale Übersicht prüfen.';
            return implode(PHP_EOL, $lines);
        }
        $pages = [['view' => 7, 'room' => '', 'name' => 'PV-Details']];
        foreach (array_slice($this->NavigationRooms(), 0, 50) as $room) { $pages[] = ['view' => 12, 'room' => $room, 'name' => $room]; }
        $savedContext = $this->pageContext;
        foreach ($pages as $page) {
            try {
                $this->pageContext = ['view' => $page['view'], 'room' => $page['room']];
                $encoded = json_encode($this->State(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                $lines[] = $page['name'] . ': OK, ' . strlen($encoded) . ' Bytes Zustandsdaten.';
            } catch (Throwable $e) { $lines[] = $page['name'] . ': ' . get_class($e) . ': ' . $e->getMessage(); }
            finally { $this->pageContext = $savedContext; }
        }
        $lines[] = 'Serverprüfung ohne Gerätebefehle. Die Browserübertragung wird damit nicht bestätigt.';
        return implode(PHP_EOL, $lines);
    }
}

