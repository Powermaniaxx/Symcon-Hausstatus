<?php

// ============================================================
// KACHELVISUALISIERUNG - SPIEGELUNG
//
// MASTER: 55503
//
// Sven:   komplette freigegebene Struktur
// Susi:   gleiche Struktur, eigene Hausstatus-Ansichten mit Shield kompakt
// Sophie: bleibt vollständig unverändert und wird hier nicht bearbeitet
//
// Keine IPS_LogMessage-Ausgaben.
// Keine Statusausgaben bei automatischem Aufruf.
// Statusausgabe nur bei manuellem Ausfuehren.
//
// Keine automatischen Loeschungen.
// Keine doppelten Links auf dasselbe Ziel.
// Vorhandene Kacheln beibehalten.
// ============================================================


// ============================================================
// EINSTELLUNGEN
// ============================================================

$masterID = 55503;

$maxKacheln = 120;
$maxProSeite = 35;
$maxKategorien = 25;
$maxTiefe = 2;

// true  = nur pruefen
// false = synchronisieren
$nurPruefen = false;

$nurSichtbare = true;

$ausgeschlossen = [];

$benutzer = [

    'Sven' => [
        'name'  => 'Visualisierung Sven',
        'ident' => 'MirrorVisualisierungSven',
        'modus' => 'full'
    ],

    'Susi' => [
        'name'  => 'Visualisierung Susi',
        'ident' => 'MirrorVisualisierungSusi',
        'modus' => 'full'
    ]
    
    //,
//
  //  'Sophie' => [
 //       'name'  => 'Visualisierung Sophie',
 //       'ident' => 'MirrorVisualisierungSophie',
 //       'modus' => 'start'
  //  ]
];

$manuell = (
    ($_IPS['SENDER'] ?? '') === 'Execute'
);


// ============================================================
// KINDER SORTIEREN
// ============================================================

function ksKinder(int $parent): array
{
    $ids = IPS_GetChildrenIDs($parent);

    usort($ids, function ($a, $b) {

        $oa = IPS_GetObject($a);
        $ob = IPS_GetObject($b);

        $pa = (int)$oa['ObjectPosition'];
        $pb = (int)$ob['ObjectPosition'];

        if ($pa !== $pb) {
            return $pa <=> $pb;
        }

        return $a <=> $b;
    });

    return $ids;
}


// ============================================================
// QUELLOBJEKT PRUEFEN
// ============================================================

function ksErlaubt(
    int $id,
    bool $nurSichtbare,
    array $ausgeschlossen
): bool {

    if (in_array($id, $ausgeschlossen, true)) {
        return false;
    }

    if (!IPS_ObjectExists($id)) {
        return false;
    }

    $objekt = IPS_GetObject($id);

    if (
        $nurSichtbare
        &&
        $objekt['ObjectIsHidden']
    ) {
        return false;
    }

    return true;
}


// ============================================================
// QUELLSTRUKTUR PRUEFEN
// ============================================================

function ksPruefen(
    int $quelle,
    int $tiefe,
    string $modus,
    array &$statistik,
    array $grenzen,
    bool $nurSichtbare,
    array $ausgeschlossen
): void {

    $anzahlDieseSeite = 0;

    foreach (ksKinder($quelle) as $id) {

        if (!ksErlaubt(
            $id,
            $nurSichtbare,
            $ausgeschlossen
        )) {
            continue;
        }

        $obj = IPS_GetObject($id);
        $typ = (int)$obj['ObjectType'];

        if ($typ === 0) {

            if (
                $modus === 'start'
                ||
                $tiefe >= $grenzen['tiefe']
            ) {
                $statistik['uebersprungen']++;
                continue;
            }

            $statistik['kategorien']++;

            if (
                $statistik['kategorien']
                > $grenzen['kategorien']
            ) {
                throw new RuntimeException(
                    'Kategoriegrenze ueberschritten.'
                );
            }

            ksPruefen(
                $id,
                $tiefe + 1,
                $modus,
                $statistik,
                $grenzen,
                $nurSichtbare,
                $ausgeschlossen
            );

            continue;
        }

        if (!in_array($typ, [1, 2, 5, 6], true)) {
            $statistik['uebersprungen']++;
            continue;
        }

        $anzahlDieseSeite++;
        $statistik['kacheln']++;

        if ($anzahlDieseSeite > $grenzen['seite']) {
            throw new RuntimeException(
                'Zu viele Kacheln auf Seite: '
                . IPS_GetName($quelle)
            );
        }

        if (
            $statistik['kacheln']
            > $grenzen['gesamt']
        ) {
            throw new RuntimeException(
                'Kachelgrenze ueberschritten.'
            );
        }
    }
}


// ============================================================
// ROOT-KATEGORIE
// ============================================================

function ksRoot(
    int $parent,
    string $ident,
    string $name
): int {

    $id = @IPS_GetObjectIDByIdent(
        $ident,
        $parent
    );

    if ($id === false) {

        foreach (IPS_GetChildrenIDs($parent) as $kind) {

            $obj = IPS_GetObject($kind);

            if (
                (int)$obj['ObjectType'] === 0
                &&
                $obj['ObjectName'] === $name
            ) {
                throw new RuntimeException(
                    'Root-Kategorie bereits vorhanden: '
                    . $name
                    . ' / ID ' . $kind
                );
            }
        }

        $id = IPS_CreateCategory();

        IPS_SetParent($id, $parent);
        IPS_SetIdent($id, $ident);
    }

    $obj = IPS_GetObject($id);

    if ((int)$obj['ObjectType'] !== 0) {
        throw new RuntimeException(
            'Root ist keine Kategorie: ' . $ident
        );
    }

    if ($obj['ObjectName'] !== $name) {
        IPS_SetName($id, $name);
    }

    return $id;
}


// ============================================================
// SPIEGELKATEGORIE
// ============================================================

function ksKategorie(
    int $parent,
    int $quelle
): int {

    $obj = IPS_GetObject($quelle);

    $ident = 'MirrorCat' . $quelle;

    $id = @IPS_GetObjectIDByIdent(
        $ident,
        $parent
    );

    if ($id === false) {

        foreach (IPS_GetChildrenIDs($parent) as $kind) {

            $vorhanden = IPS_GetObject($kind);

            if (
                (int)$vorhanden['ObjectType'] === 0
                &&
                $vorhanden['ObjectName']
                    === $obj['ObjectName']
            ) {
                throw new RuntimeException(
                    'Kategorie ohne passenden Ident: '
                    . $obj['ObjectName']
                    . ' / ID ' . $kind
                );
            }
        }

        $id = IPS_CreateCategory();

        IPS_SetParent($id, $parent);
        IPS_SetIdent($id, $ident);
    }

    $alt = IPS_GetObject($id);

    if ((int)$alt['ObjectType'] !== 0) {
        throw new RuntimeException(
            'Kategorie-Ident-Konflikt: ' . $ident
        );
    }

    if ($alt['ObjectName'] !== $obj['ObjectName']) {

        IPS_SetName(
            $id,
            (string)$obj['ObjectName']
        );
    }

    if (
        (int)$alt['ObjectPosition']
        !== (int)$obj['ObjectPosition']
    ) {

        IPS_SetPosition(
            $id,
            (int)$obj['ObjectPosition']
        );
    }

    if (
        (bool)$alt['ObjectIsHidden']
        !== (bool)$obj['ObjectIsHidden']
    ) {

        IPS_SetHidden(
            $id,
            (bool)$obj['ObjectIsHidden']
        );
    }

    return $id;
}


// ============================================================
// LINK-ZIEL ERMITTELN
// ============================================================

function ksLinkZiel(int $quelle): int
{
    $obj = IPS_GetObject($quelle);

    if ((int)$obj['ObjectType'] === 6) {

        $link = IPS_GetLink($quelle);

        return (int)$link['TargetID'];
    }

    return $quelle;
}

// Die fruehere Gesamtansicht darf neben Susis Einzelkacheln nicht erscheinen.
function ksSusiIstGesamtansicht(int $ziel): bool
{
    $module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
    return IPS_InstanceExists($ziel)
        && IPS_GetInstance($ziel)['ModuleInfo']['ModuleID'] === $module
        && (int)IPS_GetProperty($ziel, 'View') === 0
        && (int)IPS_GetProperty($ziel, 'HomepagePart') === 0;
}

// Eigene Spiegel-Links und alte Fremdlinks: nur in Susis Root ausblenden.
// Keine Objekte loeschen, keine anderen Visualisierungen bearbeiten.
function ksSusiAlteGesamtansichtenAusblenden(int $root): int
{
    $queue = [[$root, 0]];
    $combined = [];
    $individual = 0;
    while ($queue) {
        [$category, $depth] = array_shift($queue);
        foreach (IPS_GetChildrenIDs($category) as $id) {
            $object = IPS_GetObject($id);
            if ((int)$object['ObjectType'] === 0) {
                if ($depth < 3 && !($object['ObjectIsHidden'] ?? false)) { $queue[] = [$id, $depth + 1]; }
                continue;
            }
            if ($object['ObjectIsHidden'] ?? false) { continue; }
            $target = ksLinkZiel($id);
            if (ksSusiIstGesamtansicht($target)) { $combined[] = $id; }
            elseif (IPS_InstanceExists($target)
                && IPS_GetInstance($target)['ModuleInfo']['ModuleID'] === '{9E33E109-4881-4E78-9906-38CAC2F1E210}') {
                $individual++;
            }
        }
    }
    if ($individual === 0) { return 0; }
    foreach ($combined as $id) { IPS_SetHidden($id, true); }
    return count($combined);
}

// Susi erhält eigene HTML-Instanzen. Ein Link auf Svens/Masters Instanz
// könnte keine andere Darstellung liefern. Die Statusquelle bleibt der Master.
function ksSusiZiel(int $ziel): int
{
    $module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
    if (!IPS_InstanceExists($ziel) || IPS_GetInstance($ziel)['ModuleInfo']['ModuleID'] !== $module) { return $ziel; }
    $config = json_decode(IPS_GetConfiguration($ziel), true, 512, JSON_THROW_ON_ERROR);
    if (!array_key_exists('ShieldCompact', $config)) { throw new RuntimeException('Hausstatus zuerst von main aktualisieren.'); }
    $source = (int)($config['ConfigSource'] ?? 0) ?: $ziel;
    if (!IPS_InstanceExists($source) || IPS_GetInstance($source)['ModuleInfo']['ModuleID'] !== $module
        || IPS_GetProperty($source, 'ConfigSource') !== 0) { throw new RuntimeException('Ungültige Hausstatus-Datenquelle für Susi.'); }
    $parent = IPS_GetParent(55503);
    $storage = @IPS_GetObjectIDByIdent('SVHSSusiMirrorViews', $parent);
    if ($storage !== false && IPS_GetObject($storage)['ObjectType'] !== 0) { throw new RuntimeException('Susis Ansichten-Kategorie ist belegt.'); }
    if ($storage === false) {
        $storage = IPS_CreateCategory(); IPS_SetParent($storage, $parent);
        IPS_SetIdent($storage, 'SVHSSusiMirrorViews'); IPS_SetName($storage, 'Hausstatus Susi – Ansichten'); IPS_SetHidden($storage, true);
    }
    $ident = 'SVHSSusiMirror_' . $ziel;
    $id = @IPS_GetObjectIDByIdent($ident, $storage);
    if ($id !== false && (!IPS_InstanceExists($id) || IPS_GetInstance($id)['ModuleInfo']['ModuleID'] !== $module
        || IPS_GetProperty($id, 'ConfigSource') !== $source)) { throw new RuntimeException('Susis Hausstatus-Kennung ist bereits belegt.'); }
    $new = $id === false;
    if ($new) {
        $id = IPS_CreateInstance($module); IPS_SetParent($id, $storage); IPS_SetIdent($id, $ident);
    }
    $properties = ['ConfigSource' => $source, 'ShieldCompact' => true, 'ActiveView' => true];
    foreach (['View', 'HomepagePart', 'RoomFilter', 'ExtraTileVariables'] as $key) {
        if (array_key_exists($key, $config)) { $properties[$key] = $config[$key]; }
    }
    $changed = $new;
    foreach ($properties as $key => $value) {
        if (IPS_GetProperty($id, $key) !== $value) { IPS_SetProperty($id, $key, $value); $changed = true; }
    }
    if (IPS_GetName($id) !== IPS_GetName($ziel)) { IPS_SetName($id, IPS_GetName($ziel)); }
    if ($changed) { IPS_ApplyChanges($id); }
    return $id;
}


// ============================================================
// LINK ERSTELLEN ODER AKTUALISIEREN
// ============================================================

function ksLink(
    int $parent,
    int $quelle,
    array &$statistik,
    string $benutzer = ''
): void {

    $obj = IPS_GetObject($quelle);

    $ident = 'MirrorLink' . $quelle;

    $ziel = ksLinkZiel($quelle);

    if ($benutzer === 'Susi') { $ziel = ksSusiZiel($ziel); }

    if (!IPS_ObjectExists($ziel)) {

        $statistik['fehler']++;
        return;
    }

    $id = @IPS_GetObjectIDByIdent(
        $ident,
        $parent
    );

    if ($id === false) {

        // Keine doppelten Links auf dasselbe Ziel.

        foreach (IPS_GetChildrenIDs($parent) as $kind) {

            $kindObj = IPS_GetObject($kind);

            if ((int)$kindObj['ObjectType'] !== 6) {
                continue;
            }

            $link = IPS_GetLink($kind);

            if ((int)$link['TargetID'] === $ziel) {

                $statistik['vorhanden']++;

                // Vorhandenen fremden Link
                // nicht veraendern.

                return;
            }
        }

        $id = IPS_CreateLink();

        IPS_SetParent($id, $parent);
        IPS_SetIdent($id, $ident);

        $statistik['neu']++;

    } else {

        if ((int)IPS_GetObject($id)['ObjectType'] !== 6) {
            throw new RuntimeException(
                'Link-Ident-Konflikt: ' . $ident
            );
        }

        $statistik['aktualisiert']++;
    }

    $alterLink = IPS_GetLink($id);

    if ((int)$alterLink['TargetID'] !== $ziel) {

        IPS_SetLinkTargetID(
            $id,
            $ziel
        );
    }

    $alt = IPS_GetObject($id);

    // Nur bei tatsaechlichen Aenderungen
    // aktualisieren.

    if ($alt['ObjectName'] !== $obj['ObjectName']) {

        IPS_SetName(
            $id,
            (string)$obj['ObjectName']
        );
    }

    if (
        (int)$alt['ObjectPosition']
        !== (int)$obj['ObjectPosition']
    ) {

        IPS_SetPosition(
            $id,
            (int)$obj['ObjectPosition']
        );
    }

    if (
        (bool)$alt['ObjectIsHidden']
        !== (bool)$obj['ObjectIsHidden']
    ) {

        IPS_SetHidden(
            $id,
            (bool)$obj['ObjectIsHidden']
        );
    }
}


// ============================================================
// STRUKTUR SPIEGELN
// ============================================================

function ksSpiegeln(
    int $quelle,
    int $ziel,
    int $tiefe,
    string $modus,
    int $maxTiefe,
    bool $nurSichtbare,
    array $ausgeschlossen,
    array &$statistik,
    string $benutzer = ''
): void {
    $gesehen = [];
    foreach (ksKinder($quelle) as $id) {

        if (!ksErlaubt(
            $id,
            $nurSichtbare,
            $ausgeschlossen
        )) {
            continue;
        }

        $obj = IPS_GetObject($id);

        $typ = (int)$obj['ObjectType'];

        if ($typ === 0) {

            if (
                $modus === 'start'
                ||
                $tiefe >= $maxTiefe
            ) {
                continue;
            }

            $neuesZiel = ksKategorie(
                $ziel,
                $id
            );
            $gesehen['MirrorCat' . $id] = true;

            ksSpiegeln(
                $id,
                $neuesZiel,
                $tiefe + 1,
                $modus,
                $maxTiefe,
                $nurSichtbare,
                $ausgeschlossen,
                $statistik,
                $benutzer
            );

            continue;
        }

        if (in_array($typ, [1, 2, 5, 6], true)) {

            // Auf Susi nicht erneut eine kombinierte Hausstatus-Kachel spiegeln.
            if ($benutzer === 'Susi' && ksSusiIstGesamtansicht(ksLinkZiel($id))) {
                continue;
            }

            ksLink(
                $ziel,
                $id,
                $statistik,
                $benutzer
            );
            $gesehen['MirrorLink' . $id] = true;
        }
    }
    // Alte eigene Spiegelobjekte bleiben erhalten, werden aber ausgeblendet,
    // wenn die Quelle weggefallen oder auf der Master-Seite ausgeblendet ist.
    // Fremde/manuell angelegte Kacheln bleiben unverändert.
    foreach (IPS_GetChildrenIDs($ziel) as $id) {
        $ident = IPS_GetObject($id)['ObjectIdent'];
        if (preg_match('/^Mirror(?:Cat|Link)[0-9]+$/D', $ident) && !isset($gesehen[$ident])) {
            IPS_SetHidden($id, true);
        }
    }
}


// ============================================================
// HAUPTPROGRAMM
// ============================================================

if (!IPS_ObjectExists($masterID)) {

    throw new RuntimeException(
        'Master 55503 nicht gefunden.'
    );
}

$masterObj = IPS_GetObject($masterID);

if ((int)$masterObj['ObjectType'] !== 0) {

    throw new RuntimeException(
        'Master ist keine Kategorie.'
    );
}

$grenzen = [
    'gesamt'     => $maxKacheln,
    'seite'      => $maxProSeite,
    'kategorien' => $maxKategorien,
    'tiefe'      => $maxTiefe
];

$ausgabe = [];

// Zuerst Struktur pruefen.
// Keine Objekte werden dabei geaendert.

foreach ($benutzer as $name => $config) {

    $statistik = [
        'kacheln'       => 0,
        'kategorien'    => 0,
        'uebersprungen' => 0
    ];

    ksPruefen(
        $masterID,
        0,
        $config['modus'],
        $statistik,
        $grenzen,
        $nurSichtbare,
        $ausgeschlossen
    );

    if ($manuell) {

        $ausgabe[] =
            $name
            . ': '
            . $statistik['kacheln']
            . ' Kacheln, '
            . $statistik['kategorien']
            . ' Kategorien';
    }
}

if ($manuell) {

    echo 'MASTER: '
        . $masterID
        . PHP_EOL
        . PHP_EOL;

    echo implode(PHP_EOL, $ausgabe);

    echo PHP_EOL . PHP_EOL;
}

if ($nurPruefen) {

    if ($manuell) {

        echo 'PRUEFMODUS'
            . PHP_EOL;

        echo 'Keine Objekte veraendert.'
            . PHP_EOL;
    }

    return;
}


// ============================================================
// GLEICHZEITIGE AUSFUEHRUNG VERHINDERN
// ============================================================

if (!IPS_SemaphoreEnter(
    'KachelSpiegelung55503',
    1000
)) {

    if ($manuell) {
        echo 'Synchronisierung laeuft bereits.';
    }

    return;
}

try {

    $targetParent = IPS_GetParent(
        $masterID
    );

    foreach ($benutzer as $name => $config) {

        $root = ksRoot(
            $targetParent,
            $config['ident'],
            $config['name']
        );

        $statistik = [
            'neu'          => 0,
            'aktualisiert' => 0,
            'vorhanden'    => 0,
            'fehler'       => 0
        ];

        ksSpiegeln(
            $masterID,
            $root,
            0,
            $config['modus'],
            $maxTiefe,
            $nurSichtbare,
            $ausgeschlossen,
            $statistik,
            $name
        );

        if ($name === 'Susi') {
            $statistik['alteGesamtansichtenAusgeblendet'] = ksSusiAlteGesamtansichtenAusblenden($root);
        }

        // Status nur bei manueller Ausfuehrung.

        if ($manuell) {

            echo PHP_EOL
                . '=== ' . $name . ' ==='
                . PHP_EOL;

            echo 'Neu: '
                . $statistik['neu']
                . PHP_EOL;

            echo 'Aktualisiert: '
                . $statistik['aktualisiert']
                . PHP_EOL;

            echo 'Bereits vorhanden: '
                . $statistik['vorhanden']
                . PHP_EOL;

            echo 'Fehler: '
                . $statistik['fehler']
                . PHP_EOL;
        }
    }

    if ($manuell) {

        echo PHP_EOL
            . 'Synchronisierung abgeschlossen.'
            . PHP_EOL;
    }

} finally {

    IPS_SemaphoreLeave(
        'KachelSpiegelung55503'
    );
}
