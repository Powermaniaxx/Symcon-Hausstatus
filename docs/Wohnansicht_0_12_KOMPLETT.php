<?php
declare(strict_types=1);
// Nach Modulupdate auf 0.12 vollständig in ein NEUES temporäres Skript kopieren.
// Liest bestehende Raumverknüpfungen. Bearbeitet nur eigene Kacheln unter 55503.
// Kein Ersatz für 49024, kein Start der alten Visualisierung, keine Gerätebefehle.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Einmal manuell ausführen.'); }
$root = 55503; $master = 52627; $mainLink = 29867;
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
if (!IPS_ObjectExists($root) || IPS_GetObject($root)['ObjectType'] !== 0
    || !IPS_InstanceExists($master) || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== $module
    || IPS_GetParent($master) !== $root || !IPS_LinkExists($mainLink)
    || IPS_GetParent($mainLink) !== $root || IPS_GetLink($mainLink)['TargetID'] !== $master) {
    throw new RuntimeException('Basis 55503, Instanz 52627 oder Link 29867 stimmt nicht. Nichts eingerichtet.');
}
$config = json_decode(IPS_GetConfiguration($master), true, 512, JSON_THROW_ON_ERROR);
if (!array_key_exists('Raining', $config) || !array_key_exists('DiningState', $config) || !array_key_exists('RoomEntries', $config) || !array_key_exists('RoomFilter', $config) || $config['ConfigSource'] !== 0
    || !function_exists('SVHS_GetNavigationTargets') || !function_exists('SVHS_GetRoomDefaults')) { throw new RuntimeException('Zuerst Modul 0.12 laden und bei 52627 Änderungen übernehmen.'); }
foreach ([25259, 35313] as $id) {
    if (!IPS_VariableExists($id)) { throw new RuntimeException('Statusvariable ' . $id . ' fehlt. Nichts eingerichtet.'); }
}
$defaults = json_decode(SVHS_GetRoomDefaults($master), true, 512, JSON_THROW_ON_ERROR);
$entries = json_decode($config['RoomEntries'], true, 512, JSON_THROW_ON_ERROR);
if (!is_array($entries) || !is_array($defaults)) { throw new RuntimeException('Raumkonfiguration ist keine Liste.'); }
$snapshot = [];
foreach (IPS_GetChildrenIDs($root) as $id) {
    $o = IPS_GetObject($id);
    if ($id === $master || $id === $mainLink || str_starts_with($o['ObjectIdent'], 'SVHS')) { continue; }
    $snapshot[$id] = ['parent' => IPS_GetParent($id), 'name' => IPS_GetName($id), 'hidden' => $o['ObjectIsHidden'], 'position' => $o['ObjectPosition']];
}
$labelUmlauts = static function (string $label): string {
    // Only visible labels, never object IDs, values, property keys or identifiers.
    $words = ['Buero' => 'Büro', 'Kueche' => 'Küche', 'Praesenz' => 'Präsenz',
        'Haustuer' => 'Haustür', 'Tuer' => 'Tür', 'Tuerkontakt' => 'Türkontakt',
        'Tuerfreigabe' => 'Türfreigabe', 'Geraete' => 'Geräte', 'Geraetewarnungen' => 'Gerätewarnungen',
        'Lautstaerke' => 'Lautstärke', 'Helligkeitsuebergang' => 'Helligkeitsübergang',
        'Oeffnen' => 'Öffnen', 'oeffnen' => 'öffnen', 'Schliessen' => 'Schließen',
        'schliessen' => 'schließen', 'Oeffnung' => 'Öffnung', 'Schliessung' => 'Schließung',
        'Uebergang' => 'Übergang', 'Ueber' => 'Über', 'ueber' => 'über'];
    return preg_replace_callback('/\b(' . implode('|', array_map(static fn(string $word): string => preg_quote($word, '/'), array_keys($words))) . ')\b/u',
        static fn(array $match): string => $words[$match[1]], $label) ?? $label;
};
$nameMap = ['Büro neu' => 'Büro'];
$normalRoom = static function (string $name) use ($nameMap, $labelUmlauts): string {
    $name = $labelUmlauts(trim($name));
    return $nameMap[$name] ?? $name;
};
$groupFor = static function (int $target, string $group): string {
    if ($group !== '') { return $group; }
    $text = strtolower(IPS_GetName($target) . ' ' . IPS_GetLocation($target));
    foreach (['Medien' => ['heos', 'marantz', 'surround'], 'Wasserbett' => ['wasserbett'],
        'Heizung' => ['heizung', 'set_point'], 'Klima' => ['temperatur', 'temperature', 'humidity', 'feuchte'],
        'Sensoren' => ['motion', 'bewegung', 'presence', 'präsenz', 'illumination'],
        'Licht' => ['licht', 'lampe', 'hue'], 'Energie' => ['verbrauch', 'kosten', 'energie']] as $name => $words) {
        foreach ($words as $word) { if (str_contains($text, $word)) { return $name; } }
    }
    return 'Weitere Geräte';
};
$allowedTarget = static function (int $id) use ($module): bool {
    if (!IPS_ObjectExists($id)) { return false; }
    $o = IPS_GetObject($id);
    if (!in_array($o['ObjectType'], [1, 2, 3, 5], true)) { return false; }
    if ($o['ObjectType'] === 1 && IPS_GetInstance($id)['ModuleInfo']['ModuleID'] === $module) { return false; }
    $path = strtolower(IPS_GetLocation($id));
    foreach (['alt, nicht mehr benötigt', 'inaktiv derzeit', 'hue unötig', 'hue unnötig'] as $word) {
        if (str_contains($path, $word)) { return false; }
    }
    if ($o['ObjectType'] === 2) {
        $v = IPS_GetVariable($id);
        $p = IPS_GetVariablePresentation($id);
        if (str_contains(strtolower($v['VariableProfile'] . ' ' . $v['VariableCustomProfile']), 'htmlbox')
            || ($p['PRESENTATION'] ?? '') === '{9DE1D610-5106-97FB-714D-1AADEDF8377A}') { return false; }
    }
    return true;
};
$found = []; $visited = [];
$collect = function (int $category, string $room, string $group = '', int $depth = 0) use (&$collect, &$found, &$visited, $allowedTarget, $groupFor, $labelUmlauts): void {
    if ($depth > 10 || isset($visited[$room . ':' . $category])) { return; }
    $visited[$room . ':' . $category] = true;
    $children = IPS_GetChildrenIDs($category);
    usort($children, static fn(int $a, int $b): int => IPS_GetObject($a)['ObjectPosition'] <=> IPS_GetObject($b)['ObjectPosition']);
    foreach ($children as $child) {
        $o = IPS_GetObject($child);
        if ($o['ObjectIsHidden'] || $o['ObjectIsDisabled'] || str_starts_with($o['ObjectIdent'], 'SVHS')) { continue; }
        if ($o['ObjectType'] === 0) {
            $name = $labelUmlauts(trim(IPS_GetName($child)));
            if (!in_array(strtolower($name), ['box', 'box klein', 'box anzucht'], true)) { $collect($child, $room, $name, $depth + 1); }
            continue;
        }
        $target = $o['ObjectType'] === 6 ? (int)IPS_GetLink($child)['TargetID'] : $child;
        if (IPS_ObjectExists($target) && IPS_GetObject($target)['ObjectType'] === 0) { $collect($target, $room, $group, $depth + 1); continue; }
        if (!$allowedTarget($target)) { continue; }
        $key = $room . ':' . $target;
        if (isset($found[$key])) { continue; }
        $caption = trim(IPS_GetName($child));
        if ($caption === '' || str_contains($caption, 'Unnamed Object')) { $caption = IPS_GetName($target); }
        $found[$key] = ['Room' => $room, 'Group' => $groupFor($target, $group), 'Name' => $labelUmlauts($caption), 'Target' => $target, 'Operate' => true];
    }
};
$sources = [];
// Current native rooms first; older room links are only read as a second source.
foreach ([52891, 10577] as $source) {
    if (!IPS_ObjectExists($source) || IPS_GetObject($source)['ObjectType'] !== 0) { continue; }
    $sources[] = $source;
    foreach (IPS_GetChildrenIDs($source) as $roomID) {
        $o = IPS_GetObject($roomID);
        if ($o['ObjectType'] !== 0 || $o['ObjectIsHidden'] || $o['ObjectIsDisabled']) { continue; }
        $room = $normalRoom(IPS_GetName($roomID));
        if ($room !== '') { $collect($roomID, $room); }
    }
}
$keys = [];
foreach ($entries as &$entry) {
    if (!is_array($entry) || !isset($entry['Room'], $entry['Group'], $entry['Name'], $entry['Target'])
        || !is_string($entry['Room']) || trim($entry['Room']) === '' || !is_string($entry['Group'])
        || !is_string($entry['Name']) || !is_int($entry['Target']) || $entry['Target'] <= 0) {
        throw new RuntimeException('Ein vorhandener Raumeintrag ist ungültig. Nichts eingerichtet.');
    }
    $entry['Room'] = $normalRoom($entry['Room']);
    $entry['Group'] = $labelUmlauts($entry['Group']); $entry['Name'] = $labelUmlauts($entry['Name']);
    $keys[$entry['Room'] . ':' . $entry['Target']] = true;
}
unset($entry);
$captionLists = [];
foreach (['MotionSensors', 'Rooms'] as $property) {
    $list = json_decode($config[$property], true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($list)) { throw new RuntimeException('Die vorhandene Liste ' . $property . ' ist ungültig. Nichts eingerichtet.'); }
    foreach ($list as &$item) {
        if (is_array($item) && isset($item['Name']) && is_string($item['Name'])) { $item['Name'] = $labelUmlauts($item['Name']); }
    }
    unset($item); $captionLists[$property] = $list;
}
foreach (array_merge(array_values($found), $defaults) as $entry) {
    $key = $entry['Room'] . ':' . $entry['Target'];
    if (!isset($keys[$key])) { $entries[] = $entry; $keys[$key] = true; }
}
$roomNames = array_values(array_unique(array_column($entries, 'Room')));
$roomNames = array_values(array_filter($roomNames, static fn(string $name): bool => $name !== 'PV-Anlage'));
$order = ['Wohnzimmer', 'Schlafzimmer', 'Flur', 'Ankleidezimmer', 'Bad', 'Büro', 'Büro Keller', 'Sportraum', 'Keller', 'Küche', 'Terrasse'];
usort($roomNames, static function (string $a, string $b) use ($order): int {
    $pa = array_search($a, $order, true); $pb = array_search($b, $order, true);
    return (($pa === false ? 999 : $pa) <=> ($pb === false ? 999 : $pb)) ?: strnatcasecmp($a, $b);
});
$roomCategory = @IPS_GetObjectIDByIdent('SVHSRoomsHTML', $root);
if ($roomCategory !== false && IPS_GetObject($roomCategory)['ObjectType'] !== 0) { throw new RuntimeException('Eigener Raumkachel-Bereich ist anders belegt.'); }
$pairs = [];
foreach ($roomNames as $room) {
    $key = substr(sha1($room), 0, 14); $instance = false; $link = false;
    if ($roomCategory !== false) {
        $instance = @IPS_GetObjectIDByIdent('SVHSRoom_' . $key, $roomCategory);
        $link = @IPS_GetObjectIDByIdent('SVHSRoomLink_' . $key, $roomCategory);
        if ($instance !== false && (!IPS_InstanceExists($instance) || IPS_GetInstance($instance)['ModuleInfo']['ModuleID'] !== $module
            || IPS_GetProperty($instance, 'ConfigSource') !== $master)) { throw new RuntimeException('Raum-Ident ist anders belegt: ' . $room); }
        if ($link !== false && (!IPS_LinkExists($link) || $instance === false || IPS_GetLink($link)['TargetID'] !== $instance)) {
            throw new RuntimeException('Raum-Link ist anders belegt: ' . $room);
        }
    }
    $pairs[$room] = ['key' => $key, 'instance' => $instance, 'link' => $link];
}
$pv = @IPS_GetObjectIDByIdent('SVHSTile_7', $root); $pvLink = @IPS_GetObjectIDByIdent('SVHSTileLink_7', $root);
if ($pv !== false && (!IPS_InstanceExists($pv) || IPS_GetInstance($pv)['ModuleInfo']['ModuleID'] !== $module
    || IPS_GetProperty($pv, 'ConfigSource') !== $master)) { throw new RuntimeException('Eigene PV-Kachel ist anders belegt.'); }
if ($pvLink !== false && (!IPS_LinkExists($pvLink) || $pv === false || IPS_GetLink($pvLink)['TargetID'] !== $pv)) {
    throw new RuntimeException('Eigener PV-Link ist anders belegt.');
}
// All ownership checks complete. Existing source objects are never modified.
IPS_SetProperty($master, 'RoomEntries', json_encode($entries, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
foreach ($captionLists as $property => $list) { IPS_SetProperty($master, $property, json_encode($list, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)); }
IPS_SetProperty($master, 'RoofStatus', 25259); IPS_SetProperty($master, 'AwningStatus', 35313);
if (!IPS_ApplyChanges($master)) { throw new RuntimeException('Zentrale Einstellungen konnten nicht übernommen werden.'); }
if ($roomCategory === false) { $roomCategory = IPS_CreateCategory(); IPS_SetParent($roomCategory, $root); IPS_SetIdent($roomCategory, 'SVHSRoomsHTML'); }
IPS_SetName($roomCategory, 'Räume'); IPS_SetPosition($roomCategory, 40); IPS_SetHidden($roomCategory, false);
$position = 0;
foreach ($pairs as $room => $pair) {
    $id = $pair['instance'];
    if ($id === false) { $id = IPS_CreateInstance($module); IPS_SetParent($id, $roomCategory); IPS_SetIdent($id, 'SVHSRoom_' . $pair['key']); }
    IPS_SetName($id, $room); IPS_SetHidden($id, true);
    IPS_SetProperty($id, 'View', 12); IPS_SetProperty($id, 'RoomFilter', $room); IPS_SetProperty($id, 'ConfigSource', $master); IPS_SetProperty($id, 'ActiveView', true);
    if (!IPS_ApplyChanges($id)) { throw new RuntimeException('Raumkachel konnte nicht eingerichtet werden: ' . $room); }
    $link = $pair['link'];
    if ($link === false) { $link = IPS_CreateLink(); IPS_SetParent($link, $roomCategory); IPS_SetIdent($link, 'SVHSRoomLink_' . $pair['key']); }
    IPS_SetName($link, $room); IPS_SetLinkTargetID($link, $id); IPS_SetPosition($link, $position); IPS_SetHidden($link, false); $position += 10;
    echo 'Raumkachel: ' . $room . ' (Instanz ' . $id . ')' . PHP_EOL;
}
if ($pv === false) { $pv = IPS_CreateInstance($module); IPS_SetParent($pv, $root); IPS_SetIdent($pv, 'SVHSTile_7'); }
IPS_SetName($pv, 'PV-Details'); IPS_SetHidden($pv, true); IPS_SetProperty($pv, 'View', 7); IPS_SetProperty($pv, 'ConfigSource', $master); IPS_SetProperty($pv, 'ActiveView', true);
if (!IPS_ApplyChanges($pv)) { throw new RuntimeException('PV-Detailkachel konnte nicht eingerichtet werden.'); }
if ($pvLink === false) { $pvLink = IPS_CreateLink(); IPS_SetParent($pvLink, $root); IPS_SetIdent($pvLink, 'SVHSTileLink_7'); }
IPS_SetName($pvLink, 'PV-Details'); IPS_SetLinkTargetID($pvLink, $pv); IPS_SetPosition($pvLink, 50); IPS_SetHidden($pvLink, true);
IPS_SetPosition($mainLink, 0);
foreach ($snapshot as $id => $before) {
    $o = IPS_GetObject($id);
    if (IPS_GetParent($id) !== $before['parent'] || IPS_GetName($id) !== $before['name']
        || $o['ObjectIsHidden'] !== $before['hidden'] || $o['ObjectPosition'] !== $before['position']) {
        throw new RuntimeException('Ein vorhandenes fremdes Objekt wurde unerwartet verändert: ' . $id);
    }
}
echo 'Fertig: ' . count($roomNames) . ' Raumkacheln. PV-Details über Details öffnen in der Übersicht.' . PHP_EOL;
echo 'Status im bestehenden Startseitenblock: Dachfenster 25259, Markise/Rollo 35313.' . PHP_EOL;
echo 'Gelesene Raumquellen: ' . ($sources ? implode(', ', $sources) : 'keine; nur bekannte Vorgaben verwendet') . PHP_EOL;
echo 'Übernommene vorhandene Raumziele: ' . count($found) . '; konfigurierte Quellen insgesamt: ' . count($entries) . PHP_EOL;
echo 'Vorhandene native Bereiche und alle Gerätequellen bleiben bestehen. Keine Gerätebefehle gesendet.' . PHP_EOL;
echo 'Visualisierung neu laden. Die HTML-Raumkacheln stehen unter „Räume“.' . PHP_EOL;
