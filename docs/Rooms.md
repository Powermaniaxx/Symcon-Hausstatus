# Räume ab v0.128

In den Hausstatus-Moduleinstellungen unter „Räume, Geräte und PV-Details“ die Spalte „Anzeigename (frei wählbar)“ bearbeiten und die Änderungen übernehmen. Dieser Name erscheint auf der Gerätekachel, auch wenn eine einzelne Variable unter einem allgemein benannten Objekt wie „HomeMatic Systemvariablen“ liegt. Die Spalte „Raum / Bereich“ legt die Raumzuordnung fest. Symcon-Objektnamen, Variablen-IDs und Aktionswege werden dafür nicht geändert.

„Klima allgemein“ erscheint nicht mehr auf Raumseiten. Das Heizungsprofil bleibt in seinen anderen Ansichten verfügbar.

Die Terrasse zeigt ihre Bereiche gemeinsam ohne Kategorie-Tabs. Ihre Bewegungsmelder erhalten die gleichen 24-Stunden-Verlaufsbalken und Zeitpunkte wie auf der Startseite. Es werden nur zur Terrasse gehörende Boolean-Bewegungsvariablen abgefragt; Präsenz und andere Räume werden nicht als Bewegung hinzugefügt. Fehlen Archivdaten, erscheint der vorhandene Hinweis aus der Bewegungsanzeige. Die Änderung aktiviert keine Archivierung.

Prüfung: `php tests/room-terrace.php` (mbstring erforderlich), `node tests/room-terrace.js`. Zusätzlich Browserprüfung mit synthetischen Daten bei Desktop- und Smartphone-Breite. Die private Symcon-Installation wurde nicht direkt geprüft.
