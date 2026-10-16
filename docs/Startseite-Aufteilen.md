# Hausstatus als einzelne Symcon-Kacheln

Modul von main aktualisieren und beim Master 52627 Änderungen übernehmen.
`Startseite-Aufteilen.php` vollständig als neues PHP-Skript in Symcon speichern
und einmal ausführen. Es verwendet die bereits eingestellte Startseitenkategorie
oder erkennt einen eindeutigen Link auf den Master. Bei mehreren möglichen
Kategorien listet es diese auf und verändert nichts. Dann im Skript die gewünschte
Kategorie-ID eintragen.

Die Kacheln übernehmen die aktuellen Inhalte und CSS-Stile der gemeinsamen
Übersicht. Zusammengehörige Gruppen bleiben erhalten: Anwesenheit/Alarm/Haustür,
PV/Batteriewarnung, Wohnzimmerlicht, Waschmaschine/Trockner, je eine Mediengruppe
pro Raum, Wetter mit Markise/Dachfenster, Räume mit Zusatzheizung und Heizungsprofil.
Bewegung, Raumgeräte und eigene Variablen erscheinen bei entsprechender Einrichtung
als weitere Kacheln. Die Datenquelle bleibt Master 52627; Node-RED bleibt unverändert.

Die äußere Anordnung und Größe eigenständiger Kacheln übernimmt Symcon. Dort ggf.
Breiten und Reihenfolge einmal passend setzen: Anwesenheit und PV nebeneinander,
Medien- und Wetter/Räume-Gruppen in voller Seitenbreite. Pixelgenaue Abstände über
Kachelgrenzen hinweg können die bisherigen HTML-Stile nicht festlegen.

Andere Links auf den Master werden nicht ausgeblendet. Die Einrichtung erzeugt
bei erneutem Ausführen keine Duplikate. Zum Rückgängigmachen beim Master
„Startseite aufteilen“ deaktivieren und Änderungen übernehmen; die Einzelkacheln
werden ausgeblendet und der gemeinsame Hausstatus wieder eingeblendet.
