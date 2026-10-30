<?php
declare(strict_types=1);

/*
 * NVIDIA Shield -> Philips TV
 * Zustandsabhängige Kopplung
 *
 * Shield awake   -> Philips EIN
 * Shield standby -> Philips STANDBY
 *
 * Keine wiederholten Befehle bei gleichem Zustand.
 * Keine TV-Aktion bei Netzwerkfehlern.
 * Manueller Start richtet nur das Ereignis ein.
 */

$MASTER_ID = 52627;
// 0 übernimmt die im Hausstatus ausgewählte Philips-TV-Instanz.
$TV_INSTANCE_ID = 0;
// Shield-Instanz, Statusvariable oder Link. 0 = bisherige Shield-Werte im Hausstatus.
$SHIELD_SOURCE_ID = 26475;
// Wie im Hausstatus: 150 Sekunden tolerieren (60-Sekunden-Polling plus Laufzeit).
$MAX_STATUS_AGE = 150;
$SCRIPT_ID = (int) ($_IPS['SELF'] ?? 0);
$MANUAL = ($_IPS['SENDER'] ?? '') === 'Execute';

if ($SCRIPT_ID <= 0 || !IPS_ScriptExists($SCRIPT_ID)) {
    throw new RuntimeException('Ungültige Skript-ID.');
}

if (!IPS_InstanceExists($MASTER_ID)
    || IPS_GetInstance($MASTER_ID)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}') {
    throw new RuntimeException('Hausstatus-Master fehlt.');
}

$configuration = json_decode(
    IPS_GetConfiguration($MASTER_ID),
    true,
    512,
    JSON_THROW_ON_ERROR
);

if (
    ($configuration['ConfigSource'] ?? -1) !== 0 ||
    !($configuration['PhilipsTvEnabled'] ?? false)
) {
    throw new RuntimeException(
        'Philips TV im Hausstatus-Master aktivieren.'
    );
}

$tvInstanceID = $TV_INSTANCE_ID > 0 ? $TV_INSTANCE_ID : (int) ($configuration['PhilipsTVInstance'] ?? 0);
if ($tvInstanceID <= 0 || !IPS_InstanceExists($tvInstanceID)
    || IPS_GetInstance($tvInstanceID)['ModuleInfo']['ModuleID'] !== '{7A9BA592-5E11-4F37-B9D0-740BC1C61C19}') {
    throw new RuntimeException('Im Hausstatus-Master die neue direkte Philips-TV-Instanz auswählen oder TV_INSTANCE_ID im Skript setzen.');
}
$tvConfig = json_decode(IPS_GetConfiguration($tvInstanceID), true, 512, JSON_THROW_ON_ERROR);
if (!($tvConfig['Enabled'] ?? false)) {
    throw new RuntimeException('Verbindung in der Philips-TV-Instanz aktivieren.');
}
$tvPowerID = @IPS_GetObjectIDByIdent('Power', $tvInstanceID);
if ($tvPowerID === false || !IPS_VariableExists($tvPowerID) || IPS_GetVariable($tvPowerID)['VariableType'] !== 0) {
    throw new RuntimeException('Die neue Boolean-Variable Power der Philips-TV-Instanz fehlt.');
}

// Quelle prüfen; eine angegebene Variable muss eine bekannte Statuskennung haben.
$shieldSource = $SHIELD_SOURCE_ID > 0 ? $SHIELD_SOURCE_ID : $MASTER_ID;
$seen = [];
for ($hop = 0; IPS_LinkExists($shieldSource); $hop++) {
    if ($hop >= 8 || isset($seen[$shieldSource])) { throw new RuntimeException('Zyklischer Shield-Link.'); }
    $seen[$shieldSource] = true;
    $shieldSource = (int) IPS_GetLink($shieldSource)['TargetID'];
}
if (!IPS_ObjectExists($shieldSource)) { throw new RuntimeException('Shield-Quelle ' . $shieldSource . ' existiert nicht.'); }
$preferredID = IPS_VariableExists($shieldSource) ? $shieldSource : 0;
$shieldContainer = $preferredID > 0 ? IPS_GetParent($preferredID) : $shieldSource;
$aliases = [
    'ShieldOnline' => ['ShieldOnline', 'Online', 'Connected', 'Available', 'Reachable'],
    'ShieldPower' => ['ShieldPower', 'PowerState', 'Power', 'DeviceState', 'State'],
    'ShieldUpdated' => ['ShieldUpdated', 'Updated', 'StatusUpdated', 'LastUpdated', 'LastUpdate', 'LastSeen', 'LastPoll', 'LastStatusUpdate']
];
$types = ['ShieldOnline' => [0], 'ShieldPower' => [3, 0], 'ShieldUpdated' => [1]];
$inventory = static function (int $parent): string {
    $rows = [];
    foreach (array_slice(IPS_GetChildrenIDs($parent), 0, 40) as $id) {
        if (!IPS_VariableExists($id)) { continue; }
        $rows[] = $id . ': ' . IPS_GetName($id) . ' [Ident=' . IPS_GetObject($id)['ObjectIdent']
            . ', Typ=' . IPS_GetVariable($id)['VariableType'] . ']';
    }
    return implode("\n", $rows);
};
if ($preferredID > 0) {
    $ident = (string) IPS_GetObject($preferredID)['ObjectIdent'];
    if (!in_array($ident, array_merge(...array_values($aliases)), true)) {
        throw new RuntimeException('ID ' . $preferredID . ' ist eine Variable mit unbekannter Statuskennung. '
            . 'Bitte die Shield-Instanz angeben. Verfügbare Variablen unter ' . $shieldContainer . ":\n" . $inventory($shieldContainer));
    }
}
$ids = [];
foreach ($aliases as $role => $candidates) {
    if ($preferredID > 0 && in_array(IPS_GetObject($preferredID)['ObjectIdent'], $candidates, true)
        && in_array(IPS_GetVariable($preferredID)['VariableType'], $types[$role], true)) {
        $ids[$role] = $preferredID;
        continue;
    }
    foreach ($candidates as $ident) {
        $id = @IPS_GetObjectIDByIdent($ident, $shieldContainer);
        if ($id !== false && IPS_VariableExists($id) && in_array(IPS_GetVariable($id)['VariableType'], $types[$role], true)) {
            $ids[$role] = $id;
            break;
        }
    }
    if (!isset($ids[$role])) {
        throw new RuntimeException('Shield-Quelle ' . $shieldSource . ': Keine passende Variable für ' . $role
            . '. Kein TV-Befehl. Verfügbare Variablen unter ' . $shieldContainer . ":\n" . $inventory($shieldContainer));
    }
}
$shieldSourceKey = implode(':', [$shieldContainer, $ids['ShieldOnline'], $ids['ShieldPower'], $ids['ShieldUpdated']]);

$key = 'ShieldTvFollow' . $SCRIPT_ID;

if (!IPS_SemaphoreEnter($key, 1000)) {
    return;
}

try {
    $cacheID = @IPS_GetObjectIDByIdent(
        'ShieldTvFollowState',
        $SCRIPT_ID
    );

    $eventID = @IPS_GetObjectIDByIdent(
        'ShieldTvFollowEvent',
        $SCRIPT_ID
    );

    // Einmalige Einrichtung
    if ($MANUAL) {
        if ($cacheID === false) {
            $cacheID = IPS_CreateVariable(3);
            IPS_SetParent($cacheID, $SCRIPT_ID);
            IPS_SetIdent($cacheID, 'ShieldTvFollowState');
            IPS_SetName(
                $cacheID,
                'TV-Kopplung - interner Zustand'
            );
            IPS_SetHidden($cacheID, true);
        }

        if ($eventID === false) {
            $eventID = IPS_CreateEvent(0);
            IPS_SetParent($eventID, $SCRIPT_ID);
            IPS_SetIdent($eventID, 'ShieldTvFollowEvent');
            IPS_SetName(
                $eventID,
                'Wohnzimmer-Shield -> Philips TV'
            );
        }
    }

    if (
        $cacheID === false ||
        !IPS_VariableExists($cacheID) ||
        IPS_GetVariable($cacheID)['VariableType'] !== 3 ||
        $eventID === false ||
        !IPS_EventExists($eventID) ||
        IPS_GetEvent($eventID)['EventType'] !== 0
    ) {
        throw new RuntimeException(
            'Einrichtung fehlt. Skript manuell ausführen.'
        );
    }

    if ($MANUAL) {
        IPS_SetEventTrigger(
            $eventID,
            1,
            $ids['ShieldUpdated']
        );

        IPS_SetEventScript(
            $eventID,
            'IPS_RunScript(' . $SCRIPT_ID . ');'
        );

        IPS_SetEventActive($eventID, true);

        echo "TV-Kopplung eingerichtet.\n";
        echo "Ereignis-ID: $eventID\n";
    }

    $updated = (int) GetValue($ids['ShieldUpdated']);
    $rawPower = GetValue($ids['ShieldPower']);
    $mode = is_bool($rawPower) ? ($rawPower ? 'awake' : 'standby') : match (strtolower(trim((string)$rawPower))) {
        'awake', 'on' => 'awake',
        'standby', 'off', 'sleep', 'asleep' => 'standby',
        default => 'unknown'
    };
    $online = GetValue($ids['ShieldOnline']) === true;
    $now = time();

    $age = $updated > 0 ? $now - $updated : null;
    $reasons = [];
    if (!$online) { $reasons[] = 'ShieldOnline ist false (offline oder Abfrage pausiert).'; }
    if ($updated <= 0) { $reasons[] = 'Noch kein gültiger ShieldUpdated-Zeitstempel vorhanden.'; }
    elseif ($age > $MAX_STATUS_AGE) { $reasons[] = 'Letzte Shield-Meldung ist ' . $age . ' Sekunden alt (Grenze: ' . $MAX_STATUS_AGE . ').'; }
    elseif ($updated > $now + 5) { $reasons[] = 'Shield-Zeitstempel liegt in der Zukunft. Uhrzeit prüfen.'; }
    if (!in_array($mode, ['awake', 'standby'], true)) { $reasons[] = 'ShieldPower enthält weder awake noch standby.'; }

    if ($MANUAL) {
        echo 'Shield-Quelle: ' . IPS_GetName($shieldSource) . ' [' . $shieldSource . '] (Statusobjekte unter ' . $shieldContainer . ')' . "\n";
        echo 'ShieldOnline [' . $ids['ShieldOnline'] . ']: ' . ($online ? 'true' : 'false') . "\n";
        echo 'ShieldPower [' . $ids['ShieldPower'] . ']: ' . json_encode($rawPower, JSON_HEX_TAG | JSON_HEX_AMP) . ' -> ' . $mode . "\n";
        echo 'ShieldUpdated [' . $ids['ShieldUpdated'] . ']: ' . $updated
            . ($age === null ? '' : ' (Alter: ' . $age . ' s)') . "\n";
    }
    // Ungültige Quellen niemals als TV-Aus interpretieren; gültigen Cache erhalten.
    if ($reasons !== []) {
        if ($MANUAL) {
            echo implode("\n", $reasons) . "\nPhilips TV bleibt unverändert.\n";
        }
        return;
    }

    $raw = (string) GetValue($cacheID);
    $previous = json_decode($raw, true);

    if (!is_array($previous)) {
        $previous = [];
    }

    $previousMode = (string) ($previous['mode'] ?? '');
    $sameTarget = (int) ($previous['tvInstance'] ?? 0) === $tvInstanceID;
    $sameSource = ($previous['shieldSource'] ?? '') === $shieldSourceKey;

    // Manueller Start richtet nur ein und übernimmt den aktuellen Ausgangszustand.
    // Auch beim Umstieg auf die neue TV-Instanz niemals sofort schalten.
    if ($MANUAL || !$sameTarget || !$sameSource || !in_array($previousMode, ['awake', 'standby'], true)) {
        SetValue($cacheID, json_encode([
            'mode' => $mode, 'updated' => $updated, 'tvInstance' => $tvInstanceID, 'shieldSource' => $shieldSourceKey
        ], JSON_THROW_ON_ERROR));
        if ($MANUAL) {
            echo "Philips-Instanz: $tvInstanceID, Power-Variable: $tvPowerID\n";
            echo "Ausgangszustand gespeichert: $mode. Kein TV-Befehl gesendet.\n";
        }
        return;
    }
    if ($updated < (int) ($previous['updated'] ?? 0)) { return; }

    // Gleicher Zustand -> keine Aktion
    if ($previousMode === $mode) {
        return;
    }

    // Nur tatsächlicher Zustandswechsel
    $tvPower = ($mode === 'awake');

    // Power ist die neue Boolean-Aktionsvariable der eigenständigen TV-Instanz.
    // Online/PowerState des TV sind kein Auslöser: keine Rückkopplung und kein
    // Blockieren des Einschaltens allein wegen pausierter Standby-Abfragen.
    if (!RequestAction($tvPowerID, $tvPower)) {
        throw new RuntimeException('Philips-TV-Schaltbefehl fehlgeschlagen; Ausgangszustand bleibt erhalten.');
    }

    // Erst nach erfolgreichem Aktionsaufruf speichern
    SetValue(
        $cacheID,
        json_encode(
            ['mode' => $mode, 'updated' => $updated, 'tvInstance' => $tvInstanceID, 'shieldSource' => $shieldSourceKey],
            JSON_THROW_ON_ERROR
        )
    );

    IPS_LogMessage(
        'Shield TV-Kopplung',
        $previousMode . ' -> ' . $mode .
        ' | Philips-Instanz ' . $tvInstanceID . ' | Befehl ' . ($tvPower ? 'EIN' : 'STANDBY')
    );

} finally {
    IPS_SemaphoreLeave($key);
}
