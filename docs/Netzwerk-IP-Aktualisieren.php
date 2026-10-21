<?php
declare(strict_types=1);
// Vollständig als Symcon-PHP-Skript speichern und einmal manuell ausführen.
$master = 52627;
$url = 'http://192.168.178.90:1880/symcon/visu-ping';
if (!IPS_InstanceExists($master)
    || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
    || IPS_GetProperty($master, 'ConfigSource') !== 0) {
    throw new RuntimeException('Zentrale Hausstatus-Instanz 52627 nicht gefunden.');
}
$config = json_decode(IPS_GetConfiguration($master), true, 512, JSON_THROW_ON_ERROR);
if (!array_key_exists('NodeRedViewerPingURL', $config)) {
    throw new RuntimeException('Zuerst das aktuelle Hausstatus-Modul installieren.');
}
if ($config['NodeRedViewerPingURL'] !== $url) {
    IPS_SetProperty($master, 'NodeRedViewerPingURL', $url);
    IPS_ApplyChanges($master);
}
echo 'Visu-Freigabe für Node-RED: ' . $url . PHP_EOL;
echo 'Bitte die Hausstatus-Visu öffnen oder neu laden. Die vorhandenen Abfrage-Flows bleiben unverändert.' . PHP_EOL;

