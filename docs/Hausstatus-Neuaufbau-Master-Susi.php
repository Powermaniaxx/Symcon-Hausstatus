<?php
declare(strict_types=1);

/**
 * IP-Symcon 9.0+ / Hausstatus main v0.79+
 * Wiederaufbau nach Bereinigung der Visualisierungen.
 *
 * Quelle:     52627  – bestehende zentrale Hausstatus-Konfiguration
 * Master:     55503  – Startseite Sven, bisherige Optik mit Einzelkacheln
 * Susi:       23271  – gleiche Einzelkacheln, Shield kompakt
 *
 * WICHTIG: Keine Loeschungen; Ordner "fehlerhaft" (38631) und Sophie bleiben
 * vollstaendig unveraendert. Das alte Spiegelungsskript muss deaktiviert sein.
 * Das Skript ist wiederholt ausfuehrbar und legt keine Doppelkopien an.
 */

$master = 52627;
$rootSven = 55503;
$rootSusi = 23271;
$anwesenheitSven = 35287;
$heizungSven = 24646;
$modul = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';

function svhrKategoriePruefen(int $id, string $name): void
{
    if (!IPS_ObjectExists($id) || (int) IPS_GetObject($id)['ObjectType'] !== 0) {
        throw new RuntimeException($name . ' (' . $id . ') ist nicht als Kategorie vorhanden. Nichts veraendern und erst die Objekt-IDs klaeren.');
    }
}

function svhrIstModul(int $id): bool
{
    global $modul;
    return IPS_InstanceExists($id) && IPS_GetInstance($id)['ModuleInfo']['ModuleID'] === $modul;
}

function svhrKategorie(int $parent, string $ident, string $name, bool $identBenoetigt = false): int
{
    $id = @IPS_GetObjectIDByIdent($ident, $parent);
    if ($id !== false) {
        svhrKategoriePruefen((int) $id, $name);
    } else {
        $found = [];
        foreach (IPS_GetChildrenIDs($parent) as $child) {
            $o = IPS_GetObject($child);
            if ((int) $o['ObjectType'] === 0 && $o['ObjectName'] === $name) {
                $found[] = $child;
            }
        }
        if (count($found) > 1) {
            throw new RuntimeException('Mehrere Kategorien "' . $name . '" unter ' . $parent . '. Bitte zuerst die Dubletten pruefen.');
        }
        if ($found) {
            $id = $found[0];
            $alterIdent = (string) IPS_GetObject($id)['ObjectIdent'];
            if ($alterIdent === '') {
                IPS_SetIdent($id, $ident);
            } elseif ($alterIdent !== $ident) {
                if ($identBenoetigt) {
                    // Nur fuer die vom Modul verwendeten Master-Navigationskennungen.
                    // Vorher wurde bereits sichergestellt, dass sie frei sind.
                    IPS_SetIdent($id, $ident);
                    echo 'Master-Kategorie ' . $id . ' zur Modulnavigation zugeordnet.' . PHP_EOL;
                } else {
                    echo 'Info: Kategorie ' . $id . ' nutzt eigenen Ident ' . $alterIdent . PHP_EOL;
                }
            }
        } else {
            $id = IPS_CreateCategory();
            IPS_SetParent($id, $parent);
            IPS_SetIdent($id, $ident);
            IPS_SetName($id, $name);
            echo 'Neue Kategorie: ' . $name . ' (' . $id . ')' . PHP_EOL;
        }
    }
    if (IPS_GetObject($id)['ObjectIsHidden']) { IPS_SetHidden($id, false); }
    return (int) $id;
}

/**
 * Eine (technische oder sichtbare) Detailkachel pro Kategorie und Ansicht.
 * Bereits vorhandene passende Kacheln werden ohne Duplikat wiederverwendet.
 */
function svhrAnsicht(
    int $parent,
    string $ident,
    string $name,
    int $view,
    int $part = 0,
    bool $kompakt = false,
    bool $versteckt = false
): int {
    global $master, $modul;

    $id = @IPS_GetObjectIDByIdent($ident, $parent);
    if ($id !== false) {
        if (!svhrIstModul((int) $id)) {
            throw new RuntimeException('Kennung ' . $ident . ' ist schon durch ein anderes Objekt (' . $id . ') belegt.');
        }
        if ((int) IPS_GetProperty($id, 'ConfigSource') !== $master) {
            throw new RuntimeException('Vorhandene Kachel ' . $id . ' hat eine fremde Datenquelle. Abbruch statt Ueberschreiben.');
        }
    } else {
        $found = [];
        foreach (IPS_GetChildrenIDs($parent) as $child) {
            if (!svhrIstModul($child)) { continue; }
            if ((int) IPS_GetProperty($child, 'ConfigSource') !== $master
                || (int) IPS_GetProperty($child, 'View') !== $view
                || (int) IPS_GetProperty($child, 'HomepagePart') !== $part) { continue; }
            $found[] = $child;
        }
        if (count($found) > 1) {
            // Die bestehenden Objekte nicht blind ersetzen oder umkonfigurieren.
            throw new RuntimeException('Mehrere Kacheln fuer Ansicht ' . $view . '/' . $part . ' in Kategorie ' . $parent . ': ' . implode(', ', $found));
        }
        if ($found) {
            $id = $found[0];
        } else {
            $id = IPS_CreateInstance($modul);
            IPS_SetParent($id, $parent);
            IPS_SetIdent($id, $ident);
            echo 'Neue Kachel: ' . $name . ' (' . $id . ')' . PHP_EOL;
        }
    }

    $changed = false;
    foreach (['ConfigSource' => $master, 'View' => $view,
              'HomepagePart' => $part, 'ActiveView' => true,
              'ShieldCompact' => $kompakt] as $key => $value) {
        if (IPS_GetProperty($id, $key) !== $value) {
            IPS_SetProperty($id, $key, $value);
            $changed = true;
        }
    }
    if ($changed) { IPS_ApplyChanges($id); }
    if (IPS_GetName($id) !== $name) { IPS_SetName($id, $name); }
    if ((bool) IPS_GetObject($id)['ObjectIsHidden'] !== $versteckt) {
        IPS_SetHidden($id, $versteckt);
    }
    return (int) $id;
}

// ====================================
// 1. Vorpruefung OHNE Veraenderungen
// ====================================
svhrKategoriePruefen($rootSven, 'Master-Visualisierung');
svhrKategoriePruefen($rootSusi, 'Visualisierung Susi');
svhrKategoriePruefen($anwesenheitSven, 'Anwesenheit Sven');
svhrKategoriePruefen($heizungSven, 'Heizung Sven');
if (IPS_GetParent($anwesenheitSven) !== $rootSven
    || IPS_GetParent($heizungSven) !== $rootSven) {
    throw new RuntimeException('Die beiden funktionierenden Kategorien liegen nicht mehr unter 55503. Keine Aenderung.');
}
if (!svhrIstModul($master)) {
    throw new RuntimeException('Hausstatus-Master 52627 fehlt. Er muss mit seinen bisherigen Einstellungen wieder vorhanden sein. Keine neuen Kacheln angelegt.');
}
if ((int) IPS_GetProperty($master, 'ConfigSource') !== 0
    || (int) IPS_GetProperty($master, 'View') !== 0) {
    throw new RuntimeException('52627 ist nicht die zentrale Hausstatus-Quelle (View=0, ConfigSource=0). Keine Aenderung.');
}
if (IPS_GetParent($master) !== $rootSven) {
    throw new RuntimeException('Master 52627 liegt nicht direkt unter 55503, sondern unter ' . IPS_GetParent($master) . '. Bitte vor dem Neuaufbau zur Master-Kategorie zurueckverschieben.');
}
$settings = json_decode(IPS_GetConfiguration($master), true, 512, JSON_THROW_ON_ERROR);
foreach (['SeparateHomepageTiles', 'PreserveHomepageStyle', 'HomepageCategory'] as $prop) {
    if (!array_key_exists($prop, $settings)) {
        throw new RuntimeException('Hausstatus-Modul aktualisieren: Eigenschaft ' . $prop . ' fehlt.');
    }
}

// Eigene Ident-Kollisionen und Fehlkonfigurationen frueh erkennen.
foreach ([$rootSven, $rootSusi] as $root) {
    foreach (IPS_GetChildrenIDs($root) as $id) {
        $ident = (string) IPS_GetObject($id)['ObjectIdent'];
        if (preg_match('/^SVHS(?:HomePart_|RebuildSusiPart_)([0-9]+)$/D', $ident)
            && !svhrIstModul($id)) {
            throw new RuntimeException('Fremdobjekt mit Hausstatus-Kennung ' . $ident . ' (' . $id . '). Kein sicherer Neuaufbau moeglich.');
        }
    }
}

echo 'Vorpruefung erfolgreich: Sven 55503, Susi 23271, Hausstatus 52627.' . PHP_EOL;
echo 'Anwesenheit 35287 und Heizung 24646 werden nicht veraendert.' . PHP_EOL;
echo 'Fehlerhaft 38631 sowie Sophie werden nicht veraendert.' . PHP_EOL;

// ====================================
// 2. Fehlende Kategorien ergaenzen
// ====================================
$pvSven     = svhrKategorie($rootSven, 'SVHSPVPage', 'PV-Details', true);
$systemSven = svhrKategorie($rootSven, 'SVHSSystemPage', 'System');
$raeumeSven = svhrKategorie($rootSven, 'SVHSRoomsHTML', 'Räume', true);

$anwSusi    = svhrKategorie($rootSusi, 'SVHSRebuildSusiPresence', 'Anwesenheit');
svhrKategorie($rootSusi, 'SVHSRebuildSusiHeating', 'Heizung · Pläne und Wetter');
$pvSusi     = svhrKategorie($rootSusi, 'SVHSRebuildSusiPV', 'PV-Details');
$systemSusi = svhrKategorie($rootSusi, 'SVHSRebuildSusiSystem', 'System');
$raeumeSusi = svhrKategorie($rootSusi, 'SVHSRebuildSusiRooms', 'Räume');

// Existing technical PV view from older installer: move it into the PV folder.
// Never create a second PV instance while the old one is still present.
$oldPvID = @IPS_GetObjectIDByIdent('SVHSTile_7', $rootSven);
if ($oldPvID !== false) {
    if (!svhrIstModul((int)$oldPvID)
        || (int)IPS_GetProperty($oldPvID, 'ConfigSource') !== $master
        || (int)IPS_GetProperty($oldPvID, 'View') !== 7) {
        throw new RuntimeException('SVHSTile_7 unter Master ist anders belegt (' . $oldPvID . '). Kein Verschieben.');
    }
    $destinationID = @IPS_GetObjectIDByIdent('SVHSTile_7', $pvSven);
    if ($destinationID !== false && $destinationID !== $oldPvID) {
        throw new RuntimeException('Zwei PV-Details-Instanzen gefunden (' . $oldPvID . '/' . $destinationID . '). Kein Verschieben.');
    }
    IPS_SetParent($oldPvID, $pvSven);
    IPS_SetName($oldPvID, 'PV-Details');
    IPS_SetHidden($oldPvID, false);
    echo 'Bestehende technische PV-Ansicht ' . $oldPvID . ' nach PV-Details verschoben.' . PHP_EOL;
}

// ====================================
// 3. Detailseiten aus EINER Datenquelle
// ====================================
// PV-Detailansicht als einzige Kachel im PV-Details-Ordner.
// Keine technische PV-Kachel mehr direkt auf der Master-Startseite.
svhrAnsicht($pvSven, 'SVHSTile_7', 'PV-Details', 7, 0, false, false);
svhrAnsicht($systemSven, 'SVHSRebuildFritzbox', 'FritzBox Gesamtansicht', 24);
svhrAnsicht($systemSven, 'SVHSRebuildDocker', 'NAS-Container', 25);
svhrAnsicht($raeumeSven, 'SVHSRebuildRoomSelector', 'Räume', 12);

svhrAnsicht($anwSusi, 'SVHSRebuildPresenceHistory', 'Sven & Susi – Anwesenheit mit 24h-Verlauf', 18, 0, true);
svhrAnsicht($pvSusi, 'SVHSRebuildPVDetail', 'PV-Details', 7, 0, true);
svhrAnsicht($systemSusi, 'SVHSRebuildFritzbox', 'FritzBox Gesamtansicht', 24, 0, true);
svhrAnsicht($systemSusi, 'SVHSRebuildDocker', 'NAS-Container', 25, 0, true);
svhrAnsicht($raeumeSusi, 'SVHSRebuildRoomSelector', 'Räume', 12, 0, true);

// ====================================
// 4. Master-Startseite im LETZTEN Design
// ====================================
// Das vorhandene Modul legt die Einzelkacheln mit seinen gespeicherten
// Stilen selbst an. Es benutzt stabile SVHSHomePart_N Kennungen.
foreach (['HomepageCategory' => $rootSven,
          'PreserveHomepageStyle' => true,
          'SeparateHomepageTiles' => true] as $name => $value) {
    if (IPS_GetProperty($master, $name) !== $value) { IPS_SetProperty($master, $name, $value); }
}
IPS_ApplyChanges($master);
if (!function_exists('SVHS_GetHomepageLayoutDiagnostics')) {
    throw new RuntimeException('SVHS_GetHomepageLayoutDiagnostics fehlt. Bitte Modul main aktualisieren.');
}
$diagnose = json_decode(SVHS_GetHomepageLayoutDiagnostics($master), true, 512, JSON_THROW_ON_ERROR);
if (!empty($diagnose['lastError'])) {
    throw new RuntimeException('Die Master-Startseite meldet: ' . $diagnose['lastError']);
}

// ====================================
// 5. Nur AKTIVE Master-Kacheln nach Susi
// ====================================
$active = [];
foreach (IPS_GetChildrenIDs($rootSven) as $id) {
    if (!preg_match('/^SVHSHomePart_([0-9]+)$/D',
        (string) IPS_GetObject($id)['ObjectIdent'], $match)) { continue; }
    if (!svhrIstModul($id) || (int) IPS_GetProperty($id, 'ConfigSource') !== $master
        || IPS_GetObject($id)['ObjectIsHidden']) { continue; }
    $part = (int) $match[1];
    if ($part < 1 || $part > 12) { continue; }
    $active[$part] = IPS_GetName($id);
}
if (!$active) { throw new RuntimeException('Keine aktiven Master-Startseiten-Kacheln gefunden. Susi wurde nicht aufgebaut.'); }
ksort($active);

$owned = [];
foreach ($active as $part => $title) {
    $id = svhrAnsicht($rootSusi, 'SVHSRebuildSusiPart_' . $part,
        $title, 0, $part, true);
    IPS_SetPosition($id, $part * 10);
    $owned[$id] = true;
}

// Ausgediente sichtbare Einzelkacheln und Gesamtansichten von derselben
// Quelle ausblenden, ABER NIE loeschen. Neue Kacheln sind vorher bestaetigt.
$hidden = [];
foreach (IPS_GetChildrenIDs($rootSusi) as $id) {
    if (isset($owned[$id])) { continue; }
    $target = IPS_LinkExists($id) ? (int) IPS_GetLink($id)['TargetID'] : $id;
    if (!svhrIstModul($target)) { continue; }
    if ((int) IPS_GetProperty($target, 'ConfigSource') !== $master) { continue; }
    if ((int) IPS_GetProperty($target, 'View') !== 0) { continue; }
    if (!IPS_GetObject($id)['ObjectIsHidden']) {
        IPS_SetHidden($id, true);
        $hidden[] = $id;
    }
}

echo 'Master: ' . count($active) . ' aktuelle Startseiten-Kacheln.' . PHP_EOL;
echo 'Susi:   ' . count($owned) . ' entsprechende Kacheln (Shield kompakt).' . PHP_EOL;
echo 'Susi:   ' . count($hidden) . ' alte Startseiten-Duplikate ausgeblendet.' . PHP_EOL;
if ($hidden) { echo 'Ausgeblendete IDs: ' . implode(', ', $hidden) . PHP_EOL; }
echo 'FERTIG. Keine alten Objekte geloescht. Spiegelung bleibt deaktiviert.' . PHP_EOL;