# Anwesenheit: sieben Tage ab v0.130

Auf der Anwesenheitsseite hat jede einzelne Karte den aufklappbaren Bereich „Verlauf · letzte 7 Tage“. Das gilt für die allgemeine Anwesenheit, Google Home, Sven, Susi und die vorhandenen Alarmkarten. Die Statusbezeichnungen bleiben passend zur Karte (Anwesend/Abwesend bzw. Scharf/Unscharf).

Der Bereich zeigt sieben lokale Kalendertage einschließlich heute. Pro Tag gibt es einen Zeitbalken von 00:00 bis 24:00; heute endet die Aufzeichnung beim aktuellen Zeitpunkt. Tage mit Zeitumstellung berücksichtigen ihre tatsächliche Länge. Der vorhandene 24-Stunden-Balken bleibt erhalten. Aufgeklappte Bereiche bleiben bei normalen Aktualisierungen geöffnet.

Alle Daten werden mit der normalen Anwesenheitsseite geliefert; es gibt keine zusätzliche Ladeanfrage beim Aufklappen. Doppelt verwendete Variablen werden einmal abgefragt. Fehlende Archivierung und unbekannte Abschnitte werden angezeigt; sie werden nicht als Abwesenheit erfunden. Die Änderung aktiviert keine Archivierung und verändert keine Statusvariable oder Aktion.

Die Abfragen sind insgesamt auf etwa 2.000 Statuswechsel pro Aktualisierung begrenzt. Bei Erreichen des Limits bleiben ältere Abschnitte vor dem ersten geladenen Ereignis unbekannt und ein Hinweis erscheint. Große Cache-Einträge werden nicht in einen einzelnen Symcon-Buffer geschrieben.

Prüfungen: `php tests/presence-week.php` (mbstring benötigt) und `node tests/presence-week.js`. Zusätzlich Desktop-/Smartphone-Browserprüfung mit synthetischen Daten, einschließlich Aufklappen aller Karten, Zeitumstellung, Teil-Tag und fortbestehender 24-Stunden-Anzeige. Live-Symcon-Prüfung steht aus.
