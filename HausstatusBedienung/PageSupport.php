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
        return $this->pageContext['room'] ?? $this->ReadPropertyString('RoomFilter');
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
        return $this->ReadPropertyInteger('View') === 0
            && $this->ReadPropertyInteger('ConfigSource') === 0 && !$this->SupportsHtmlFullscreen();
    }

    private function NavigationRooms(): array
    {
        $names = [];
        foreach ($this->RoomEntries() as $entry) { $names[$entry['Room']] = true; }
        foreach ($this->Rooms() as $entry) { $names[$entry['Name']] = true; }
        // Keep the already configured room pages reachable, including rooms without devices yet.
        foreach (IPS_GetInstanceListByModuleID('{9E33E109-4881-4E78-9906-38CAC2F1E210}') as $id) {
            if ($id !== $this->InstanceID && IPS_GetProperty($id, 'ConfigSource') === $this->InstanceID
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
        $request = is_array($payload) && is_string($payload['request'] ?? null) ? $payload['request'] : '';
        if (preg_match('/^page-[a-z0-9-]{1,100}$/D', $request) !== 1) {
            throw new InvalidArgumentException('Ungültige Seitenanfrage.');
        }
        $reply = ['pageReply' => $request];
        $locked = false;
        $key = 'SVHSCommand' . $this->InstanceID;
        try {
            if (!$this->UsesInlinePages()) { throw new RuntimeException('Diese Instanz verwendet keine eingebetteten Unterseiten.'); }
            $view = $payload['view'] ?? null;
            $room = $payload['room'] ?? '';
            if (!is_int($view) || !in_array($view, [7, 12], true) || !is_string($room)
                || ($view === 7 && $room !== '') || ($view === 12 && !in_array($room, $this->NavigationRooms(), true))) {
                throw new InvalidArgumentException('Diese Seite ist nicht eingerichtet.');
            }
            $this->pageContext = ['view' => $view, 'room' => $room];
            if ($action === 'PageValue') {
                if (!IPS_SemaphoreEnter($key, 1000)) { throw new RuntimeException('Bitte kurz warten und erneut bedienen.'); }
                $locked = true;
                // The existing room validator checks membership, type, bounds and action route.
                // Door actions remain exclusively in the homepage confirmation flow.
                $this->SetRoomValue($payload['value'] ?? null);
            }
            $this->SetBuffer('MotionCache', '');
            $this->SetBuffer('RainCache', '');
            $reply['state'] = $this->State();
        } catch (Throwable $e) {
            $reply['error'] = $e->getMessage();
            $this->SendDebug('Unterseite', $e->getMessage(), 0);
        } finally {
            $this->pageContext = null;
            if ($locked) { IPS_SemaphoreLeave($key); }
        }
        $this->UpdateVisualizationValue(json_encode($reply, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }
}
