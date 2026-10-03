<?php
// Nur lesen: zeigt Variablen im Bereich des bisher ausgewählten Cinema-Status/Power.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Bitte manuell ausführen.'); }
$master = 52627;
if (!IPS_InstanceExists($master)) { throw new RuntimeException('Instanz 52627 fehlt.'); }
$seeds = array_unique([45754, 10950, 45376, (int)IPS_GetProperty($master, 'CinemaState'), (int)IPS_GetProperty($master, 'CinemaControl')]);
$parents = [];
foreach ($seeds as $id) {
    if ($id > 0 && IPS_ObjectExists($id)) { $parent = IPS_GetParent($id); if ($parent > 0) { $parents[$parent] = true; } }
}
foreach (array_keys($parents) as $parent) {
    echo PHP_EOL . IPS_GetLocation($parent) . PHP_EOL;
    foreach (IPS_GetChildrenIDs($parent) as $id) {
        if (!IPS_VariableExists($id)) { continue; }
        $v = IPS_GetVariable($id);
        $profile = $v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile'];
        $action = (int)$v['VariableCustomAction'] > 0 ? (int)$v['VariableCustomAction'] : (int)$v['VariableAction'];
        echo $id . ' | ' . IPS_GetName($id) . ' | Typ ' . $v['VariableType']
            . ' | ' . GetValueFormatted($id) . ' | Aktion ' . $action . ' | Profil ' . $profile . PHP_EOL;
        if (function_exists('IPS_GetVariablePresentation')) {
            $presentation = IPS_GetVariablePresentation($id);
            if ($id === 45376 || $id === 16889 || $id === (int)IPS_GetProperty($master, 'CinemaVolume') || $id === (int)IPS_GetProperty($master, 'CinemaSource')) {
                echo '  Darstellung: ' . json_encode($presentation, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
            }
        }
        if ($profile !== '' && IPS_VariableProfileExists($profile)) {
            $p = IPS_GetVariableProfile($profile);
            echo '  Grenzen: ' . $p['MinValue'] . ' bis ' . $p['MaxValue'] . ', Schritt ' . $p['StepSize'] . PHP_EOL;
        }
    }
}
echo PHP_EOL . 'Keine Variablen oder Geräte geändert.' . PHP_EOL;
