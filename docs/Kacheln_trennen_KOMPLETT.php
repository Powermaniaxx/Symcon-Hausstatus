<?php
// Nach Modulupdate auf 0.5 in einem NEUEN temporaeren Skript manuell ausfuehren.
// Bestehende Konfiguration 52627 bleibt erhalten und ist Quelle aller neuen Kacheln.
// Erneute Ausfuehrung verwendet dieselben Objekte. Es werden keine Objekte geloescht.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Bitte manuell ausfuehren.'); }
$root = 55503;
$master = 52627;
$oldLink = 29867;
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
$views = [1 => 'Anwesenheit und Alarm', 2 => 'Haustuer', 4 => 'Geraete',
    5 => 'Wohnzimmerlicht', 6 => 'Cinema 40', 7 => 'PV-Anlage',
    8 => 'Bewegungsmelder', 9 => 'Raumtemperaturen', 10 => 'Wetter'];
if (!IPS_ObjectExists($root) || IPS_GetObject($root)['ObjectType'] !== 0) { throw new RuntimeException('Basis 55503 fehlt.'); }
if (!IPS_InstanceExists($master) || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== $module) {
    throw new RuntimeException('Zentrale Hausstatus-Instanz 52627 fehlt oder hat ein anderes Modul.');
}
$config = json_decode(IPS_GetConfiguration($master), true);
if (!isset($config['View'], $config['ConfigSource']) || $config['ConfigSource'] !== 0) {
    throw new RuntimeException('Zuerst Modul auf 0.5 aktualisieren. Instanz 52627 muss eigene Einstellungen verwenden.');
}
if (!IPS_LinkExists($oldLink) || IPS_GetObject($oldLink)['ParentID'] !== $root
    || IPS_GetLink($oldLink)['TargetID'] !== $master) {
    throw new RuntimeException('Der bisherige Link 29867 zeigt nicht auf 52627 unter 55503. Keine Aufteilung ausgefuehrt.');
}
// Detect naming collisions before creating any new objects.
foreach ($views as $view => $name) {
    $id = @IPS_GetObjectIDByIdent('SVHSTile_' . $view, $root);
    if ($id !== false && (!IPS_InstanceExists($id) || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== $module)) {
        throw new RuntimeException('Kachel-Ident bereits anders belegt: ' . $view);
    }
    $link = @IPS_GetObjectIDByIdent('SVHSTileLink_' . $view, $root);
    if ($link !== false && !IPS_LinkExists($link)) { throw new RuntimeException('Link-Ident bereits anders belegt: ' . $view); }
}
// Bind the existing count only when its identity is unambiguous.
if ((int)$config['BatteryWarnings'] === 0) {
    $candidates = [];
    foreach (IPS_GetVariableList() as $candidate) {
        if (IPS_GetName($candidate) === "Anzahl Ger\u{e4}tewarnungen"
            && IPS_GetVariable($candidate)['VariableType'] === 1
            && IPS_ScriptExists(IPS_GetParent($candidate))) { $candidates[] = $candidate; }
    }
    if (count($candidates) === 1) {
        IPS_SetProperty($master, 'BatteryWarnings', $candidates[0]);
        IPS_ApplyChanges($master);
        echo 'Geraetewarnungen zugeordnet: ' . $candidates[0] . PHP_EOL;
    } else {
        echo 'Warnanzahl bitte in 52627 auswaehlen: keine eindeutige Variable gefunden.' . PHP_EOL;
    }
}

foreach ($views as $view => $name) {
    $id = @IPS_GetObjectIDByIdent('SVHSTile_' . $view, $root);
    if ($id === false) {
        $id = IPS_CreateInstance($module);
        IPS_SetParent($id, $root);
        IPS_SetIdent($id, 'SVHSTile_' . $view);
    }
    IPS_SetName($id, $name);
    IPS_SetHidden($id, true);
    IPS_SetProperty($id, 'View', $view);
    IPS_SetProperty($id, 'ActiveView', true);
    IPS_SetProperty($id, 'ConfigSource', $master);
    if (!IPS_ApplyChanges($id)) { throw new RuntimeException('Konfiguration konnte nicht uebernommen werden: ' . $name); }
    $link = @IPS_GetObjectIDByIdent('SVHSTileLink_' . $view, $root);
    if ($link === false) {
        $link = IPS_CreateLink();
        IPS_SetParent($link, $root);
        IPS_SetIdent($link, 'SVHSTileLink_' . $view);
    }
    IPS_SetName($link, $name);
    IPS_SetLinkTargetID($link, $id);
    IPS_SetPosition($link, 10 + $view);
    IPS_SetHidden($link, false);
    echo $name . ': Instanz ' . $id . ', Link ' . $link . PHP_EOL;
}
// Hide the old combined tile only after all separate tiles were prepared.
IPS_SetHidden($oldLink, true);
IPS_SetHidden($master, true);
echo PHP_EOL . 'Aufteilung fertig. Kachelansicht neu laden.' . PHP_EOL;
echo 'Quellen und Bedienoptionen weiterhin in 52627 einstellen, auch Cinema-Quelle und Lautstaerke.' . PHP_EOL;
echo 'Rueckkehr zur Sammelkachel: Link 29867 wieder einblenden und die neuen Links ausblenden.' . PHP_EOL;
