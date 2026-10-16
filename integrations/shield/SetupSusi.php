<?php
declare(strict_types=1);

// Vollständiges Einrichtungsskript: einmal in der Symcon-Konsole ausführen.
// Voraussetzung: aktuelle Moduldateien mit ShieldCompact installiert.
$MASTER_ID = 52627;
// Optional: Objekt-ID von Susis bisherigem Link eintragen. 0 = nur Ansicht anlegen.
$SUSI_LINK_ID = 0;

$moduleID = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
if (!IPS_InstanceExists($MASTER_ID)
    || IPS_GetInstance($MASTER_ID)['ModuleInfo']['ModuleID'] !== $moduleID
    || (int)IPS_GetProperty($MASTER_ID, 'ConfigSource') !== 0) {
    throw new RuntimeException('52627 ist keine zentrale Hausstatus-Instanz. Einrichtung abgebrochen.');
}
$configuration = json_decode(IPS_GetConfiguration($MASTER_ID), true, 512, JSON_THROW_ON_ERROR);
if (!array_key_exists('ShieldCompact', $configuration)) {
    throw new RuntimeException('Zuerst die aktuelle ShieldSupport.php und form.json sowie module.html installieren und bei Master Änderungen übernehmen.');
}
// Validate an optional existing link before creating or changing anything.
if ($SUSI_LINK_ID !== 0) {
    if (!IPS_LinkExists($SUSI_LINK_ID) || (int)IPS_GetLink($SUSI_LINK_ID)['TargetID'] !== $MASTER_ID) {
        throw new RuntimeException('Der angegebene Susi-Link zeigt nicht auf Master. Einrichtung abgebrochen.');
    }
}
$parent = IPS_GetParent($MASTER_ID);
$ident = 'SVHSSusiCompact_' . $MASTER_ID;
$susi = @IPS_GetObjectIDByIdent($ident, $parent);
if ($susi !== false) {
    if (!IPS_InstanceExists($susi) || IPS_GetInstance($susi)['ModuleInfo']['ModuleID'] !== $moduleID
        || (int)IPS_GetProperty($susi, 'ConfigSource') !== $MASTER_ID) {
        throw new RuntimeException('Die Objektkennung für Susi ist bereits anderweitig belegt.');
    }
} else {
    $susi = IPS_CreateInstance($moduleID);
    IPS_SetParent($susi, $parent);
    IPS_SetIdent($susi, $ident);
    IPS_SetName($susi, 'Hausstatus Susi');
    IPS_SetProperty($susi, 'ConfigSource', $MASTER_ID);
}
IPS_SetProperty($susi, 'View', 0);
IPS_SetProperty($susi, 'ShieldCompact', true);
IPS_SetProperty($susi, 'ActiveView', true);
IPS_ApplyChanges($susi);

if ($SUSI_LINK_ID !== 0) {
    IPS_SetLinkTargetID($SUSI_LINK_ID, $susi);
    echo 'Susis Link ' . $SUSI_LINK_ID . ' zeigt jetzt auf ' . $susi . '.' . PHP_EOL;
} else {
    echo 'Im bisherigen Link von Susi das Zielobjekt auf diese ID umstellen: ' . $susi . PHP_EOL;
}
echo 'Hausstatus Susi: ' . $susi . PHP_EOL;
echo 'Datenquelle: Master ' . $MASTER_ID . PHP_EOL;
echo 'Shield: nur Online und Diagnose. Master und Sven wurden nicht verändert.' . PHP_EOL;

