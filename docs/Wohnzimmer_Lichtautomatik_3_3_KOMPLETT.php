<?php
/*
 * Wohnzimmer Lichtautomatik, Version 3.3
 * Vollstaendiger Ersatz im EXISTIERENDEN Skript 33054.
 * Alte Frueh-/Nachtlicht-Ablaufplaene inkl. Statusplan deaktivieren.
 * Speichern und einmal manuell ausfuehren: richtet Ereignisse und Timer ein,
 * schaltet bei Einrichtung keine Lampen. Bestehende Sperre bleibt erhalten.
 *
 * Fruehlicht 05:30-08:00: Bewegung, Tag=false,
 * innen<=1, Terrasse<=4; weiss 100%. Ruhezeit 30 Minuten.
 * Nachtlicht ausserhalb 05:30-08:00: Bewegung, innen<=1, Terrasse<=4,
 * AVR 10950=false; rot 1%. Keine feste Abendzeit, Tag ist keine Sperre.
 * Nacht AUS: 3 Minuten alle Melder false, AVR an, Fruehzeit beginnt,
 * oder Terrasse>=6 fuer 5 Minuten. Bei AUS zuerst Weiss, dann AUS.
 * Um 05:30 bei aktiver Praesenz und gueltigen Fruehbedingungen direkt Weiss.
 * Manuell EIN geschuetzt; Manuell AUS sperrt beide bis 30 Minuten Ruhe.
 * Abwesenheit 12936=false seit 5 Minuten UND alle Melder false: AUS.
 * Farbbefehle und AUS werden nacheinander gesendet; Hue kann dabei kurz
 * Weiss zeigen. Das tatsaechliche Verhalten muss vor Ort geprueft werden.
 * Die alte Variable Nachtlicht (38164) wird nicht mehr benoetigt.
 */

$cfg = [
    'avr' => 10950,
    'nachtRuhe' => 180,
    'einTaste' => 19469,
    'ausTaste' => 56892,
    'melder' => [26325, 37345, 58943],
    'anwesenheit' => 12936,
    'tag' => 37975,
    'innen' => 50930,
    'aussen' => 21092,
    'licht' => 57731,
    'helligkeit' => 31102,
    'farben' => [40852, 21768, 41186],
    'beginn' => '05:30:00',
    'ende' => '08:00:00',
    'innenEinMax' => 1.0,
    'aussenEinMax' => 4.0,
    'aussenAusMin' => 6.0,       // Startwert: bei Bedarf anpassen
    'hellDauer' => 300,         // 5 Minuten ausreichend hell
    'ruheDauer' => 1800,        // 30 Minuten bis automatisches AUS
    'sperrDauer' => 1800,       // 30 Minuten Ruhe nach manuellem AUS
    'abwesenheitDauer' => 300,  // 5 Minuten; 12936 muss verlaesslich sein
];

$self = (int) $_IPS['SELF'];
$sender = $_IPS['SENDER'];
$source = ($sender === 'Variable') ? (int) $_IPS['VARIABLE'] : 0;
$now = time();
$parent = IPS_GetParent($self);
$lock = 'Morgenlicht_' . $self;
if (!IPS_SemaphoreEnter($lock, 5000)) {
    IPS_LogMessage('Wohnzimmer Lichtautomatik', 'Paralleler Aufruf konnte nicht verarbeitet werden.');
    return;
}

try {
    // Zuerst IDs und Typen pruefen; bei Fehler keine Schaltbefehle.
    $bools = array_merge($cfg['melder'], [
        $cfg['anwesenheit'], $cfg['tag'], $cfg['licht'], $cfg['avr']
    ]);
    $numbers = array_merge([$cfg['innen'], $cfg['aussen'], $cfg['helligkeit']], $cfg['farben']);
    foreach (array_merge($bools, $numbers, [$cfg['einTaste'], $cfg['ausTaste']]) as $id) {
        if (!IPS_VariableExists($id)) {
            throw new Exception('Variable ' . $id . ' fehlt.');
        }
        $type = IPS_GetVariable($id)['VariableType'];
        if (in_array($id, $bools, true) && $type !== 0) {
            throw new Exception('Variable ' . $id . ' muss Boolean sein.');
        }
        if (in_array($id, $numbers, true) && !in_array($type, [1, 2], true)) {
            throw new Exception('Variable ' . $id . ' muss eine Zahl enthalten.');
        }
    }

    $variable = function ($ident, $name, $type, $hidden = false) use ($parent) {
        $id = @IPS_GetObjectIDByIdent($ident, $parent);
        if ($id === false) {
            $id = IPS_CreateVariable($type);
            IPS_SetParent($id, $parent);
            IPS_SetIdent($id, $ident);
            IPS_SetName($id, $name);
            IPS_SetHidden($id, $hidden);
        }
        return $id;
    };
    $stateID = $variable('MorgenlichtState_' . $self, 'Lichtautomatik interner Zustand', 3, true);
    $statusID = $variable('MorgenlichtStatus_' . $self, 'Lichtautomatik Status', 3);
    $blockedID = $variable('MorgenlichtSperre_' . $self, 'Lichtautomatik nach manuellem Aus gesperrt', 0);
    $s = json_decode(GetValueString($stateID), true);
    if (!is_array($s) || !isset($s['mode'])) {
        $s = [
            'mode' => GetValueBoolean($cfg['licht']) ? 'manual' : 'idle',
            'offAt' => 0, 'brightSince' => 0, 'pendingUntil' => 0,
            'retryAt' => 0, 'initAt' => $now, 'lastRun' => $now
        ];
        // Eine Sperre aus dem bisherigen Skript bei der Migration erhalten.
        if (GetValueBoolean($blockedID)) {
            $s['mode'] = 'blocked';
            $s['offAt'] = $now;
        }
    }
    $save = function ($message) use (&$s, $stateID, $statusID, $blockedID, $now) {
        $s['lastRun'] = $now;
        SetValueString($stateID, json_encode($s));
        SetValueBoolean($blockedID, $s['mode'] === 'blocked');
        if (GetValueString($statusID) !== $message) {
            SetValueString($statusID, $message);
        }
    };

    // Commands from the HTML module use the same lock and state as the automation.
    if (isset($_IPS['COMMAND'])) {
        if (($_IPS['SOURCE'] ?? '') !== 'HausstatusBedienung') {
            throw new Exception('Unbekannte Quelle fuer manuellen Lichtbefehl.');
        }
        $command = $_IPS['COMMAND'];
        $value = $_IPS['VALUE'] ?? null;
        if ($command === 'Light') {
            if (!is_bool($value)) { throw new Exception('Light braucht Boolean.'); }
        } elseif ($command === 'Brightness') {
            if (!is_int($value) || $value < 1 || $value > 100) {
                throw new Exception('Brightness braucht Integer 1 bis 100.');
            }
        } else { throw new Exception('Unbekannter manueller Lichtbefehl.'); }
        $wasNight = $s['mode'] === 'night';
        $switchOn = $command === 'Brightness' || $value === true;
        $s['mode'] = $switchOn ? 'manual' : 'blocked';
        $s['brightSince'] = 0;
        $s['pendingUntil'] = $switchOn ? $now + 15 : 0;
        $s['retryAt'] = $now + 15;
        if (!$switchOn) { $s['offAt'] = $now; }
        // Save before sending: the next timer or feedback must respect manual intent.
        $save($switchOn ? 'Manuell EIN ueber Kachel: geschuetzt'
            : 'Manuell AUS ueber Kachel: gesperrt bis 30 Minuten Ruhe');
        if ($switchOn) {
            if ($wasNight || !GetValueBoolean($cfg['licht'])) {
                foreach ($cfg['farben'] as $id) { RequestAction($id, 16777215); }
            }
            RequestAction($cfg['helligkeit'], $command === 'Brightness' ? $value : 100);
            RequestAction($cfg['licht'], true);
        } else {
            try {
                if ($wasNight) {
                    foreach ($cfg['farben'] as $id) { RequestAction($id, 16777215); }
                }
            } finally { RequestAction($cfg['licht'], false); }
        }
        return;
    }

    if ($sender === 'Execute') {
        // Interne Identifikatoren bleiben erhalten: vorhandene Zustaende weiterverwenden.
        IPS_SetName($self, 'Wohnzimmer Lichtautomatik');
        IPS_SetName($stateID, 'Lichtautomatik interner Zustand');
        IPS_SetName($statusID, 'Lichtautomatik Status');
        IPS_SetName($blockedID, 'Lichtautomatik nach manuellem Aus gesperrt');
        // Alte Ereignisse deaktivieren; die Quellvariablen selbst bleiben bestehen.
        foreach ([13284, 38164] as $obsoleteID) {
            $obsoleteEvent = @IPS_GetObjectIDByIdent('MorgenlichtQuelle_' . $obsoleteID, $self);
            if ($obsoleteEvent !== false && IPS_EventExists($obsoleteEvent)) {
                IPS_SetEventActive($obsoleteEvent, false);
            }
        }
        $sources = array_unique(array_merge($cfg['melder'], [
            $cfg['einTaste'], $cfg['ausTaste'], $cfg['anwesenheit'],
            $cfg['licht'], $cfg['innen'], $cfg['aussen'], $cfg['tag'], $cfg['avr']
        ]));
        foreach ($sources as $id) {
            $ident = 'MorgenlichtQuelle_' . $id;
            $eid = @IPS_GetObjectIDByIdent($ident, $self);
            if ($eid === false) {
                $eid = IPS_CreateEvent(0);
                IPS_SetParent($eid, $self);
                IPS_SetIdent($eid, $ident);
            }
            IPS_SetName($eid, 'Lichtautomatik: ' . $id . ' ' . IPS_GetName($id));
            IPS_SetEventTrigger($eid, 0, $id);
            IPS_SetEventAction($eid, '{7938A5A2-0981-5FE0-BE6C-8AA610D654EB}', []);
            IPS_SetEventActive($eid, true);
        }
        IPS_SetScriptTimer($self, 5);
        $save('Eingerichtet; Modus: ' . $s['mode']);
        echo "Einrichtung abgeschlossen. Statusvariable: $statusID\n";
        echo "Alte Frueh-/Nachtlicht-Ablaufplaene inkl. Statusplan deaktivieren.\n";
        echo "Anwesenheit: 12936 (true=jemand da).\n";
        return;
    }

    $setColor = function ($color) use ($cfg) {
        foreach ($cfg['farben'] as $id) {
            RequestAction($id, $color);
        }
    };
    $isOn = GetValueBoolean($cfg['licht']);
    $button = (string) ($_IPS['VALUE'] ?? '');
    // Sowohl Hue-Rohwert als auch deutsche Darstellung unterstuetzen.
    $shortPress = in_array($button, ['short_release', 'Kurzer Tastendruck', 'Short release'], true);
    if ($source === $cfg['einTaste'] && $shortPress) {
        $wasNight = $s['mode'] === 'night';
        $s['mode'] = 'manual';
        $s['brightSince'] = 0;
        $s['pendingUntil'] = $now + 15; // Hue-Rueckmeldung abwarten
        $save('Manuell EIN: geschuetzt; AUS nur bei Abwesenheit oder Bedienung');
        if ($wasNight) {
            $setColor(16777215);
            RequestAction($cfg['helligkeit'], 100);
        }
        return;
    }
    if ($source === $cfg['ausTaste'] && $shortPress) {
        $wasNight = $s['mode'] === 'night';
        $s['mode'] = 'blocked';
        $s['offAt'] = $now;
        $s['brightSince'] = 0;
        $s['pendingUntil'] = 0;
        $save('Manuell AUS: gesperrt bis 30 Minuten ohne Bewegung/Präsenz');
        if ($wasNight) {
            try { $setColor(16777215); }
            finally { RequestAction($cfg['licht'], false); }
        }
        return;
    }

    // Ruhestand anhand letzter WERTÄNDERUNG, nicht letzter Aktualisierung.
    // Wiederholte false-Meldungen verlaengern die Ruhezeit nicht.
    $quietSince = (int) $s['initAt'];
    $allQuiet = true;
    $wasQuiet = true;
    $previousQuietSince = (int) $s['initAt'];
    foreach ($cfg['melder'] as $id) {
        $meta = IPS_GetVariable($id);
        $value = GetValueBoolean($id);
        $allQuiet = $allQuiet && !$value;
        $quietSince = max($quietSince, (int) ceil($meta['VariableChanged']));
        // Erste neue Bewegung nach 30 Minuten darf bereits wieder einschalten,
        // auch wenn der 5-Sekunden-Timer die Sperre noch nicht freigegeben hat.
        if ($source === $id && isset($_IPS['OLDVALUE'], $_IPS['OLDCHANGED'])) {
            $wasQuiet = $wasQuiet && !(bool) $_IPS['OLDVALUE'];
            $previousQuietSince = max($previousQuietSince, (int) ceil($_IPS['OLDCHANGED']));
        } else {
            $wasQuiet = $wasQuiet && !$value;
            $previousQuietSince = max($previousQuietSince, (int) ceil($meta['VariableChanged']));
        }
    }
    $movement = in_array($source, $cfg['melder'], true) && (bool) ($_IPS['VALUE'] ?? false);
    if ($s['mode'] === 'blocked') {
        $quietEnough = $allQuiet && $now - max($quietSince, $s['offAt']) >= $cfg['sperrDauer'];
        $quietBeforeMovement = $movement && $wasQuiet
            && $now - max($previousQuietSince, $s['offAt']) >= $cfg['sperrDauer'];
        if ($quietEnough || $quietBeforeMovement) {
            $s['mode'] = 'idle';
        }
    }

    // Ausfall-/Neustartluecken zaehlen nicht als bestaetigte Helligkeitsdauer.
    if ($now - $s['lastRun'] > 30) {
        $s['brightSince'] = 0;
    }

    // Rueckmeldung AUS beendet manuellen/automatischen Betrieb.
    if (!$isOn && $now >= $s['pendingUntil'] && in_array($s['mode'], ['manual', 'auto', 'night'], true)) {
        $wasNight = $s['mode'] === 'night';
        $s['mode'] = 'idle';
        $s['brightSince'] = 0;
        if ($wasNight && !($s['whiteRestored'] ?? false)) {
            $s['retryAt'] = $now + 15;
            $save('Nachtlicht aus: Weiss wiederherstellen');
            try { $setColor(16777215); }
            finally { RequestAction($cfg['licht'], false); }
            return;
        }
    }
    // Bereits/extern eingeschaltetes Licht vorsichtshalber als manuell behandeln.
    if ($isOn && $s['mode'] === 'idle') {
        $s['mode'] = 'manual';
    }

    $timeOfDay = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('H:i:s');
    $outside = (float) GetValue($cfg['aussen']);
    $inside = (float) GetValue($cfg['innen']);
    $absent = !GetValueBoolean($cfg['anwesenheit']);
    $absentSince = (int) ceil(IPS_GetVariable($cfg['anwesenheit'])['VariableChanged']);
    $absenceConfirmed = $absent && $allQuiet
        && $now - max($absentSince, $s['initAt']) >= $cfg['abwesenheitDauer'];

    $morningWindow = $timeOfDay >= $cfg['beginn'] && $timeOfDay < $cfg['ende'];
    $dark = $inside <= $cfg['innenEinMax'] && $outside <= $cfg['aussenEinMax'];
    $avrOn = GetValueBoolean($cfg['avr']);
    // Beim Wechsel von Rot zu Weiss kann das eigene Licht den Innenwert erhoehen.
    // Der Aussenwert bleibt daher fuer die laufende Nachtlicht-Uebergabe massgeblich.
    $morningEligible = !GetValueBoolean($cfg['tag'])
        && $outside <= $cfg['aussenEinMax'];
    if ($isOn && $s['mode'] === 'night' && $morningWindow && !$allQuiet
        && $morningEligible && !$absenceConfirmed) {
        $s['mode'] = 'auto';
        $s['brightSince'] = 0;
        $s['pendingUntil'] = $now + 15;
        $save('Fruehlicht: Nachtlicht auf Weiss umgestellt');
        $setColor(16777215);
        RequestAction($cfg['helligkeit'], 100);
        return;
    }

    $offReason = '';
    if ($isOn && $absenceConfirmed) {
        $offReason = '5 Minuten niemand im Haus und kein Melder aktiv';
    } elseif ($isOn && in_array($s['mode'], ['auto', 'night'], true)) {
        if ($s['mode'] === 'night') {
            if ($avrOn) {
                $offReason = 'AVR eingeschaltet';
            } elseif ($morningWindow) {
                $offReason = 'Fruehlicht-Zeitfenster beginnt';
            } elseif ($allQuiet && $now - $quietSince >= $cfg['nachtRuhe']) {
                $offReason = '3 Minuten ohne Bewegung/Praesenz';
            }
        } elseif (!$morningWindow) {
            $offReason = 'Morgen-Zeitfenster beendet';
        } elseif ($allQuiet && $now - $quietSince >= $cfg['ruheDauer']) {
            $offReason = '30 Minuten ohne Bewegung/Präsenz';
        }
        if ($outside >= $cfg['aussenAusMin']) {
            if ($s['brightSince'] === 0) {
                $s['brightSince'] = $now;
            }
            if ($now - $s['brightSince'] >= $cfg['hellDauer']) {
                $offReason = 'Terrassenhelligkeit mindestens 6 seit 5 Minuten';
            }
        } else {
            $s['brightSince'] = 0;
        }
    } else {
        $s['brightSince'] = 0;
    }

    if ($offReason !== '') {
        if ($now >= $s['retryAt']) {
            $s['retryAt'] = $now + 15;
            $save('Schalte AUS: ' . $offReason);
            try {
                if ($s['mode'] === 'night' && !($s['whiteRestored'] ?? false)) {
                    $setColor(16777215);
                    $s['whiteRestored'] = true;
                    $save('Schalte AUS: ' . $offReason);
                }
            } finally {
                RequestAction($cfg['licht'], false);
            }
        }
        return;
    }

    $mayStart = $movement && !$isOn && $s['mode'] === 'idle'
        && $now >= $s['retryAt'] && $dark;
    $startMorning = $mayStart && $morningWindow && $morningEligible;
    $startNight = $mayStart && !$morningWindow && !$avrOn;

    if ($startMorning || $startNight) {
        $s['mode'] = $startMorning ? 'auto' : 'night';
        $s['whiteRestored'] = false;
        $s['pendingUntil'] = $now + 15;
        $s['retryAt'] = $now + 15;
        $save($startMorning ? 'Fruehlicht automatisch: Weiss 100 %' : 'Nachtlicht automatisch: Rot 1 %');
        // Zuerst die geringe Helligkeit setzen, damit Rot nicht mit 100 % startet.
        RequestAction($cfg['helligkeit'], $startMorning ? 100 : 1);
        $setColor($startMorning ? 16777215 : 16711680);
        RequestAction($cfg['licht'], true);
        return;
    }

    $labels = [
        'night' => 'Nachtlicht Rot 1 %: AUS nach 3 Minuten Ruhe, bei AVR oder Tageslicht',
        'manual' => 'Manuell EIN: geschuetzt; AUS nur bei Abwesenheit oder Bedienung',
        'auto' => 'Automatisch EIN: Zeit, Ruhezeit und Tageslicht werden geprueft',
        'blocked' => 'Manuell AUS: gesperrt bis 30 Minuten ohne Bewegung/Präsenz',
        'idle' => 'Bereit: wartet auf Bewegung und Einschaltbedingungen'
    ];
    $save($labels[$s['mode']]);
} catch (Throwable $e) {
    IPS_LogMessage('Wohnzimmer Lichtautomatik', $e->getMessage());
    if (isset($statusID)) {
        SetValueString($statusID, 'Fehler: ' . $e->getMessage());
    }
    if (isset($_IPS['COMMAND'])) { throw $e; }
    if ($sender === 'Execute') {
        echo 'Fehler: ' . $e->getMessage();
    }
} finally {
    IPS_SemaphoreLeave($lock);
}
