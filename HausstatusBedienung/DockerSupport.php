<?php
declare(strict_types=1);

// Optional Portainer integration, version 0.42.
// Uses the existing Portainer container module; never calls a shell or Docker socket.
trait HausstatusDockerSupport
{
    private const DOCKER_PORTAINER_MODULE = '{80AA764D-EABE-B85E-D997-43A2D244D6E8}';

    private function DockerContainerIDs(): array
    {
        $category = $this->ConfigInteger('DockerCategory');
        if ($category > 0 && !IPS_CategoryExists($category)) { return []; }
        $ids = IPS_GetInstanceListByModuleID(self::DOCKER_PORTAINER_MODULE);
        $ids = array_values(array_filter($ids, static function ($id) use ($category): bool {
            return IPS_InstanceExists((int)$id) &&
                ($category === 0 || IPS_GetParent((int)$id) === $category);
        }));
        usort($ids, static fn(int $a, int $b): int => strnatcasecmp(IPS_GetName($a), IPS_GetName($b)));
        return array_slice($ids, 0, 32);
    }

    private function DockerContainerValues(int $instanceID): array
    {
        $names = [
            'Name' => 'name', 'Image' => 'image', 'Läuft' => 'running',
            'Wird neu gestartet' => 'restarting', 'Pausiert' => 'paused',
            'Tot' => 'dead', 'Fehler' => 'error', 'Status' => 'status',
            'Gestartet um' => 'started', 'Anzahl der Neustarts' => 'restarts'
        ];
        $result = [];
        foreach (IPS_GetChildrenIDs($instanceID) as $child) {
            if (!IPS_VariableExists($child)) { continue; }
            $key = $names[IPS_GetName($child)] ?? null;
            if ($key === null) { continue; }
            $result[$key] = $this->Read($child);
        }
        return $result;
    }

    private function DockerProtected(array $values, string $fallbackName): bool
    {
        $name = (string)($values['name']['raw'] ?? $fallbackName);
        $image = (string)($values['image']['raw'] ?? '');
        // A Symcon container must not stop its own control interface accidentally.
        return stripos($name, 'symcon') !== false
            || stripos($fallbackName, 'symcon') !== false
            || stripos($image, 'symcon/symcon') !== false;
    }

    private function DockerContainerState(int $id): array
    {
        $name = IPS_GetName($id);
        $values = $this->DockerContainerValues($id);
        $protected = $this->DockerProtected($values, $name);
        $running = $values['running']['raw'] ?? null;
        $paused = $values['paused']['raw'] ?? null;
        $restarting = $values['restarting']['raw'] ?? null;
        $dead = $values['dead']['raw'] ?? null;
        $known = is_bool($running);
        $ready = function_exists('PORTAINER_StartContainer')
            && function_exists('PORTAINER_StopContainer')
            && function_exists('PORTAINER_RestartContainer');
        $busy = $paused === true || $restarting === true || $dead === true;
        $allowCriticalRestart = $this->ConfigBoolean('DockerAllowSymconRestart');

        $displayName = trim((string)($values['name']['raw'] ?? ''));
        if ($displayName === '') { $displayName = $name; }
        return [
            'id' => $id,
            'name' => $displayName,
            'image' => (string)($values['image']['text'] ?? ''),
            'status' => (string)($values['status']['text'] ?? 'Unbekannt'),
            'running' => $known ? $running : null,
            'paused' => $paused === true,
            'restarting' => $restarting === true,
            'restarts' => $values['restarts']['raw'] ?? null,
            'started' => (string)($values['started']['text'] ?? ''),
            'protected' => $protected,
            'canStart' => $ready && $known && $running === false && !$busy && !$protected,
            'canStop' => $ready && $known && $running === true && !$busy && !$protected,
            'canRestart' => $ready && $known && $running === true && !$busy
                && (!$protected || $allowCriticalRestart),
            'note' => $protected
                ? ($allowCriticalRestart
                    ? 'Symcon-Container: Start/Stop gesperrt; Neustart ausdrücklich freigegeben.'
                    : 'Symcon-Container geschützt: Bedienung gesperrt.')
                : (!$ready ? 'Portainer-Steuerbefehle nicht verfügbar.' : '')
        ];
    }

    private function DockerState(): ?array
    {
        if ($this->CurrentView() !== 25) { return null; }
        $ids = $this->DockerContainerIDs();
        $containers = [];
        foreach ($ids as $id) {
            try { $containers[] = $this->DockerContainerState($id); }
            catch (Throwable $e) {
                $containers[] = [
                    'id' => $id, 'name' => IPS_GetName($id), 'image' => '', 'status' => 'Unbekannt',
                    'running' => null, 'paused' => false, 'restarting' => false,
                    'restarts' => null, 'started' => '', 'protected' => true,
                    'canStart' => false, 'canStop' => false, 'canRestart' => false,
                    'note' => $e->getMessage()
                ];
            }
        }
        return ['containers' => $containers, 'configuredCategory' => $this->ConfigInteger('DockerCategory')];
    }

    // Explicit user action from the master settings: create one tile in the chosen
    // visualisation category, never alter or remove an existing tile.
    public function CreateDockerTile(): string
    {
        if ($this->ReadPropertyInteger('ConfigSource') !== 0) {
            return 'Bitte diesen Button in der zentralen Hausstatus-Instanz verwenden.';
        }
        $category = $this->ReadPropertyInteger('DockerTileCategory');
        if ($category <= 0 || !IPS_CategoryExists($category)) {
            return 'Bitte zuerst die Zielkategorie „Variablen“ auswählen und Änderungen übernehmen.';
        }
        $moduleID = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
        foreach (IPS_GetChildrenIDs($category) as $child) {
            if (!IPS_InstanceExists($child)
                || IPS_GetInstance($child)['ModuleInfo']['ModuleID'] !== $moduleID) {
                continue;
            }
            if ((int)IPS_GetProperty($child, 'View') === 25) {
                return 'Die NAS-Container-Kachel ist in dieser Kategorie bereits vorhanden (ID ' . $child . ').';
            }
        }
        $id = IPS_CreateInstance($moduleID);
        IPS_SetParent($id, $category);
        IPS_SetName($id, 'NAS-Container');
        IPS_SetProperty($id, 'View', 25);
        IPS_SetProperty($id, 'ConfigSource', $this->InstanceID);
        IPS_ApplyChanges($id);
        return 'NAS-Container-Kachel erstellt (ID ' . $id
            . '). Die Kategorie jetzt in der Visualisierung öffnen.';
    }

    private function SetDockerControl(mixed $value): void
    {
        if ($this->CurrentView() !== 25) {
            throw new RuntimeException('Die Docker-Steuerung ist nur in der NAS-Container-Kachel verfügbar.');
        }
        if (!is_array($value) || count($value) !== 2) {
            throw new InvalidArgumentException('Ungültiger Containerbefehl.');
        }
        $id = $value['id'] ?? null;
        $action = $value['action'] ?? null;
        if (!is_int($id) || !is_string($action) || !in_array($action, ['start', 'stop', 'restart'], true)) {
            throw new InvalidArgumentException('Ungültiger Container oder Befehl.');
        }
        if (!in_array($id, $this->DockerContainerIDs(), true)
            || !IPS_InstanceExists($id)
            || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== self::DOCKER_PORTAINER_MODULE) {
            throw new RuntimeException('Dieser Container gehört nicht zur freigegebenen Portainer-Liste.');
        }
        $state = $this->DockerContainerState($id);
        $permissions = ['start' => 'canStart', 'stop' => 'canStop', 'restart' => 'canRestart'];
        if ($state[$permissions[$action]] !== true) {
            throw new RuntimeException('Dieser Befehl ist für den Containerzustand nicht freigegeben.');
        }

        // Prevent accidental double actions even across subsequent requests.
        $last = json_decode($this->GetBuffer('DockerLastCommand'), true);
        if (is_array($last) && ($last['id'] ?? 0) === $id
            && time() - (int)($last['time'] ?? 0) < 15) {
            throw new RuntimeException('Bitte 15 Sekunden warten, bevor derselbe Container erneut bedient wird.');
        }
        $this->SetBuffer('DockerLastCommand', json_encode(['id' => $id, 'time' => time()]));

        $result = match ($action) {
            'start' => PORTAINER_StartContainer($id),
            'stop' => PORTAINER_StopContainer($id),
            'restart' => PORTAINER_RestartContainer($id)
        };
        if ($result !== true) {
            throw new RuntimeException('Portainer hat den Befehl nicht bestätigt. Containerstatus prüfen.');
        }
    }
}
