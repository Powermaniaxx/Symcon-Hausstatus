<?php
declare(strict_types=1);

/**
 * Bereinigung ausschließlich generierter Philips-/Shield-Statusvariablen
 * auf Anzeige-Instanzen der Hausstatus-Datenquelle 52627.
 * Master 52627, andere Datenquellen, der Ordner "fehlerhaft" (38631),
 * Statusvariablen mit Links/Aktionen/aktivem Archiv werden NIE gelöscht.
 *
 * Vorher das Hausstatus-Modul auf >= 0.81 aktualisieren, sonst werden
 * diese Variablen beim Neuerstellen einer Kachel erneut angelegt.
 */
$hausstatusMaster = 52627;
$hausstatusModule = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
$backupCategory = 38631;
$wirklichLoeschen = true;

if (!IPS_InstanceExists($hausstatusMaster)
    || IPS_GetInstance($hausstatusMaster)['ModuleInfo']['ModuleID'] !== $hausstatusModule
    || (int)IPS_GetProperty($hausstatusMaster, 'ConfigSource') !== 0) {
    throw new RuntimeException('Die zentrale Datenquelle 52627 ist nicht eindeutig erkannt. Nichts gelöscht.');
}
if (isset($hausstatusMaster) && !$wirklichLoeschen) {
    echo "PRÜFMODUS: keine Objekte werden verändert.\n";
}
$names = ['Online', 'Power', 'App', 'Playback', 'Title', 'Channel', 'Subtitle', 'Error', 'Updated'];
$prefixes = ['Shield', 'ShieldDressing', 'ShieldBedroom', 'PhilipsTv'];
$allowed = [];
foreach ($prefixes as $prefix) {
    foreach ($names as $field) { $allowed[$prefix . $field] = true; }
}

$linkedTargets = [];
foreach (IPS_GetObjectList() as $id) {
    if (IPS_LinkExists($id)) {
        $linkedTargets[(int)IPS_GetLink($id)['TargetID']] = true;
    }
}
$archiveIDs = function_exists('AC_GetLoggingStatus')
    ? IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}') : [];
$inBackup = static function (int $instanceID) use ($backupCategory): bool {
    $id = $instanceID;
    $loops = 0;
    while ($id > 0 && $loops++ < 25) {
        if ($id === $backupCategory) { return true; }
        $id = IPS_GetParent($id);
    }
    return false;
};

$found = 0;
$processed = 0;
$skipped = 0;
foreach (IPS_GetInstanceListByModuleID($hausstatusModule) as $instanceID) {
    if ($instanceID === $hausstatusMaster) { continue; }
    if ((int)IPS_GetProperty($instanceID, 'ConfigSource') !== $hausstatusMaster) { continue; }
    if ($inBackup($instanceID)) {
        echo 'Sicherung bleibt unberührt: ' . $instanceID . ' / ' . IPS_GetName($instanceID) . PHP_EOL;
        continue;
    }
    foreach (IPS_GetChildrenIDs($instanceID) as $variableID) {
        if (!IPS_VariableExists($variableID)) { continue; }
        $object = IPS_GetObject($variableID);
        $ident = (string)$object['ObjectIdent'];
        if (!isset($allowed[$ident])) { continue; }
        $found++;
        $reasons = [];
        if (isset($linkedTargets[$variableID])) { $reasons[] = 'Verknüpfung vorhanden'; }
        $v = IPS_GetVariable($variableID);
        if ((int)$v['VariableAction'] > 0 || (int)$v['VariableCustomAction'] > 0) {
            $reasons[] = 'Bedienaktion vorhanden';
        }
        foreach ($archiveIDs as $archive) {
            try {
                if (AC_GetLoggingStatus($archive, $variableID)) {
                    $reasons[] = 'Archivierung aktiv'; break;
                }
            } catch (Throwable $e) {
                $reasons[] = 'Archivprüfung unklar'; break;
            }
        }
        $name = 'Instanz ' . $instanceID . ' (' . IPS_GetName($instanceID)
            . '), Variable ' . $variableID . ' (' . $ident . ')';
        if ($reasons) {
            $skipped++;
            echo 'NICHT LÖSCHEN: ' . $name . ' – ' . implode(', ', $reasons) . PHP_EOL;
            continue;
        }
        if (!$wirklichLoeschen) {
            echo 'BEREINIGBAR: ' . $name . PHP_EOL;
            continue;
        }
        IPS_DeleteVariable($variableID);
        $processed++;
        echo 'ENTFERNT: ' . $name . PHP_EOL;
    }
}

echo PHP_EOL . 'Gefundene Kopien: ' . $found
    . ' | aus Sicherheitsgründen übersprungen: ' . $skipped
    . ' | tatsächlich gelöscht: ' . $processed . PHP_EOL;
echo 'Der Master 52627 und der Sicherungsordner 38631 wurden nicht verändert.' . PHP_EOL;
