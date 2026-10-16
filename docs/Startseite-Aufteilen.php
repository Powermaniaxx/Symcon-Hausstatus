<?php
declare(strict_types=1);
// Vollständig in ein neues Symcon-PHP-Skript kopieren und einmal ausführen.
$master = 52627;
$category = 0; // Nur bei mehreren Startseiten hier die gewünschte Kategorie-ID eintragen.
if (!IPS_InstanceExists($master) || IPS_GetInstance($master)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}') {
    throw new RuntimeException('Hausstatus-Master 52627 nicht gefunden.');
}
$old = json_decode(IPS_GetConfiguration($master), true, 512, JSON_THROW_ON_ERROR);
if (!array_key_exists('PreserveHomepageStyle', $old)) {
    throw new RuntimeException('Zuerst das Hausstatus-Modul von main aktualisieren und beim Master Änderungen übernehmen.');
}
if (!$category) { $category = (int)($old['HomepageCategory'] ?? 0); }
if (!$category) {
    $candidates = [];
    foreach (IPS_GetObjectList() as $id) {
        if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $master) {
            $parent = IPS_GetParent($id);
            if ($parent > 0 && IPS_GetObject($parent)['ObjectType'] === 0 && !IPS_GetObject($parent)['ObjectIsHidden']) { $candidates[$parent] = IPS_GetName($parent); }
        }
    }
    if (count($candidates) === 1) { $category = (int)array_key_first($candidates); }
    elseif (!$candidates && !IPS_GetObject($master)['ObjectIsHidden']) { $category = IPS_GetParent($master); }
    else {
        echo "Keine eindeutige Zielkategorie. Es wurde nichts geändert.\n";
        foreach ($candidates as $id => $name) { echo $id . ' = ' . $name . PHP_EOL; }
        return;
    }
}
if ($category <= 0 || !IPS_ObjectExists($category) || IPS_GetObject($category)['ObjectType'] !== 0) {
    throw new RuntimeException('Ziel muss eine vorhandene Kategorie sein.');
}
try {
    IPS_SetProperty($master, 'HomepageCategory', $category);
    IPS_SetProperty($master, 'PreserveHomepageStyle', true);
    IPS_SetProperty($master, 'SeparateHomepageTiles', true);
    IPS_ApplyChanges($master);
    $result = json_decode(SVHS_GetHomepageLayoutDiagnostics($master), true, 512, JSON_THROW_ON_ERROR);
    if (!empty($result['lastError'])) { throw new RuntimeException($result['lastError']); }
    echo 'Aufgeteilt in Kategorie ' . $category . ' (' . IPS_GetName($category) . ").\n";
    foreach ($result['tiles'] as $tile) { if (!$tile['hidden']) { echo $tile['id'] . ' = ' . $tile['name'] . PHP_EOL; } }
    echo "Rückgängig: Im Master ‚Startseite aufteilen‘ deaktivieren und Änderungen übernehmen.\n";
} catch (Throwable $error) {
    foreach (['HomepageCategory', 'PreserveHomepageStyle', 'SeparateHomepageTiles'] as $key) { IPS_SetProperty($master, $key, $old[$key]); }
    IPS_ApplyChanges($master);
    throw $error;
}
