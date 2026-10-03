<?php
// Erst nach Installation des Moduls einmal manuell ausführen.
// Erstellt die Instanz unter 55503 und stellt Hausstatus-Link 29867 um.
// Stoppt den alten Kachel-HTML-Timer 49024 nach erfolgreicher Einrichtung.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Bitte manuell ausführen.'); }
$root = 55503;
$moduleID = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
if (!IPS_ModuleExists($moduleID)) { throw new RuntimeException('Das Modul Sven Hausstatus ist noch nicht installiert.'); }
if (!IPS_ObjectExists($root) || IPS_GetObject($root)['ObjectType'] !== 0) {
    throw new RuntimeException('Kachel-Basis 55503 fehlt.');
}
$link = 29867;
if (!IPS_LinkExists($link) || IPS_GetObject($link)['ParentID'] !== $root) {
    throw new RuntimeException('Hausstatus-Link 29867 unter 55503 fehlt.');
}
$instance = @IPS_GetObjectIDByIdent('SvenHausstatusBedienung', $root);
if ($instance !== false && (!IPS_InstanceExists($instance)
    || IPS_GetInstance($instance)['ModuleInfo']['ModuleID'] !== $moduleID)) {
    throw new RuntimeException('Der vorgesehene Ident ist bereits anders belegt.');
}
if ($instance === false) {
    $instance = IPS_CreateInstance($moduleID);
    IPS_SetParent($instance, $root);
    IPS_SetIdent($instance, 'SvenHausstatusBedienung');
    IPS_SetName($instance, 'Hausstatus mit Bedienung');
    IPS_SetPosition($instance, 5);
}
IPS_ApplyChanges($instance);
// Die Instanz selbst dient als Kachel; Link-Identität und Platz bleiben erhalten.
IPS_SetHidden($instance, true);
IPS_SetLinkTargetID($link, $instance);
IPS_SetName($link, 'Hausstatus');
$oldScript = 49024;
if (IPS_ScriptExists($oldScript)) {
    $ancestor = $oldScript;
    while ($ancestor > 0 && $ancestor !== $root) {
        $ancestor = (int)IPS_GetObject($ancestor)['ParentID'];
    }
    if ($ancestor === $root) { IPS_SetScriptTimer($oldScript, 0); }
}
echo "Hausstatus-Modul eingerichtet. Instanz-ID: $instance\n";
echo "Instanz öffnen, Variablen prüfen und gewünschte Bedienung aktivieren.\n";
echo "Kachelansicht neu laden.\n";
