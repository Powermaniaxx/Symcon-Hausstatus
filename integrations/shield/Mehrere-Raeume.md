# Drei Shields und Medien Schlafzimmer

## Vollständige Moduldateien aktualisieren

Im Modulordner `HausstatusBedienung` diese vollständigen Dateien zusammen aktualisieren:

- `module.php`
- `ShieldSupport.php`
- `BedroomMediaSupport.php` (neu)
- `form.json`
- `module.html`

Danach im Master 52627 Änderungen übernehmen. Neue Variablen entstehen automatisch.
Die vorhandenen Wohnzimmer-Variablen und der Aufruf ohne Raumargument bleiben kompatibel.
Susis eigene Ansicht mit `ShieldCompact=true` zeigt bei allen drei Shields nur Online
und Diagnose. Master und Sven behalten die ausführliche Darstellung. AVR/Subwoofer
sind in allen Ansichten bedienbar, wenn die vorhandenen Symcon-Aktionen verfügbar sind.

## Node-RED ergänzen

Die vollständige neue `poll.js` unter `/data/shield/poll.js` ersetzen. Der vorhandene
Wohnzimmer-Flow bleibt unverändert. `flow-additional.json` als NEUEN Flow importieren
und geänderte Flows deployen. Nicht nochmals den bisherigen Wohnzimmer-Flow importieren.
Die zusätzlichen Abfragen laufen alle 15 Sekunden, beim Start zeitlich versetzt.
Die Zugangsdaten bleiben in den bereits funktionierenden Container-Umgebungsvariablen.

| Raumargument | Adresse | Gruppe |
|---|---|---|
| `wohnzimmer` (Standard ohne Argument) | 192.168.178.107:5555 | Medien Wohnzimmer |
| `ankleidezimmer` | 192.168.178.106:5555 | Medien Ankleidezimmer |
| `schlafzimmer` | 192.168.178.108:5555 | Medien Schlafzimmer |

Auf beiden zusätzlichen Shields Netzwerk-ADB aktivieren und gegebenenfalls den
RSA-Schlüssel dauerhaft bestätigen. Die bereits dauerhafte ADB-Einrichtung im
Node-RED-Container wird gemeinsam genutzt. Keine ADB-Server-Neustarts nötig.
Fehler und veraltete Daten werden für jedes Gerät unabhängig behandelt.

## Eine Gruppe Medien Schlafzimmer

Shield, Marantz SR-5010 und Subwoofer liegen gemeinsam in einer Gruppe.
Die Quellen sind anhand der bestätigten IDs voreingestellt und können beim Master
im Abschnitt „Medien Schlafzimmer · Marantz SR-5010 und Subwoofer“ angepasst werden:

| Bedienung | Variable |
|---|---|
| AVR Power | 13490 |
| MainZone Power | 47173 |
| Quelle | 33178 |
| Lautstärke | 18046 |
| Stumm | 41849 |
| Subwoofer Ein/Aus | 20175 |

Bedienung verwendet ausschließlich vorhandene `RequestAction`-Aktionen. Quellenoptionen,
Lautstärkegrenzen und Schrittweite stammen aus der jeweiligen Symcon-Variable.
Fehlen passende Aktionen oder Darstellungen, erscheint Status ohne erfundene Regler.
Eine Bedienung schaltet nur das ausgewählte Gerät; es gibt keine automatische Kopplung.

Geprüft: PHP-Syntax, getrennte Gerätezustände, Standby und Watchdog pro Gerät,
kompakte Anzeige aller Räume, AVR-/Subwoofer-Routing und ungültige Werte,
JavaScript-Syntax, Raumgruppen und unveränderte vorhandene Heizprofil-Tests.
Der bestehende Laundry-Test hat eine unabhängige unvollständige Fixture
(`IPS_GetInstanceListByModuleID` fehlt); die Laundry-Implementierung wurde nicht verändert.
Die neuen Geräte und Bedienaktionen müssen nach Installation am realen System geprüft werden.
