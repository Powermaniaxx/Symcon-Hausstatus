<?php
// Nach Modulupdate auf 0.6 einmal in einem NEUEN temporaeren Skript ausfuehren.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Bitte manuell ausfuehren.'); }
$root = 55503; $master = 52627; $link = 29867; $volume = 45376;
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
if (!IPS_InstanceExists($master) || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== $module) {
    throw new RuntimeException('Zentrale Instanz 52627 fehlt oder verwendet ein anderes Modul.');
}
$config = json_decode(IPS_GetConfiguration($master), true);
if (!isset($config['View'], $config['ConfigSource'], $config['CinemaVolume'], $config['ActiveView']) || $config['ConfigSource'] !== 0) {
    throw new RuntimeException('Zuerst Modul aktualisieren. 52627 muss die zentrale eigene Konfiguration sein.');
}
if (!IPS_LinkExists($link) || IPS_GetObject($link)['ParentID'] !== $root || IPS_GetLink($link)['TargetID'] !== $master) {
    throw new RuntimeException('Hausstatus-Link 29867 unter 55503 stimmt nicht. Nichts umgestellt.');
}
if (!IPS_VariableExists($volume) || !in_array(IPS_GetVariable($volume)['VariableType'], [1, 2], true)) {
    throw new RuntimeException('Lautstaerkevariable 45376 fehlt oder ist keine Zahl. Nichts umgestellt.');
}
$generated = [];
foreach ([1, 2, 4, 5, 6, 7, 8, 9, 10] as $view) {
    $partLink = @IPS_GetObjectIDByIdent('SVHSTileLink_' . $view, $root);
    $part = @IPS_GetObjectIDByIdent('SVHSTile_' . $view, $root);
    if ($partLink === false && $part === false) { continue; }
    if ($partLink === false || $part === false || !IPS_LinkExists($partLink) || !IPS_InstanceExists($part)
        || IPS_GetLink($partLink)['TargetID'] !== $part
        || IPS_GetInstance($part)['ModuleInfo']['ModuleID'] !== $module
        || IPS_GetProperty($part, 'ConfigSource') !== $master) {
        throw new RuntimeException('Aufgeteilte Kachel ' . $view . ' ist anders belegt. Nichts umgestellt.');
    }
    $generated[] = ['link' => $partLink, 'instance' => $part];
}
IPS_SetProperty($master, 'View', 0);
IPS_SetProperty($master, 'ActiveView', true);
IPS_SetProperty($master, 'CinemaVolume', $volume);
if (!IPS_ApplyChanges($master)) { throw new RuntimeException('Zentrale Konfiguration konnte nicht uebernommen werden.'); }
IPS_SetName($link, 'Hausstatus');
IPS_SetHidden($link, false);
IPS_SetHidden($master, true);
foreach ($generated as $part) {
    IPS_SetHidden($part['link'], true);
    IPS_SetProperty($part['instance'], 'ActiveView', false);
    IPS_ApplyChanges($part['instance']);
}
echo 'Gemeinsame Hausstatus-Kachel wieder aktiv. Kachelansicht neu laden.' . PHP_EOL;
echo 'Cinema-Lautstaerke 45376 zugeordnet. Quelle weiterhin in 52627 auswaehlen.' . PHP_EOL;
echo 'Aufgeteilte Kacheln ausgeblendet; vorhandene Einstellungen und Objekte bleiben erhalten.' . PHP_EOL;
