<?php
declare(strict_types=1);
// Vollständig in ein NEUES temporäres Skript kopieren und einmal manuell ausführen.
// Nur Diagnose: keine Änderungen, keine Geräteaktionen, kein ApplyChanges.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Einmal manuell ausführen.'); }
$root = 55503; $master = 52627;
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
if (!IPS_InstanceExists($master) || IPS_GetParent($master) !== $root
    || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== $module) {
    throw new RuntimeException('Zentrale Instanz 52627 unter 55503 passt nicht. Nichts verändert.');
}
if (IPS_GetProperty($master, 'ConfigSource') !== 0 || IPS_GetProperty($master, 'View') !== 0) {
    throw new RuntimeException('52627 ist nicht die zentrale Startseiteninstanz. Nichts verändert.');
}
$modeName = static fn(int $mode): string => [0 => 'keine HTML-Darstellung', 1 => 'HTML nur als Kachel',
    2 => 'HTML als Kachel und im Vollbild', 3 => 'Formulardarstellung'][$mode] ?? 'Typ ' . $mode;
echo 'Startseite 52627: ' . $modeName((int)IPS_GetInstance($master)['InstanceVisualizationType']) . PHP_EOL;
$category = @IPS_GetObjectIDByIdent('SVHSRoomsHTML', $root);
if ($category === false || IPS_GetObject($category)['ObjectType'] !== 0) {
    echo 'Kein eigener HTML-Raumbereich SVHSRoomsHTML unter 55503 vorhanden.' . PHP_EOL;
    echo 'Ein Modulupdate allein erstellt keine fehlenden Rauminstanzen.' . PHP_EOL;
    echo 'Dafür ist das vollständige Einrichtungsskript Wohnansicht_0_12_KOMPLETT.php vorgesehen.' . PHP_EOL;
    return;
}
echo 'HTML-Raumbereich: ' . IPS_GetName($category) . ' | Kategorie ' . $category . PHP_EOL;
$links = [];
foreach (IPS_GetChildrenIDs($category) as $id) {
    if (IPS_LinkExists($id)) { $links[(int)IPS_GetLink($id)['TargetID']][] = $id; }
}
$count = 0;
foreach (IPS_GetChildrenIDs($category) as $id) {
    $object = IPS_GetObject($id);
    if (!str_starts_with($object['ObjectIdent'], 'SVHSRoom_') || !IPS_InstanceExists($id)
        || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== $module) { continue; }
    $count++;
    $source = (int)IPS_GetProperty($id, 'ConfigSource'); $view = (int)IPS_GetProperty($id, 'View');
    $room = (string)IPS_GetProperty($id, 'RoomFilter'); $active = (bool)IPS_GetProperty($id, 'ActiveView');
    $mode = (int)IPS_GetInstance($id)['InstanceVisualizationType'];
    $issues = [];
    if ($source !== $master) { $issues[] = 'falsche Konfigurationsquelle'; }
    if ($view !== 12) { $issues[] = 'keine Raumansicht (View muss 12 sein)'; }
    if ($room === '' || $object['ObjectIdent'] !== 'SVHSRoom_' . substr(sha1($room), 0, 14)) { $issues[] = 'Raumfilter passt nicht zum Ident'; }
    if (!$active) { $issues[] = 'Ansicht pausiert'; }
    if ($mode !== 2) { $issues[] = 'HTML-Vollbild nicht aktiviert; Modul aktualisieren und 52627 übernehmen'; }
    if (empty($links[$id])) { $issues[] = 'keine Raumverknüpfung'; }
    echo IPS_GetName($id) . ' | Instanz ' . $id . ' | Raumfilter ' . ($room !== '' ? $room : '(leer)')
        . ' | View ' . $view . ' | Quelle ' . $source . ' | ' . $modeName($mode)
        . ' | Links ' . implode(', ', $links[$id] ?? []) . PHP_EOL;
    echo $issues ? '  Prüfen: ' . implode('; ', $issues) . PHP_EOL : '  Rauminstanz korrekt eingerichtet.' . PHP_EOL;
}
echo 'Gefundene HTML-Rauminstanzen: ' . $count . PHP_EOL;
$pv = @IPS_GetObjectIDByIdent('SVHSTile_7', $root);
if ($pv !== false && IPS_InstanceExists($pv) && IPS_GetInstance($pv)['ModuleInfo']['ModuleID'] === $module
    && IPS_GetProperty($pv, 'ConfigSource') === $master) {
    echo 'PV-Details | Instanz ' . $pv . ' | View ' . IPS_GetProperty($pv, 'View') . ' | '
        . $modeName((int)IPS_GetInstance($pv)['InstanceVisualizationType']) . PHP_EOL;
}
echo 'Die gespeicherte Darstellungswahl im Browser lässt sich damit nicht prüfen.' . PHP_EOL;
echo 'Im Darstellungseditor muss für die HTML-Rauminstanz Instanzspezifische Darstellung gewählt sein.' . PHP_EOL;
echo 'Die bisherigen nativen Raumseiten werden vom HTML-Modul nicht umgestaltet.' . PHP_EOL;
echo 'Keine Variablen, Verknüpfungen oder Geräte geändert.' . PHP_EOL;
