# Nvidia Shield → Symcon-Hausstatus

Vollständige Dateien zur Erweiterung des Repository-Stands
`2c3b390cf95fcf95251edf63debb4f30fd3cb9dc` (main, 16.10.2026).
Shield: `192.168.178.107:5555`. Symcon: `http://192.168.178.140:3777/api/`.
Node-RED-Container: `nodered-node-red1`. Keine vorhandenen Flows ersetzen.

## 1. Moduldateien

Das Paket enthält das komplette Modul einschließlich aller vorhandenen Supportdateien.
Die Änderungen betreffen `module.php`, `module.html`, `form.json` sowie die neue
`ShieldSupport.php`. Zusammen einspielen; nicht nur module.php kopieren.
In der zentralen Hausstatus-Instanz unter „09 · Mediengeräte und HEOS“ die Option
„Nvidia Shield Status anzeigen“ aktivieren und Änderungen übernehmen.
Es entstehen automatisch sieben Statusvariablen ohne Bedienaktion:
ShieldOnline (Boolean), ShieldPower, ShieldApp, ShieldPlayback, ShieldTitle,
ShieldError (String), ShieldUpdated (Integer, Unix-Zeit).
Die vorhandene Cinema-Ansicht (View 6) und Übersicht zeigen eine eigene Shield-Kachel
innerhalb der Cinema/HEOS-Kachel. Abgeleitete Ansichten lesen die Variablen über
ihre vorhandene zentrale ConfigSource. Keine zusätzlichen Quellen-IDs zuordnen.

## 2. Dauerhaftes ADB vorbereiten

Auf der Synology zunächst `sh inspect-setup.sh` ausführen. Das zeigt Image, Mounts,
Node-Version und Paketverwaltung, ohne Passwörter oder private Schlüssel auszugeben.
Node muss mindestens Version 18 haben. Vor Änderungen die bestehende Container-
Konfiguration und das gesamte /data-Verzeichnis sichern.

`/data` muss bereits ein dauerhaftes Volume oder Hostverzeichnis sein. Diesen Mount,
alle bisherigen Ports, Umgebungsvariablen und Container-Einstellungen beibehalten.
Den ganzen Ordner dieser Anleitung als `/data/shield` ablegen (über den ermittelten
Host-Mount oder `docker cp`). Danach auf der Synology:

```sh
sudo docker exec nodered-node-red1 sh /data/shield/persist-keys.sh
```

Dies übernimmt einen vorhandenen Schlüssel des normalen Containerbenutzers und
überschreibt keinen bereits gesicherten Schlüssel. Falls ADB bisher als anderer
Benutzer lief, dessen bereits autorisiertes `.android/adbkey` und optional
`adbkey.pub` gezielt nach `/data/shield-adb/.android/` kopieren und dem normalen
Node-RED-Benutzer lesbar machen. Private Schlüssel niemals ins Repository laden.

Eine Paketinstallation ausschließlich im laufenden Container geht beim Neuerstellen
verloren. Deshalb das bestehende Image mit ADB erweitern. Für Alpine das beiliegende
Dockerfile, für Debian Dockerfile.debian verwenden. Das tatsächlich ermittelte Image
als Build-Argument angeben, vorzugsweise mit festem Tag/Digest:

```sh
sudo docker build --build-arg NODE_RED_IMAGE=DEIN_BISHERIGES_IMAGE -t local/nodered-shield:1 -f Dockerfile .
```

Bei Debian `-f Dockerfile.debian` verwenden. Bestehende Compose-/Containerdefinition
gezielt auf dieses Image umstellen und denselben Container mit allen bisherigen
Einstellungen und demselben /data-Mount neu erstellen. Hier liegt bewusst keine
ersetzende Compose-Datei bei, da die bestehende Konfiguration unbekannt ist.
Ein Neustart betrifft kurzzeitig auch vorhandene Flows; passend terminieren.

ADB auf der Shield in den Entwickleroptionen für Netzwerkzugriff aktivieren. Falls
ein neuer Schlüssel verwendet wird, die RSA-Abfrage auf der Shield dauerhaft zulassen.
Manueller Test nach dem Neustart, als normaler Containerbenutzer:

```sh
sudo docker exec -e HOME=/data/shield-adb -e ANDROID_USER_HOME=/data/shield-adb/.android -e ADB_VENDOR_KEYS=/data/shield-adb/.android nodered-node-red1 adb connect 192.168.178.107:5555
sudo docker exec nodered-node-red1 adb -s 192.168.178.107:5555 get-state
```

Erwartet: `device`. Kein regelmäßiges `kill-server`, keine Wake-/Power-Tasten.
Der Poller verbindet sich automatisch erneut und weckt die Shield nicht absichtlich.
ADB-Schlüssel bleiben in /data/shield-adb erhalten.

## 3. Verbindung zu Symcon

In der bestehenden Containerkonfiguration ergänzen und beim Neuerstellen beibehalten:

```text
SYMCON_RPC_URL=http://192.168.178.140:3777/api/
SYMCON_INSTANCE_ID=52627
SYMCON_RPC_USER=<nur falls der JSON-RPC-Zugang Basic Auth benötigt>
SYMCON_RPC_PASSWORD=<zugehöriges Passwort; lokal speichern>
```

URL und Instanz-ID 52627 sind bereits im Poller voreingestellt. Zugangsdaten aus
einer bereits funktionierenden lokalen Symcon-Anbindung übernehmen; nicht in Chat,
Flow-Export oder Git speichern. Bei 401/403 Authentifizierung bzw. erlaubten lokalen
Zugriff in Symcon prüfen; der Poller schaltet keine Sicherheitseinstellung ab.
Die Werte müssen im Containerprozess verfügbar sein; reine Node-RED-Flowvariablen
werden nicht automatisch an den exec-Prozess vererbt.

Die ID steht in der Symcon-Konsole bei der markierten Hausstatus-Instanz. Verwendet
wird die zentrale Instanz mit `ConfigSource = 0`, nicht eine Unteransicht.
Zum Auffinden kann `FindHausstatus.php` als vollständiges temporäres PHP-Skript in
der Symcon-Konsole ausgeführt werden. Es liest ausschließlich die Instanzliste.

## 4. Separaten Flow importieren

`flow.json` über Import als neuen Flow importieren. Es werden ausschließlich Core-
Nodes benötigt. Mit „Geänderte Flows“ deployen; bestehende Tabs unverändert lassen.
Der Flow startet nach drei Sekunden und pollt alle 15 Sekunden. Er führt im Container
`node /data/shield/poll.js` aus; kein Docker-Socket und kein sudo für Node-RED nötig.
Der Poller ruft `SVHS_UpdateShield(Instanz-ID, JSON)` über JSON-RPC auf.
Fehler erscheinen im separaten Debug-Knoten; der Erfolgs-Debug ist optional deaktiviert.

## Verhalten und Prüfung

- Aktive App aus `dumpsys activity activities`, Energiezustand aus `dumpsys power`.
- `active=true`-Mediensitzungen werden einzeln ausgewertet. State 3 = playing/laufend,
  State 2 = paused/pausiert. Laufende Vordergrundsitzung hat Vorrang; andernfalls
  neueste laufende Sitzung (Hintergrundaudio). Pausierte fremde Sitzungen liefern
  keine alten Titel. Ohne Sitzung bei wacher Shield: stopped.
- Titel aus Androids `metadata … description`; das ist eine formatierte Anzeige,
  keine strukturierte Metadaten-API. Bei Apps mit anderem Format kann eine Anpassung
  anhand eines tatsächlichen dumpsys-Auszugs erforderlich sein.
- Asleep/Dozing = standby; Awake/Dreaming = awake. Im Standby werden App/Titel geleert
  und Playback unknown. Nicht erkannte Energiezustände bleiben unknown.
- „Offline“ heißt hier: kein nutzbarer ADB-Zugriff. Die Diagnose unterscheidet
  Verbindungs-/Autorisierungs-/Timeoutfehler soweit möglich. Es beweist keine
  physische Trennung vom LAN. Standby wird nur bei erfolgreicher Power-Abfrage gesetzt.
- Nach über 60 Sekunden ohne empfangenen Poll setzt Symcon online auf false,
  leert App/Titel und meldet veraltete Daten. ShieldUpdated behält den letzten Empfang.
- Abfragen sind zeitlich begrenzt; ein Lock verhindert parallele Polls. HTTP/RPC-
  Fehler werden angezeigt, ohne URL-Passwörter oder Status-Medientitel zu protokollieren.

Nach Einrichtung prüfen: laufendes Video, Pause, App-Wechsel, Standby, ADB-Verlust,
Flow für >75 Sekunden stoppen, Wiederanlauf sowie Container-Neuerstellung. Alle
Werte und die nur lesbare Kachel kontrollieren. Lokale Tests: `node tests/shield.js` und `node tests/shield-rpc.js`.
Ein Test am echten Gerät und der echten Symcon-Instanz steht noch aus.

Grundlagen: [Node-RED Docker und persistentes /data](https://nodered.org/docs/getting-started/docker),
[eigene Images](https://nodered.org/docs/getting-started/docker-custom),
[Android ADB und RSA-Autorisierung](https://developer.android.com/tools/adb).
