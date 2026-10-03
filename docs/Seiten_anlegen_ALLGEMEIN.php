<?php
declare(strict_types=1);
// Nur die eigene zentrale Hausstatus-Instanz eintragen, dann einmal manuell ausführen.
// Die Instanz muss direkt unter der eigenen Kategorie für die Kachelvisualisierung liegen.
$master = 0;
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Einmal manuell ausführen.'); }
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
if ($master <= 0 || !IPS_InstanceExists($master) || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== $module
    || IPS_GetProperty($master, 'ConfigSource') !== 0 || IPS_GetProperty($master, 'View') !== 0) {
    throw new RuntimeException('Oben die eigene zentrale Hausstatus-Instanz eintragen. Nichts verändert.');
}
$root = IPS_GetParent($master);
if ($root <= 0 || IPS_GetObject($root)['ObjectType'] !== 0) {
    throw new RuntimeException('Die zentrale Instanz muss unter einer eigenen Kategorie liegen. Nichts verändert.');
}
$rooms = [];
foreach (['RoomEntries' => 'Room', 'Rooms' => 'Name'] as $property => $field) {
    $rows = json_decode((string)IPS_GetProperty($master, $property), true, 512, JSON_THROW_ON_ERROR);
    foreach ($rows as $row) {
        $name = is_array($row) && is_string($row[$field] ?? null) ? trim($row[$field]) : '';
        if ($name !== '' && $name !== IPS_GetProperty($master, 'PVRoom')) { $rooms[$name] = true; }
    }
}
$texts = [];
foreach (json_decode((string)IPS_GetProperty($master, 'DisplayTexts'), true, 512, JSON_THROW_ON_ERROR) as $row) {
    if (is_string($row['Key'] ?? null) && is_string($row['Text'] ?? null) && trim($row['Text']) !== '') { $texts[$row['Key']] = $row['Text']; }
}
$views = [1 => ['StatusTitle', 'Anwesenheit und Alarm'], 2 => ['DoorTitle', 'Haustür'],
    5 => ['LightState', 'Licht'], 6 => ['CinemaState', 'Medien'], 4 => ['BatteryWarnings', 'Gerätewarnungen'],
    7 => ['PVPower', 'PV-Details'], 8 => ['MotionTitle', 'Bewegung'], 9 => ['TemperatureTitle', 'Temperaturen'],
    10 => ['WeatherTitle', 'Wetter'], 11 => ['OutdoorTitle', 'Außenbereich'], 13 => ['NetworkTitle', 'FritzBox']];
$roomCategory = @IPS_GetObjectIDByIdent('SVHSRoomsHTML', $root);
if ($roomCategory !== false && IPS_GetObject($roomCategory)['ObjectType'] !== 0) {
    throw new RuntimeException('SVHSRoomsHTML ist anders belegt. Nichts verändert.');
}
// Validate ALL occupied identifiers before creating or applying anything.
$checkInstance = static function(int|false $id, int $view, string $room = '') use ($module, $master): void {
    if ($id !== false && (!IPS_InstanceExists($id) || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== $module
        || IPS_GetProperty($id, 'ConfigSource') !== $master || IPS_GetProperty($id, 'View') !== $view
        || ($view === 12 && IPS_GetProperty($id, 'RoomFilter') !== $room))) {
        throw new RuntimeException('Ein geplanter Instanz-Ident ist anders belegt. Nichts verändert.');
    }
};
$checkLink = static function(int|false $id, int|false $target): void {
    if ($id !== false && (!IPS_LinkExists($id) || $target === false || IPS_GetLink($id)['TargetID'] !== $target)) {
        throw new RuntimeException('Ein geplanter Link-Ident ist anders belegt. Nichts verändert.');
    }
};
foreach ($views as $view => $_) {
    $instance = @IPS_GetObjectIDByIdent('SVHSTile_' . $view, $root);
    $checkInstance($instance, $view);
    $checkLink(@IPS_GetObjectIDByIdent('SVHSTileLink_' . $view, $root), $instance);
}
if ($roomCategory !== false) {
    foreach (array_keys($rooms) as $name) {
        $key = substr(sha1($name), 0, 14);
        $instance = @IPS_GetObjectIDByIdent('SVHSRoom_' . $key, $roomCategory);
        $checkInstance($instance, 12, $name);
        $checkLink(@IPS_GetObjectIDByIdent('SVHSRoomLink_' . $key, $roomCategory), $instance);
    }
}
$make = static function(int $parent, string $ident, string $linkIdent, string $name, int $view, string $room, int $position) use ($module, $master): void {
    $id = @IPS_GetObjectIDByIdent($ident, $parent);
    if ($id === false) {
        $id = IPS_CreateInstance($module); IPS_SetParent($id, $parent); IPS_SetIdent($id, $ident);
        IPS_SetName($id, $name); IPS_SetHidden($id, true);
        IPS_SetProperty($id, 'ConfigSource', $master); IPS_SetProperty($id, 'View', $view);
        IPS_SetProperty($id, 'RoomFilter', $room); IPS_ApplyChanges($id);
    }
    $link = @IPS_GetObjectIDByIdent($linkIdent, $parent);
    if ($link === false) {
        $link = IPS_CreateLink(); IPS_SetParent($link, $parent); IPS_SetIdent($link, $linkIdent);
        IPS_SetName($link, $name); IPS_SetLinkTargetID($link, $id); IPS_SetPosition($link, $position);
        IPS_SetHidden($link, $view === 7);
    }
    echo $name . ': Instanz ' . $id . ', Link ' . $link . PHP_EOL;
};
$position = 10;
foreach ($views as $view => [$key, $fallback]) {
    $make($root, 'SVHSTile_' . $view, 'SVHSTileLink_' . $view, $texts[$key] ?? $fallback, $view, '', $position++);
}
if ($roomCategory === false) {
    $roomCategory = IPS_CreateCategory(); IPS_SetParent($roomCategory, $root); IPS_SetIdent($roomCategory, 'SVHSRoomsHTML');
    IPS_SetName($roomCategory, 'Räume'); IPS_SetPosition($roomCategory, 30);
}
$position = 0;
foreach (array_keys($rooms) as $name) {
    $key = substr(sha1($name), 0, 14);
    $make($roomCategory, 'SVHSRoom_' . $key, 'SVHSRoomLink_' . $key, $name, 12, $name, $position++);
}
IPS_ApplyChanges($master);
echo 'Seiten angelegt beziehungsweise wiederverwendet. Bestehende Quellen und Linknamen beibehalten. Keine Gerätebefehle gesendet.' . PHP_EOL;
