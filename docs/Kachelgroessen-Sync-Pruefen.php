<?php
declare(strict_types=1);

/**
 * SYMCON 9.0 – Layout aus der Master-Kachelvisualisierung uebernehmen
 * Master: Instanz 12231 / Kategorie 55503
 * Sven:   Instanz 34154 / Kategorie 13714
 * Susi:   Instanz 44331 / Kategorie 23271
 * Sophie: Wird niemals beruehrt.
 *
 * Alle sechs Profile: Desktop/Tablet/Phone jeweils portrait/landscape.
 * Uebernimmt Raster, Standardgroessen, individuelle Groessen und Reihenfolge.
 * Uebernimmt bewusst KEINE fremden/unbekannten IDs oder individualConfig-
 * Parameter, da diese weitere Instanz-IDs enthalten koennen.
 *
 * Verarbeitet NUR eindeutig gemappte MirrorCat<Quell-ID> und
 * MirrorLink<Quell-ID>. Legt keine Kacheln oder Verknuepfungen an.
 * Vor jedem Schreiben wird eine JSON-Sicherung BEIDER Ziele erstellt.
 */
$NUR_PRUEFEN = true;
$MASTER = [12231, 55503];
$ZIELE = ['Sven' => [34154, 13714], 'Susi' => [44331, 23271]];
$PROFILE = ['~Desktop', '~Tablet', '~Phone'];
$FORMATE = ['portrait', 'landscape'];
$SPERRSCHLUESSEL = 'SVHS_GridMasterToSvenSusi';

function svglCheckVisu(int $instance, int $root): void
{
    if (!IPS_InstanceExists($instance) || !IPS_ObjectExists($root)
        || (int)IPS_GetObject($root)['ObjectType'] !== 0) {
        throw new RuntimeException('Visualisierungs-Instanz oder Kategorie fehlt: ' . $instance . ' / ' . $root);
    }
    $config = json_decode(IPS_GetConfiguration($instance), true, 128, JSON_THROW_ON_ERROR);
    if ((int)($config['BaseID'] ?? 0) !== $root) {
        throw new RuntimeException('BaseID falsch bei Visualisierung ' . $instance . ': erwartet ' . $root);
    }
    $name = (string)(IPS_GetInstance($instance)['ModuleInfo']['ModuleID'] ?? '');
    $module = IPS_GetModule($name);
    if (stripos((string)($module['ModuleName'] ?? ''), 'Tile Visualization') === false) {
        throw new RuntimeException('Instanz ' . $instance . ' ist keine Tile Visualization.');
    }
}

function svglGrid(int $instance): array
{
    $response = VISU_GetSnapshot($instance);
    if (!is_string($response)) { throw new RuntimeException('Snapshot ohne JSON fuer ' . $instance); }
    $snapshot = json_decode($response, true, 128, JSON_THROW_ON_ERROR);
    $grid = $snapshot['gridConfig']
        ?? $snapshot['objects']['ID' . $instance]['data']['attributes']['GridConfiguration']
        ?? null;
    if (is_string($grid)) { $grid = json_decode($grid, true, 128, JSON_THROW_ON_ERROR); }
    if (!is_array($grid)) { throw new RuntimeException('Grid fehlt bei ' . $instance); }
    foreach (['~Desktop', '~Tablet', '~Phone'] as $profile) {
        foreach (['portrait', 'landscape'] as $orientation) {
            $layout = $grid[$profile][$orientation] ?? null;
            if (!is_array($layout) || !is_array($layout['individualDimensions'] ?? null)
                || !is_array($layout['individualPositions'] ?? null)
                || !is_array($layout['defaultDimensions'] ?? null)) {
                throw new RuntimeException('Layout unvollstaendig in ' . $instance
                    . ' / ' . $profile . '/' . $orientation);
            }
        }
    }
    return $grid;
}

/**
 * Nur sichtbare, durch das bestehende Spiegelungsskript eindeutig benannte
 * Objekte zuordnen. Ohne passendes Ziel wird diese Kachel NICHT uebertragen.
 */
function svglMap(int $source, int $target, array &$map, array &$stats, int $depth = 0): void
{
    if ($depth > 6) { return; }
    $map[$source] = $target;
    foreach (IPS_GetChildrenIDs($source) as $id) {
        $obj = IPS_GetObject($id);
        if ($obj['ObjectIsHidden']) { continue; }
        $type = (int)$obj['ObjectType'];
        if ($type === 0) {
            $dst = @IPS_GetObjectIDByIdent('MirrorCat' . $id, $target);
            if ($dst !== false && (int)IPS_GetObject($dst)['ObjectType'] === 0
                && !IPS_GetObject($dst)['ObjectIsHidden']) {
                $stats['kategorien']++;
                svglMap($id, (int)$dst, $map, $stats, $depth + 1);
            } else {
                $stats['kategorienOhneZiel']++;
            }
            continue;
        }
        if (!in_array($type, [1, 2, 5, 6], true)) { continue; }
        $stats['sichtbareQuellkacheln']++;
        $dst = @IPS_GetObjectIDByIdent('MirrorLink' . $id, $target);
        if ($dst !== false && IPS_LinkExists($dst)
            && !IPS_GetObject($dst)['ObjectIsHidden']
            && IPS_ObjectExists((int)IPS_GetLink($dst)['TargetID'])) {
            $stats['zugeordneteKacheln']++;
            $map[$id] = (int)$dst;
        } else {
            $stats['kachelnOhneZiel']++;
        }
    }
}

function svglApplyMapping(
    array $master,
    array $target,
    array $map,
    array &$stats
): array {
    $result = $target; // preserve foreign/individual target entries
    $mapped = array_map('intval', $map);
    foreach (['~Desktop', '~Tablet', '~Phone'] as $profile) {
        foreach (['portrait', 'landscape'] as $orientation) {
            $src = $master[$profile][$orientation];
            $out =& $result[$profile][$orientation];
            // Shared profile-wide settings have no object IDs.
            foreach (['crossAxis', 'defaultDimensions', 'customDimensions'] as $key) {
                if (array_key_exists($key, $src)) { $out[$key] = $src[$key]; }
            }
            // Exact sizes for matching objects; clear outdated overrides for
            // mapped objects lacking an individual master override.
            foreach ($map as $sourceID => $destID) {
                if (isset($src['individualDimensions'][(string)$sourceID])) {
                    $dim = $src['individualDimensions'][(string)$sourceID];
                    if (is_array($dim) && isset($dim['width'], $dim['height'])) {
                        $out['individualDimensions'][(string)$destID] = $dim;
                        $stats['groessen']++;
                    }
                } else {
                    unset($out['individualDimensions'][(string)$destID]);
                }
            }

            foreach ($src['individualPositions'] as $sourceParent => $sourceChildren) {
                if (!isset($map[(int)$sourceParent]) || !is_array($sourceChildren)) { continue; }
                $destParent = (string)$map[(int)$sourceParent];
                $ordered = [];
                foreach ($sourceChildren as $sourceChild) {
                    $k = (int)$sourceChild;
                    if (isset($map[$k]) && !in_array($map[$k], $ordered, true)) {
                        $ordered[] = (int)$map[$k];
                    }
                }
                if (!$ordered) { continue; }
                $previous = $out['individualPositions'][$destParent] ?? [];
                if (!is_array($previous)) { $previous = []; }
                // Keep manually placed items that are not mapped to master.
                // Matched items absent from source ordering follow afterwards.
                $other = [];
                foreach ($previous as $value) {
                    $value = (int)$value;
                    if (!in_array($value, $ordered, true) && !in_array($value, $other, true)) {
                        $other[] = $value;
                    }
                }
                $out['individualPositions'][$destParent] = array_merge($ordered, $other);
                $stats['sortierungen']++;
            }
            // Per-object individualConfig may include nested IDs/parameters.
            // Preserve target setting instead of cloning invalid references.
            unset($out);
        }
    }
    return $result;
}

function svglBackup(array $grids): string
{
    $dir = __DIR__ . DIRECTORY_SEPARATOR . 'Hausstatus_Visu_Grid_Backups';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Sicherungsverzeichnis konnte nicht erstellt werden: ' . $dir);
    }
    $filename = $dir . DIRECTORY_SEPARATOR . 'grid_'
        . date('Ymd_His') . '_' . substr(hash('sha256', random_bytes(16)), 0, 8) . '.json';
    $data = json_encode(['saved' => date(DATE_ATOM), 'grids' => $grids],
        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT);
    $bytes = @file_put_contents($filename, $data, LOCK_EX);
    if ($bytes !== strlen($data) || @file_get_contents($filename) !== $data) {
        throw new RuntimeException('Sicherungsdatei unvollstaendig: ' . $filename);
    }
    return $filename;
}

if (!function_exists('VISU_GetSnapshot')
    || (!$NUR_PRUEFEN && !function_exists('VISU_SaveGridConfiguration'))) {
    throw new RuntimeException('Die benoetigte Symcon-Visualisierungsfunktion fehlt.');
}
if (!IPS_SemaphoreEnter($SPERRSCHLUESSEL, 1000)) {
    throw new RuntimeException('Ein Layout-Abgleich laeuft bereits.');
}
try {
    svglCheckVisu($MASTER[0], $MASTER[1]);
    foreach ($ZIELE as [$instance, $root]) { svglCheckVisu($instance, $root); }
    $masterGrid = svglGrid($MASTER[0]);
    $planned = []; $backups = [];
    foreach ($ZIELE as $user => [$instance, $root]) {
        $targetGrid = svglGrid($instance);
        $backups[$user] = ['instance' => $instance, 'root' => $root, 'grid' => $targetGrid];
        $map = [];
        $stats = [
            'kategorien' => 0, 'kategorienOhneZiel' => 0,
            'sichtbareQuellkacheln' => 0, 'zugeordneteKacheln' => 0,
            'kachelnOhneZiel' => 0, 'groessen' => 0, 'sortierungen' => 0
        ];
        svglMap($MASTER[1], $root, $map, $stats);
        $computed = svglApplyMapping($masterGrid, $targetGrid, $map, $stats);
        $changed = $computed !== $targetGrid;
        $planned[$user] = ['id' => $instance, 'grid' => $computed, 'stats' => $stats, 'changed' => $changed];
        echo $user . ' (' . $instance . ')'
            . ' | zugeordnet ' . $stats['zugeordneteKacheln']
            . '/' . $stats['sichtbareQuellkacheln'] . ' sichtbaren Kacheln'
            . ' | Kategorien ' . $stats['kategorien']
            . ' | fehlende Kacheln ' . $stats['kachelnOhneZiel']
            . ' | Groessen ' . $stats['groessen']
            . ' | Reihenfolgen ' . $stats['sortierungen']
            . ' | Aenderung ' . ($changed ? 'JA' : 'NEIN') . PHP_EOL;
    }

    if ($NUR_PRUEFEN) {
        echo PHP_EOL . 'PRUEFMODUS: Kein Objekt und keine Gitterkonfiguration veraendert.' . PHP_EOL;
        return;
    }
    // Never update solely because of global defaults when no source tile can
    // be matched. Unmatched target tiles and their personal data remain intact.
    foreach ($planned as $user => $plan) {
        if ($plan['stats']['sichtbareQuellkacheln'] === 0 || $plan['stats']['zugeordneteKacheln'] === 0) {
            throw new RuntimeException('STOP: Keine zuordenbaren Master-Kacheln bei ' . $user
                . '. Erst die Kachelspiegelung korrigieren.');
        }
        if ($plan['stats']['kachelnOhneZiel'] > 0) {
            throw new RuntimeException('STOP: Bei ' . $user . ' fehlen '
                . $plan['stats']['kachelnOhneZiel'] . ' Zielkacheln.'
                . ' Vor dem ersten Schreibzugriff Quellen/Ziele abgleichen.');
        }
    }
    if (!array_filter($planned, static fn(array $p): bool => $p['changed'])) {
        echo "Keine Layoutaenderungen: Master und Zielvisus bereits synchron.\\n";
        return;
    }

    // Abort before touching either target if backup is impossible.
    $file = svglBackup([
        'Master' => ['instance' => $MASTER[0], 'root' => $MASTER[1], 'grid' => $masterGrid],
        ...$backups
    ]);
    echo 'Sicherung beider Ziel-Layouts: ' . $file . PHP_EOL;
    foreach ($planned as $user => $plan) {
        if (!$plan['changed']) { continue; }
        $encoded = json_encode($plan['grid'], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if (!VISU_SaveGridConfiguration($plan['id'], $encoded)) {
            throw new RuntimeException('Schreiben fehlgeschlagen fuer ' . $user . ' ' . $plan['id']
                . '. Wiederherstellung aus: ' . $file);
        }
        echo 'AKTUALISIERT: ' . $user . ' (' . $plan['id'] . ')' . PHP_EOL;
    }
    echo "FERTIG. Master und Sophie nicht veraendert.\\n";
} finally {
    IPS_SemaphoreLeave($SPERRSCHLUESSEL);
}
