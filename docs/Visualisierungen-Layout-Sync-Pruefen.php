<?php
declare(strict_types=1);

/**
 * Kachel-Visualisierungen: read-only prerequisites for layout sync.
 *
 * This script does not call VISU_SaveGridConfiguration or any setter.
 * It prints ONLY candidate visualization instance IDs, related source
 * category IDs and grid/profile keys/counts (never complete grid contents).
 *
 * Master category 55503, Sven 13714, Susi 23271.
 */
$roots = [55503 => 'Master', 13714 => 'Sven', 23271 => 'Susi'];
echo "LAYOUT-SYNC PRUEFUNG (nur lesend)\n";
echo "Kategorien: Master 55503 | Sven 13714 | Susi 23271\n";
foreach ($roots as $category => $name) {
    $ok = IPS_ObjectExists($category) && (int)IPS_GetObject($category)['ObjectType'] === 0;
    echo $name . ': ' . ($ok ? IPS_GetName($category) : 'Kategorie fehlt') . PHP_EOL;
}
if (!function_exists('VISU_GetSnapshot')) {
    echo "VISU_GetSnapshot fehlt: Layoutdaten nicht auslesbar.\n";
    return;
}

$matchesRoot = static function (mixed $node, int $root) use (&$matchesRoot): bool {
    if (is_int($node) || is_string($node)) { return (string)$node === (string)$root; }
    if (!is_array($node)) { return false; }
    foreach ($node as $value) {
        if ($matchesRoot($value, $root)) { return true; }
    }
    return false;
};

$moduleNames = [];
$count = 0;
foreach (IPS_GetInstanceList() as $id) {
    $instance = IPS_GetInstance($id);
    $moduleID = (string)($instance['ModuleInfo']['ModuleID'] ?? '');
    if (!isset($moduleNames[$moduleID])) {
        try { $moduleNames[$moduleID] = (string)(IPS_GetModule($moduleID)['ModuleName'] ?? ''); }
        catch (Throwable $e) { $moduleNames[$moduleID] = ''; }
    }

    try { $config = json_decode((string)IPS_GetConfiguration($id), true, 64, JSON_THROW_ON_ERROR); }
    catch (Throwable $e) { continue; }
    $assigned = [];
    foreach ($roots as $root => $name) {
        if ($matchesRoot($config, $root)) { $assigned[] = $name . ':' . $root; }
    }
    $name = IPS_GetName($id);
    $isVis = preg_match('/(?:kachel|visuali|visu|tile)/iu',
        $moduleNames[$moduleID] . ' ' . $name) === 1;
    if (!$assigned && !$isVis) { continue; }
    // Avoid dumping unrelated instances that merely happen to have 'Visu' in
    // their user-defined name. Require at least one root ID or a visual module.
    if (!$assigned && preg_match('/(?:kachel|visuali|visualization|tile)/iu',
        $moduleNames[$moduleID]) !== 1) { continue; }

    echo PHP_EOL . 'Instanz ' . $id . ': ' . $name
        . ' | Modul: ' . $moduleNames[$moduleID]
        . ' | Kategorie: ' . ($assigned ? implode(', ', $assigned) : '(nicht erkannt)')
        . PHP_EOL;
    echo 'Konfigurationsschluessel: ' . implode(', ', array_keys($config)) . PHP_EOL;
    try {
        $snapshot = json_decode((string)VISU_GetSnapshot($id), true, 64, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) { throw new RuntimeException('Snapshot nicht als Array'); }
        $grid = $snapshot['gridConfig'] ?? null;
        if ($grid === null) {
            $grid = $snapshot['objects']['ID' . $id]['data']['attributes']['GridConfiguration'] ?? null;
        }
        if (is_string($grid)) { $grid = json_decode($grid, true, 64, JSON_THROW_ON_ERROR); }
        if (!is_array($grid)) {
            echo 'Grid: nicht gefunden | Snapshot-Schluessel: '
                . implode(', ', array_keys($snapshot)) . PHP_EOL;
            continue;
        }
        echo 'Grid-Profile: ' . implode(', ', array_keys($grid)) . PHP_EOL;
        foreach ($grid as $profile => $value) {
            if (is_string($value)) { $value = json_decode($value, true); }
            if (!is_array($value)) { continue; }
            $orientations = array_intersect(['portrait', 'landscape'], array_keys($value));
            if (!$orientations) { continue; }
            foreach ($orientations as $orientation) {
                $items = $value[$orientation];
                if (!is_array($items)) { continue; }
                $sizes = $items['individualDimensions'] ?? [];
                $positions = $items['individualPositions'] ?? [];
                echo '  ' . $profile . ' / ' . $orientation
                    . ': Groessen=' . (is_array($sizes) ? count($sizes) : '?')
                    . ', Positionen=' . (is_array($positions) ? count($positions) : '?')
                    . ', Optionen=' . implode(',', array_keys($items))
                    . PHP_EOL;
            }
        }
    } catch (Throwable $e) {
        echo 'VISU_GetSnapshot fehlgeschlagen: ' . $e->getMessage() . PHP_EOL;
    }
    $count++;
}
echo PHP_EOL . 'Visualisierungs-Kandidaten: ' . $count . PHP_EOL;
echo 'Keine Gitterkonfiguration wurde geaendert oder gespeichert.' . PHP_EOL;
