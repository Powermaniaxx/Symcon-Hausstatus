# Shield-/Philips-Abfragen nur bei geöffnetem Hausstatus-WebFront

Ab Version **v0.85** unterstützt das Hausstatus-Modul einen lokalen, optionalen
HTTP-ViewerPing an Node-RED. Damit laufen die reinen Statuspolls für drei
Nvidia Shields und den Philips-TV **nur solange mindestens eine sichtbare
Hausstatus-Kachel geöffnet ist**.

## Bestand

- Master-Hausstatus: **52627** (unter Visualisierung `55503`)
- Synology Node-RED: `http://192.168.178.140:1880`
- Der vorhandene Node-Befehl `/data/shield/poll.js` bleibt unverändert.
- Alle vier ursprünglichen Inject-Nodes sind auf **60 Sekunden** gestellt,
  obwohl einige noch »alle 15 Sekunden« heißen.

## Einrichtung

1. **Vorhandene Node-RED-Flows als Backup exportieren.**
2. [Node-RED-Shield-Philips-WebFront-60s.json](Node-RED-Shield-Philips-WebFront-60s.json)
   in Node-RED über Menü **Importieren → Zwischenablage/Datei** als neuen Flow
   importieren und bereitstellen.
3. Beide bisherigen Tabs **»Shield Status (separat)«** und
   **»Philips TV Wohnzimmer«** in Node-RED deaktivieren und bereitstellen.
   **Nicht** gleichzeitig weiterlaufen lassen: Es wären sonst doppelte
   Statusabfragen. Alle anderen Node-RED-Flows bleiben unverändert.
4. In IP-Symcon das Hausstatus-Modul von `main` auf **v0.85** aktualisieren,
   dann die **zentrale Hausstatus-Instanz 52627** öffnen.
5. Im Panel **»Performance · Node-RED nur bei offenem WebFront«** die URL
   `http://192.168.178.140:1880/symcon/visu-ping`
   eintragen. **Änderungen übernehmen**.
6. Eine Hausstatus-Kachel im WebFront öffnen. Node-RED merkt den ersten Ping,
   führt die vier ADB/TV-Befehle einmal kurzfristig aus und pollt danach pro
   Gerät **alle 60 Sekunden** (zeitlich versetzt). Sind alle Kacheln/
   Browsertabs für **75 Sekunden** nicht sichtbar, stoppen die Polls automatisch.
7. Überprüfung: Im Node-RED-Debug kurz »… OK« einschalten. Bei geschlossenem
   WebFront sollten nach spätestens 75 Sekunden keine periodischen Status-
   Ausgaben mehr eintreffen. Beim Öffnen laufen die Polls wieder an.

### Schutz und Grenzen

- Diese Steuerung betrifft **nur** die vier Statuspolls für Shield Wohnzimmer,
  Shield Ankleidezimmer, Shield Schlafzimmer und Philips TV. Nicht betroffen
  sind Anwesenheit, Alarm, Waschmaschinen-/Trockner-Ansagen, Coming Home oder
  andere Automationen.
- Node-RED speichert das letzte HTTP-Lebenszeichen im Flow-Kontext. Standardmäßig
  ist die Freigabe nach jedem Node-RED-Neustart zunächst AUS, bis ein neues
  Lebenszeichen empfangen wird. Es werden keine WebFront-Kacheln erzeugt.
- Ab v0.85 werden Shield-/Philips-Werte erst nach **150 Sekunden** ohne neuen
  Status als veraltet markiert. Das vermeidet bei 60-Sekunden-Polling falsche
  Offline-Anzeigen. Bei ausgeschaltetem Polling sind keine aktuellen
  Gerätezustände verfügbar.
- Der HTTP-Endpoint hat **keine Authentisierung**. Nur im vertrauenswürdigen
  Heimnetz betreiben, keine Portweiterleitung/Internetfreigabe dafür einrichten.
- Die serverseitige Viewer-Erkennung beruht auf den vorhandenen Hausstatus-
  Kacheln. Nur eine andere native Symcon-Kachel ohne Hausstatus führt keinen
  ViewerPing aus. Sichtbare Tabs können je nach Browser im Hintergrund
  weiterlaufen; maßgeblich ist `document.visibilityState`.
- Die TV-/Shield-Befehle aus dem bisherigen `poll.js` werden nicht geändert.
  Es handelt sich um eine **Anzeigelast-Optimierung**, nicht um eine
  Abschaltung von für die Hausautomatik wichtigen Regeln.
