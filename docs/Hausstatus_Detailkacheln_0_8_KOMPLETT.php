<?php
// Nach dem Modulupdate auf 0.8 in einem NEUEN temporaeren PHP-Skript ausfuehren.
// Dies ist ein vollstaendiges Einrichtungsskript, kein Ersatz fuer Skript 49024.
// Es sendet keine Schaltbefehle. Vorhandene Raeume und fremde Kacheln bleiben bestehen.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Bitte einmal manuell ausfuehren.'); }
$root = 55503; $master = 52627; $mainLink = 29867;
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
$details = [8 => 'Bewegung', 9 => 'Temperaturen', 10 => 'Wetter'];
if (!IPS_ObjectExists($root) || IPS_GetObject($root)['ObjectType'] !== 0
    || !IPS_InstanceExists($master) || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== $module
    || IPS_GetParent($master) !== $root) {
    throw new RuntimeException('Basis 55503 oder zentrale Hausstatus-Instanz 52627 stimmt nicht. Nichts eingerichtet.');
}
$config = json_decode(IPS_GetConfiguration($master), true);
if (!is_array($config) || !isset($config['View'], $config['ConfigSource'], $config['SeparateDetails'], $config['ActiveView'], $config['AwningPosition'])
    || $config['ConfigSource'] !== 0) {
    throw new RuntimeException('Zuerst Modul auf 0.8 aktualisieren. 52627 muss die eigene zentrale Konfiguration verwenden.');
}
if (!IPS_LinkExists($mainLink) || IPS_GetParent($mainLink) !== $root || IPS_GetLink($mainLink)['TargetID'] !== $master) {
    throw new RuntimeException('Hausstatus-Link 29867 stimmt nicht. Nichts eingerichtet.');
}
if (!IPS_VariableExists(47467) || !in_array(IPS_GetVariable(47467)['VariableType'], [0, 1], true)) {
    throw new RuntimeException('Tuerkontakt 47467 fehlt oder hat einen anderen Variablentyp. Nichts eingerichtet.');
}
// Alle eigenen Identitaeten pruefen, bevor Objekte oder Einstellungen geaendert werden.
$owned = [];
foreach ([1, 2, 4, 5, 6, 7, 8, 9, 10, 11] as $view) {
    $instance = @IPS_GetObjectIDByIdent('SVHSTile_' . $view, $root);
    $link = @IPS_GetObjectIDByIdent('SVHSTileLink_' . $view, $root);
    if ($instance !== false && (!IPS_InstanceExists($instance)
        || IPS_GetInstance($instance)['ModuleInfo']['ModuleID'] !== $module
        || IPS_GetProperty($instance, 'ConfigSource') !== $master)) {
        throw new RuntimeException('Kachel-Ident ' . $view . ' ist bereits anders belegt. Nichts eingerichtet.');
    }
    if ($link !== false && (!IPS_LinkExists($link) || $instance === false || IPS_GetLink($link)['TargetID'] !== $instance)) {
        throw new RuntimeException('Link-Ident ' . $view . ' ist bereits anders belegt. Nichts eingerichtet.');
    }
    $owned[$view] = ['instance' => $instance, 'link' => $link];
}
$kept = [];
foreach (IPS_GetChildrenIDs($root) as $id) {
    $o = IPS_GetObject($id);
    if (!$o['ObjectIsHidden'] && in_array($o['ObjectType'], [0, 1, 2, 6], true)
        && $id !== $master && $id !== $mainLink && !preg_match('/^SVHSTile(?:Link)?_\d+$/', $o['ObjectIdent'])) {
        $kept[$id] = IPS_GetName($id);
    }
}
// Bekannte, zuvor leere Bewegungsquellen vervollstaendigen; eigene Auswahlen erhalten.
$sensors = json_decode($config['MotionSensors'], true);
if (is_array($sensors)) {
    $known = ['Schlafzimmer Praesenz' => 22412, "Schlafzimmer Pr\u{e4}senz" => 22412, 'Keller' => 16931];
    $changed = false;
    foreach ($sensors as &$sensor) {
        if (!is_array($sensor) || !isset($sensor['Name'], $sensor['Variable']) || $sensor['Variable'] !== 0) { continue; }
        $id = $known[$sensor['Name']] ?? 0;
        if ($id > 0 && IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0) {
            $sensor['Variable'] = $id; $changed = true;
        }
    }
    unset($sensor);
    if ($changed) { IPS_SetProperty($master, 'MotionSensors', json_encode($sensors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); }
}
// Detailansichten vorbereiten. Der bisherige Hausstatus bleibt bis dahin sichtbar.
foreach ($details as $view => $name) {
    $id = $owned[$view]['instance'];
    if ($id === false) {
        $id = IPS_CreateInstance($module);
        IPS_SetParent($id, $root); IPS_SetIdent($id, 'SVHSTile_' . $view); IPS_SetHidden($id, true);
    }
    IPS_SetName($id, $name);
    IPS_SetProperty($id, 'View', $view); IPS_SetProperty($id, 'ConfigSource', $master); IPS_SetProperty($id, 'ActiveView', true);
    if (!IPS_ApplyChanges($id)) { throw new RuntimeException('Detailkachel konnte nicht eingerichtet werden: ' . $name); }
    $link = $owned[$view]['link'];
    if ($link === false) {
        $link = IPS_CreateLink(); IPS_SetParent($link, $root); IPS_SetIdent($link, 'SVHSTileLink_' . $view); IPS_SetHidden($link, true);
    }
    IPS_SetName($link, $name); IPS_SetLinkTargetID($link, $id); IPS_SetPosition($link, ($view - 7) * 10);
    $owned[$view] = ['instance' => $id, 'link' => $link];
}
IPS_SetProperty($master, 'DoorContact', 47467);
IPS_SetProperty($master, 'View', 0); IPS_SetProperty($master, 'SeparateDetails', true); IPS_SetProperty($master, 'ActiveView', true);
if (!IPS_ApplyChanges($master)) { throw new RuntimeException('Hausstatus-Konfiguration konnte nicht uebernommen werden.'); }
IPS_SetName($mainLink, 'Hausstatus'); IPS_SetPosition($mainLink, 0); IPS_SetHidden($mainLink, false); IPS_SetHidden($master, true);
foreach ($owned as $view => $item) {
    if (isset($details[$view])) { IPS_SetHidden($item['link'], false); continue; }
    if ($item['link'] !== false) { IPS_SetHidden($item['link'], true); }
    if ($item['instance'] !== false) { IPS_SetProperty($item['instance'], 'ActiveView', false); IPS_ApplyChanges($item['instance']); }
}
echo 'Fertig: Hausstatus, Bewegung, Temperaturen und Wetter als vier Kacheln.' . PHP_EOL;
echo 'Im Hausstatus: Anwesenheit/Alarm zuerst, Haustuer danach, Licht, Cinema, Geraete, PV sowie Markise und Dachfenster.' . PHP_EOL;
echo 'Tuerkontakt 47467 getrennt vom Schlossstatus. Vorhandene Raum- und Geraetekacheln bleiben erhalten.' . PHP_EOL;
foreach ($kept as $id => $name) {
    if (IPS_GetObject($id)['ObjectIsHidden']) { throw new RuntimeException('Vorhandener Bereich wurde unerwartet ausgeblendet: ' . $id); }
    echo 'Weiter vorhanden: ' . $name . ' (' . $id . ')' . PHP_EOL;
}
echo 'Kachelvisualisierung jetzt neu laden. Keine Motor-, Licht-, AVR- oder Tuerbefehle wurden gesendet.' . PHP_EOL;
