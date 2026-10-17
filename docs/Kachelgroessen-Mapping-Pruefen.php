<?php
declare(strict_types=1);

/**
 * MASTER -> SVEN / SUSI: KACHELGROESSEN-MAPPING (NUR PRUEFEN)
 *
 * Master: VISU 12231 / Kategorie 55503
 * Sven:   VISU 34154 / Kategorie 13714
 * Susi:   VISU 44331 / Kategorie 23271
 *
 * Gibt KEINE gespeicherten Layout-Werte oder Benutzerrechte aus,
 * sondern ausschliesslich Objekt-IDs, Beispielschluessel und Datentypen.
 * KEINE Aenderungen, auch NICHT an der GridConfigurationSynchronization.
 */
$visualizations = [
    'Master' => ['id' => 12231, 'root' => 55503],
    'Sven'   => ['id' => 34154, 'root' => 13714],
    'Susi'   => ['id' => 44331, 'root' => 23271]
];
$profiles = ['~Desktop', '~Tablet', '~Phone'];
$orientations = ['portrait', 'landscape'];
$sections = ['individualDimensions', 'individualPositions', 'individualConfig'];

function mapType(mixed $v): string
{
    if (is_array($v)) {
        return 'array(' . count($v) . ') ['
            . implode(', ', array_map('strval', array_slice(array_keys($v), 0, 8))) . ']';
    }
    return get_debug_type($v);
}

function mapGrid(int $id): array
{
    $response = VISU_GetSnapshot($id);
    if (!is_string($response)) {
        throw new RuntimeException('VISU_GetSnapshot nicht als JSON-String.');
    }
    $snapshot = json_decode($response, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot)) {
        throw new RuntimeException('Ungueltige Snapshot-Struktur.');
    }
    $grid = $snapshot['gridConfig']
        ?? $snapshot['objects']['ID' . $id]['data']['attributes']['GridConfiguration']
        ?? null;
    if (is_string($grid)) { $grid = json_decode($grid, true, 64, JSON_THROW_ON_ERROR); }
    if (!is_array($grid)) {
        throw new RuntimeException('Kein Grid-Konfigurationsobjekt im Snapshot.');
    }
    return $grid;
}

echo "LAYOUT-MAPPING VORPRUEFUNG - rein lesend\n";
echo "Keine Objekte oder Kachelgroessen werden geaendert.\n\n";

if (function_exists('VISU_SaveGridConfiguration')) {
    $reflection = new ReflectionFunction('VISU_SaveGridConfiguration');
    $parameterNames = [];
    foreach ($reflection->getParameters() as $param) {
        $parameterNames[] = '$' . $param->getName()
            . ($param->hasType() ? ': ' . (string)$param->getType() : ': (kein Typ)')
            . ($param->isOptional() ? ' (optional)' : '');
    }
    echo 'Speicherfunktion vorhanden: JA | Parameter '
        . $reflection->getNumberOfRequiredParameters() . '/'
        . $reflection->getNumberOfParameters() . PHP_EOL;
    echo 'Signatur: ' . implode(' | ', $parameterNames) . PHP_EOL;
} else {
    echo "Speicherfunktion vorhanden: NEIN\n";
}
echo PHP_EOL;

foreach ($visualizations as $label => $conf) {
    $id = $conf['id'];
    $root = $conf['root'];

    echo '=== ' . $label . ' ===' . PHP_EOL;
    if (!IPS_InstanceExists($id) || !IPS_ObjectExists($root)
        || (int)IPS_GetObject($root)['ObjectType'] !== 0) {
        echo "FEHLER: Visualisierung oder Startkategorie fehlt.\n\n";
        continue;
    }

    $config = json_decode(IPS_GetConfiguration($id), true);
    $base = is_array($config) ? ($config['BaseID'] ?? '(fehlt)') : '(ungueltig)';
    echo 'Visu ' . $id . ' | BaseID: ' . (string)$base
        . ' | erwartet: ' . $root . PHP_EOL;
    if ((int)$base !== $root) {
        echo "STOP: BaseID passt nicht. Keine Layoutzuordnung moeglich.\n\n";
        continue;
    }

    try {
        $grid = mapGrid($id);
    } catch (Throwable $e) {
        echo 'Fehler beim Lesen: ' . $e->getMessage() . PHP_EOL . PHP_EOL;
        continue;
    }

    foreach ($profiles as $profile) {
        foreach ($orientations as $orientation) {
            $layout = $grid[$profile][$orientation] ?? null;
            if (!is_array($layout)) {
                echo $profile . '/' . $orientation . ': fehlt' . PHP_EOL;
                continue;
            }
            echo $profile . '/' . $orientation . ': '
                . 'Gitter=' . (string)($layout['crossAxis'] ?? '?')
                . ' | Standard=' . mapType($layout['defaultDimensions'] ?? null)
                . PHP_EOL;
            foreach ($sections as $section) {
                $items = $layout[$section] ?? [];
                if (!is_array($items)) {
                    echo '  ' . $section . ' ist kein Array' . PHP_EOL;
                    continue;
                }
                $shown = 0;
                echo '  ' . $section . ': ' . count($items) . ' Eintraege' . PHP_EOL;
                foreach ($items as $key => $value) {
                    echo '     Schluessel=' . (string)$key
                        . ' | Wertstruktur=' . mapType($value) . PHP_EOL;
                    if (++$shown >= 4) { break; }
                }
            }
        }
    }

    echo 'Objekte direkt unter Startkategorie:' . PHP_EOL;
    $shown = 0;
    foreach (IPS_GetChildrenIDs($root) as $objID) {
        $obj = IPS_GetObject($objID);
        if ((bool)$obj['ObjectIsHidden']) { continue; }
        $target = IPS_LinkExists($objID)
            ? (int)IPS_GetLink($objID)['TargetID'] : $objID;
        $part = '';
        if (IPS_InstanceExists($target)) {
            $properties = IPS_GetConfiguration($target);
            $props = is_string($properties) ? json_decode($properties, true) : [];
            if (is_array($props) && isset($props['HomepagePart'])) {
                $part = ' | Part=' . (int)$props['HomepagePart']
                    . ', View=' . (int)($props['View'] ?? -1);
            }
        }
        echo '  ID=' . $objID
            . ' Typ=' . (int)$obj['ObjectType']
            . ' Ident=' . (string)$obj['ObjectIdent']
            . ' Name=' . IPS_GetName($objID)
            . ' Ziel=' . $target . $part . PHP_EOL;
        if (++$shown >= 25) { echo "  ... begrenzt auf 25 Objekte\n"; break; }
    }
    echo PHP_EOL;
}
echo 'FERTIG. Nur gelesen. Sophie und WebFront wurden nicht abgefragt.' . PHP_EOL;
