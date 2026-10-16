<?php
declare(strict_types=1);

// Als neues Symcon-PHP-Skript speichern und einmal manuell ausführen.
// Erstellt sein eigenes Ereignis; bestehende Ereignisse/Flows werden nicht verändert.
$MASTER_ID = 52627;
$SCRIPT_ID = (int) ($_IPS['SELF'] ?? 0);
$MANUAL = ($_IPS['SENDER'] ?? '') === 'Execute';
if ($SCRIPT_ID <= 0 || !IPS_ScriptExists($SCRIPT_ID)) {
    throw new RuntimeException('Dieses Skript innerhalb von IP-Symcon ausführen.');
}
if (!IPS_InstanceExists($MASTER_ID)
    || IPS_GetInstance($MASTER_ID)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}') {
    throw new RuntimeException('Hausstatus-Master 52627 fehlt.');
}
$configuration = json_decode(IPS_GetConfiguration($MASTER_ID), true, 512, JSON_THROW_ON_ERROR);
if (($configuration['ConfigSource'] ?? -1) !== 0 || !($configuration['PhilipsTvEnabled'] ?? false)) {
    throw new RuntimeException('Philips TV im zentralen Hausstatus-Master aktivieren.');
}
$ids = [];
foreach (['ShieldOnline' => 0, 'ShieldPower' => 3, 'ShieldUpdated' => 1] as $ident => $type) {
    $id = @IPS_GetObjectIDByIdent($ident, $MASTER_ID);
    if ($id === false || !IPS_VariableExists($id) || IPS_GetVariable($id)['VariableType'] !== $type) {
        throw new RuntimeException('Statusvariable ' . $ident . ' fehlt oder hat den falschen Typ.');
    }
    $ids[$ident] = $id;
}

$key = 'ShieldTvFollow' . $SCRIPT_ID;
if (!IPS_SemaphoreEnter($key, 1000)) { return; }
try {
    $cacheID = @IPS_GetObjectIDByIdent('ShieldTvFollowState', $SCRIPT_ID);
    $eventID = @IPS_GetObjectIDByIdent('ShieldTvFollowEvent', $SCRIPT_ID);
    if ($MANUAL) {
        if ($cacheID === false) {
            $cacheID = IPS_CreateVariable(3);
            IPS_SetParent($cacheID, $SCRIPT_ID);
            IPS_SetIdent($cacheID, 'ShieldTvFollowState');
            IPS_SetName($cacheID, 'TV-Kopplung – interner Zustand');
            IPS_SetHidden($cacheID, true);
        }
        if ($eventID === false) {
            $eventID = IPS_CreateEvent(0);
            IPS_SetParent($eventID, $SCRIPT_ID);
            IPS_SetIdent($eventID, 'ShieldTvFollowEvent');
            IPS_SetName($eventID, 'Wohnzimmer-Shield → Philips TV');
        }
    }
    if ($cacheID === false || !IPS_VariableExists($cacheID) || IPS_GetVariable($cacheID)['VariableType'] !== 3
        || $eventID === false || !IPS_EventExists($eventID) || IPS_GetEvent($eventID)['EventType'] !== 0) {
        throw new RuntimeException('Einrichtung fehlt oder Objektkennung belegt. Skript einmal manuell ausführen.');
    }
    if ($MANUAL) {
        // ShieldUpdated is written LAST: the whole received status is committed.
        IPS_SetEventTrigger($eventID, 1, $ids['ShieldUpdated']);
        IPS_SetEventAction($eventID, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);
        IPS_SetEventActive($eventID, true);
        echo 'Automatische TV-Kopplung eingerichtet. Ereignis: ' . $eventID . PHP_EOL;
    }

    $updated = (int) GetValue($ids['ShieldUpdated']);
    $mode = (string) GetValue($ids['ShieldPower']);
    if (GetValue($ids['ShieldOnline']) !== true || $updated <= 0 || time() - $updated > 60
        || $updated > time() + 5 || !in_array($mode, ['awake', 'standby'], true)) {
        // No OFF action for network errors. Recovery will resynchronize once.
        SetValue($cacheID, '');
        if ($MANUAL) { echo 'Warte auf gültigen Shield-Status; TV bleibt unverändert.' . PHP_EOL; }
        return;
    }
    $previous = json_decode((string) GetValue($cacheID), true) ?? [];
    $changed = ($previous['mode'] ?? '') !== $mode || $updated - (int) ($previous['updated'] ?? 0) > 60;
    if ($changed || $MANUAL) {
        IPS_RequestAction($MASTER_ID, 'ShieldPower_philips', $mode === 'awake');
        if ($MANUAL) { echo 'TV-Befehl vorgemerkt: ' . ($mode === 'awake' ? 'Ein' : 'Standby') . PHP_EOL; }
    }
    SetValue($cacheID, json_encode(['mode' => $mode, 'updated' => $updated], JSON_THROW_ON_ERROR));
} finally {
    IPS_SemaphoreLeave($key);
}
