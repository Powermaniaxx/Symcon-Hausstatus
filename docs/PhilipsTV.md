# Philips TV direkt in IP-Symcon 9.0

Das separate Repository [Philips-TV-Modul-for-Symcon](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon) enthält das **eigenständige Modul „Philips TV (JointSpace direkt)“ v1.0.0**. Die Verbindung zur Haussteuerung ist ab [Hausstatus 0.132](https://github.com/Powermaniaxx/Symcon-Hausstatus) verfügbar. Jede TV-Instanz verwaltet ihre eigene Verbindung und ihre eigenen Statusvariablen. Die Hausstatus-Kachel verwendet deren Status und Aktionen. Es werden keine Kopplungsdaten im Repository gespeichert.

## Einrichten

1. In Symcon unter **Modul Control → Hinzufügen** das separate Repository `https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon` hinzufügen. Für die Hausstatus-Kachel zusätzlich die Bibliothek **Symcon-Hausstatus auf 0.132 oder neuer** aktualisieren. Die Philips-Bibliothek lässt sich auch ohne Hausstatus verwenden.
2. In Symcon eine Instanz **Philips TV (JointSpace direkt)** erstellen.
3. IP-Adresse, Benutzername und Passwort der bereits funktionierenden Kopplung eintragen. Gemeint sind die erzeugten API-Zugangsdaten, nicht die TV-PIN und nicht das Passwort eines Philips-Kontos. Die bestehende Kopplung weiterverwenden.
4. Verbindung aktivieren, Änderungen übernehmen und **Status aktualisieren / Verbindung prüfen** drücken. Der TV sollte beim ersten Test eingeschaltet sein.
5. In der zentralen Hausstatus-Instanz unter **Wohnzimmer: Cinema 40, HEOS, Shield und Philips-TV** die neue **Direkte Philips-TV-Instanz** auswählen. „Philips TV anzeigen und bedienen“ aktivieren und Änderungen übernehmen. Abgeleitete Kacheln verwenden die Auswahl ihrer Konfigurationsquelle.
6. Erst wenn Anzeige und Bedienung funktionieren, den bisherigen Philips-Abfrage-Flow in Node-RED deaktivieren. Die Shield-Flows und eine vorhandene Shield/TV-Kopplung bleiben eigenständig. Eine vorhandene Kopplung über `ShieldPower_philips` wird automatisch an die neue TV-Instanz weitergereicht. Eine Instanzauswahl von 0 verwendet weiterhin den bisherigen Node-RED-Weg.

## Bedienung und Anzeige

- Ein/Aus mit ausdrücklichen `On`/`Standby`-Befehlen, kein Power-Toggle.
- Lautstärke in Prozent, Stumm und Ambilight ein/aus. Die Audio-Skala wird aus Minimum und Maximum des Fernsehers berechnet.
- Aktive Anwendung bzw. Eingangs-URI und Ambilight-Modus, soweit der TV diese Endpunkte unterstützt. Anwendungen können zunächst als technische Paketnamen erscheinen.
- Aufklappbare Fernbedienung mit Navigation, Home, Zurück, Eingangsmenü, Lautstärke, Wiedergabe und Info.
- Ein optionaler, vom TV nicht angebotener Endpunkt verhindert die anderen Funktionen nicht. Nicht unterstützte Endpunkte werden bis zum nächsten Übernehmen der Konfiguration nicht erneut abgefragt. Unvollständige oder veraltete Werte erzeugen keine bedienbare Audio-/Ambilight-Steuerung.

Befehle gehen unmittelbar über HTTPS auf Port 1926 an API 6 (Digest-Anmeldung). Vor einem Befehl wird keine komplette Statusrunde ausgeführt. Der Befehl erhält eine Bestätigung nach dem Senden; ein eigener Timer liest danach den tatsächlichen Zustand. Eine Zeitüberschreitung wiederholt keinen Schaltbefehl automatisch. Die native Symcon-Bedienung ist ebenfalls über die Variablenaktionen möglich.

## Standby

Standardmäßig fragt das Modul bei eingeschaltetem TV alle 15 Sekunden ab. Nach **bestätigtem Standby** pausiert es vollständig. Ein Einschaltbefehl oder manuelles Aktualisieren startet die Abfragen wieder. Optional kann die Variable mit dem Shield-Betriebszustand als **Wake-Signal** gewählt werden: ein Wechsel auf `awake`, `on` oder Boolean `true` setzt die Abfragen fort, schaltet den TV aber nicht selbst ein.

Wer mit der TV-Fernbedienung einschaltet und kein Wake-Signal verwendet, muss einmal aktualisieren oder ein Standby-Abfrageintervall einstellen. Für die Erkennung während der Pause gibt es bewusst keinen versteckten Hintergrund-Poll. Die Kachel nennt den Zustand dann **„Standby (zuletzt gemeldet)“**, nicht aktuell erreichbar. Ein Fehler wird nicht als bestätigtes Aus interpretiert.

Einschalten über die API setzt voraus, dass die TV-Netzwerkschnittstelle im Standby erreichbar bleibt. Ein vom Netz getrennter TV oder ein TV ohne erreichbare API im Tiefschlaf lässt sich damit nicht einschalten. Die konkrete Firmware muss am Gerät geprüft werden; eine automatische HDMI-Umschaltung wird nicht eingerichtet.

## Verbindung und Daten

Der TV verwendet üblicherweise ein selbstsigniertes TLS-Zertifikat. Die Zertifikatsprüfung ist deshalb für diese lokale TV-Verbindung standardmäßig aus und kann aktiviert werden. Zugangsdaten gehen ausschließlich an den konfigurierten HTTPS-Host; Weiterleitungen werden nicht verfolgt. Es gibt keine Debug-Ausgabe von Kennwort, Digest-Headern oder Antwortinhalten mit Zugangsdaten. Die Instanzkonfiguration und Symcon-Sicherungen enthalten die Zugangsdaten und sollten privat bleiben.

Statusvariablen: `Online`, `Power` (letzter bestätigter Boolean), `PowerState` (`awake`, `standby`, `unknown`), `Volume`, `Mute`, `Activity`, `Ambilight`, `AmbilightMode`, `Updated`, `LastError` sowie `State` als zusammenhängender Status für die Haussteuerung. Bei Fehlern können einzelne Variablen ihren letzten Messwert behalten; für die Gültigkeit immer `PowerState`, `Online`, Zeitstempel und `State` beachten. Die Hausstatus-Kachel wertet dies automatisch aus.

Für Skripte:

```php
SVPTV_UpdateStatus($tvInstance);
$status = json_decode(SVPTV_GetState($tvInstance), true);
IPS_RequestAction($tvInstance, 'Power', true);
IPS_RequestAction($tvInstance, 'Volume', 25);
IPS_RequestAction($tvInstance, 'Mute', false);
IPS_RequestAction($tvInstance, 'Ambilight', true);
IPS_RequestAction($tvInstance, 'RemoteKey', 'Home');
```

## Prüfung

Modultests im Philips-Repository: `tests/philips-direct.php`, `tests/philips-transport.php` und `tests/repository-structure.py`. Tests der Hausstatus-Verbindung im Hausstatus-Repository: `tests/philips-house.php` und `tests/philips-direct.js`. Der Transport-Test läuft **ohne** geladene cURL-Erweiterung, damit er die cURL-Funktionen isoliert nachbilden kann. Der Modultest benötigt cURL. Keine dieser Prüfungen sendet Befehle an einen echten TV.

Zusätzlich Browserprüfung mit 320/390 Pixeln Hochformat, 844 Pixeln Querformat und Desktop: kein horizontaler Überlauf, Touch-Bedienelemente, Fernbedienung auf-/zuklappen und korrekte Befehlsziele. Der Live-Test auf dem Fernseher und in Symcon steht noch aus.

Technische Referenzen: [Symcon PHP-Module](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/) und [JointSpace-Implementierung von ha-philipsjs](https://github.com/danielperna84/ha-philipsjs/blob/master/haphilipsjs/__init__.py).
