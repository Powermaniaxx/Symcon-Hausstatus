<?php
declare(strict_types=1);
// In ein NEUES temporäres Skript kopieren und einmal manuell ausführen.
// Alle Hausstatus-Instanzen werden gelesen. Keine Einrichtung, keine Gerätebefehle.
if (($_IPS['SENDER'] ?? '') !== 'Execute') { throw new RuntimeException('Einmal manuell ausführen.'); }
$module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
echo 'Symcon-Version: ' . IPS_GetKernelVersion() . PHP_EOL;
echo 'Vollbild-Konstante: ' . (defined('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN')
    ? (string)constant('INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN') : 'nicht vorhanden') . PHP_EOL;
if (!function_exists('SVHS_GetVisualizationDiagnostics')) {
    echo 'Das Modul 0.14 ist noch nicht vollständig geladen. In Module Control aktualisieren.' . PHP_EOL;
    return;
}
$ids = IPS_GetInstanceListByModuleID($module);
foreach ($ids as $id) {
    try {
        $data = json_decode(SVHS_GetVisualizationDiagnostics($id), true, 512, JSON_THROW_ON_ERROR);
        echo PHP_EOL . IPS_GetName($id) . ' | Instanz ' . $id . ' | View ' . $data['view']
            . ' | Quelle ' . $data['source'] . ' | Raum ' . ($data['room'] !== '' ? $data['room'] : '(alle)') . PHP_EOL;
        echo '  Aktiv: ' . ($data['active'] ? 'ja' : 'nein') . ' | Darstellung: ' . $data['actualType']
            . ' | erwartet: ' . $data['expectedType'] . ' | HTML: ' . $data['htmlBytes'] . ' Bytes' . PHP_EOL;
        echo '  Zustandsdaten eingesetzt: ' . ($data['initialStateInserted'] ? 'ja' : 'nein')
            . ' | HTML-Fehler: ' . ($data['htmlError'] ? 'ja' : 'nein') . PHP_EOL;
        if (isset($data['error'])) { echo '  ' . $data['error'] . PHP_EOL; }
        if ($data['active'] && $data['actualType'] !== $data['expectedType']) {
            echo '  Änderungen in der zentralen Instanz übernehmen; danach die Visualisierung neu laden.' . PHP_EOL;
        }
    } catch (Throwable $e) { echo 'Instanz ' . $id . ': ' . $e->getMessage() . PHP_EOL; }
}
echo PHP_EOL . 'Typ 1 ist auf älteren Installationen korrekt. Vollbild-HTML benötigt eine unterstützte SDK-Version.' . PHP_EOL;
echo 'Die Darstellungswahl im Browser ist nicht auslesbar. Dort Instanzspezifische Darstellung wählen.' . PHP_EOL;
echo 'Keine Quellen, Variablen oder Geräte verändert. Keine HTML-Inhalte oder WLAN-Codes ausgegeben.' . PHP_EOL;
