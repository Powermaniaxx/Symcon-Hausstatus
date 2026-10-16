# Shield Ein/Aus

Die drei Shield-Anzeigen haben Ein/Aus-Tasten, auch in Susis kompakter Ansicht.
Ein weckt mit Android KEYCODE_WAKEUP (224), Aus versetzt mit KEYCODE_SLEEP (223)
in Standby. Die Shield wird nicht vollständig heruntergefahren. TV/AVR werden
hierbei nicht direkt bedient; vorhandenes HDMI-CEC kann wie bei der Fernbedienung reagieren.

## Vollständige Dateien zusammen aktualisieren

- Im Modul: `module.php`, `ShieldSupport.php`, `module.html`.
- Im Node-RED-Container: `poll.js` unter `/data/shield/poll.js`.
- Anschließend beim Master 52627 Änderungen übernehmen; Visu neu laden.

Bestehende Flows und Container-Zugangsdaten bleiben unverändert. Kein zusätzlicher
HTTP-Endpunkt erforderlich. Vor einem Klick alle vier Dateien aktualisieren:
Ein alter Poller aktualisiert zwar weiterhin Status, führt aber keine Schaltbefehle aus.

Die nächste 15-Sekunden-Abfrage holt einen Befehl bei Symcon ab. Die folgende Abfrage
bestätigt den tatsächlichen Gerätestatus. Befehle sind pro Gerät getrennt, verfallen
nach 45 Sekunden und werden nach erfolgreicher ADB-Ausführung bestätigt. Ein neuer
Klick ersetzt einen noch ausstehenden Befehl derselben Shield. Separate Wake-/Sleep-
Befehle verhindern versehentliches Umschalten bei Wiederholungen.

Bei fehlenden/veralteten Statusdaten oder nicht autorisiertem ADB-Zugriff sind die
Tasten deaktiviert. Auf jeder neuen Shield die RSA-Freigabe dauerhaft bestätigen.
Die kompakte Ansicht erhält keine zusätzlichen Medien-/App-Details.

Geprüft: Queue pro Gerät, Bestätigung, Überholen alter Befehle, Verfallszeit,
strenge Werteprüfung, explizite ADB-Keycodes mit Testadapter, RPC-Antwort,
Tasten in voller/kompakter Ansicht, PHP- und JavaScript-Syntax.
Physische Geräte wurden aus dieser Umgebung nicht gesteuert.
