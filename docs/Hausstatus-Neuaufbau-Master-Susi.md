# Master und Susi ohne doppelte technische PV-Kachel neu aufbauen

Die Vollversion des Skripts steht in
[Hausstatus-Neuaufbau-Master-Susi.php](Hausstatus-Neuaufbau-Master-Susi.php).

1. Hausstatus-Modul von `main` auf Version **0.79** oder neuer aktualisieren.
2. In Symcon das alte Neuaufbau-Skript **41651** durch den **vollständigen Inhalt**
   der korrigierten PHP-Datei ersetzen. Das alte Skript nicht nochmals ausführen.
3. Das ersetzte Skript einmal manuell ausführen und die Konsolenausgabe prüfen.

Das Skript verschiebt eine bereits bestehende Hausstatus-Instanz
`SVHSTile_7` aus der Master-Kategorie `55503` in die vorhandene
Kategorie `PV-Details` (`SVHSPVPage`). Bei erneutem Lauf wird sie
dort wiederverwendet und **nicht** im Master-Wurzelverzeichnis neu erzeugt.

Die PV-Detailansicht bleibt für Symcon 9.0 erhalten; wenn sie direkt
in der PV-Kategorie liegt, benötigt sie keinen zusätzlichen Link.
Bereits vorhandene technische Shield-Variablen gehören zur jeweiligen
Modulinstanz und sind keine getrennten Kacheln.

Weder die Master-Datenquelle `52627`, noch die funktionierenden Kategorien
Anwesenheit/Heizung, die Susi-Ansicht oder Sophie werden beim Verschieben
gelöscht. Unbekannte Kennungen und doppelt belegte PV-Ziele lösen
stattdessen einen Abbruch aus.

**Wichtig:** Das Skript `41651` ist ein einmaliger Installer. Es darf
nicht als regelmäßiges Ereignis laufen und soll die deaktivierte alte
Master/Susi-Spiegelung nicht ersetzen.
