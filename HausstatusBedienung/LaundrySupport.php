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
                'watts' => $watts, 'since' => $since, 'note' => implode(' ', $notes)];
        }
        return ['now' => $now, 'devices' => $devices];
    }
}
