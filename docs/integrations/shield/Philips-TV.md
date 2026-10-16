# Philips-TV Wohnzimmer: direkte Netzwerksteuerung

TV: Philips 65PFK6520/12, 192.168.178.190, WLAN. Am echten Gerät lesend geprüft:
JointSPACE 6.1.1, HTTPS-Port 1926 und Digest-Authentifizierung. Das Gerät benötigt
eine PIN-Kopplung. Kein HDMI-CEC-Aufruf wird verwendet.

## Vollständige Dateien installieren

Im Modul `HausstatusBedienung` gemeinsam ersetzen:
`module.php`, `ShieldSupport.php`, `form.json`, `module.html`.
Danach beim Master 52627 Änderungen übernehmen und die Visu neu laden.
Die Option „Philips TV Wohnzimmer anzeigen und bedienen“ liegt unter Medien/HEOS.
Sie ist standardmäßig aktiviert. TV-Status und Ein/Aus stehen unter der
Shield in der rechten Spalte von „Medien Wohnzimmer“.

Im bestehenden Node-RED-Datenordner `/data/shield` die vollständige `poll.js`
ersetzen und `philips-tv.js` hinzufügen. Die Shield-Flows bleiben bestehen.
`flow-philips-tv.json` als neuen Tab importieren; zunächst deaktiviert lassen,
bis die Kopplung abgeschlossen ist. Er verwendet die bereits funktionierenden
Symcon-Zugangsdaten des Containers. Keine zusätzlichen Node-Pakete erforderlich.

## Einmal am eingeschalteten TV koppeln

Auf der Synology per SSH, als normaler Containerbenutzer:

```sh
sudo docker exec -it -e PHILIPS_TV_MAC=DEINE_WLAN_MAC nodered-node-red1 node /data/shield/philips-tv.js pair
```

`DEINE_WLAN_MAC` durch die WLAN-MAC des TV ersetzen (Format AA:BB:CC:DD:EE:FF).
Die am TV angezeigte PIN an der SSH-Konsole eingeben. Dies ist weder das
Synology-Passwort noch das Symcon-Kennwort. Die PIN läuft nach kurzer Zeit ab;
gegebenenfalls den Kopplungsbefehl erneut starten.

Der Dialog speichert Benutzerkennung und Schlüssel ausschließlich in
`/data/shield/philips-tv.credentials.json`, mit Dateimodus 0600. Nicht in Git,
Flow-Exports oder Chat kopieren. Der vorhandene /data-Mount muss dauerhaft sein.
Zum Prüfen:

```sh
sudo docker exec nodered-node-red1 node /data/shield/philips-tv.js status
```

Danach den neuen TV-Flow aktivieren und geänderte Flows deployen.

## Ein/Standby

Die Visu übergibt Befehle über die vorhandene quittierte Symcon-Warteschlange.
Der zusätzliche TV-Flow fragt alle 15 Sekunden ab. „Ein“ setzt explizit On,
„Aus“ explizit Standby über die authentifizierte TV-API. Kein Power-Toggle.
Falls der TV beim Einschalten im Netzwerk schläft, wird ein Wake-on-WLAN-Paket
an die gespeicherte TV-MAC gesendet. „Mit WiFi einschalten / WoWLAN“ muss am TV
aktiv sein. Die Broadcast-Adresse ist standardmäßig 192.168.178.255; bei einem
anderen Subnetz beim Koppeln PHILIPS_TV_BROADCAST passend setzen.
Ein gesendetes Weckpaket bestätigt noch kein eingeschaltetes Gerät. Der
tatsächliche Zustand wird anschließend über die TV-API abgefragt.

Kurze Netzwerk-/TLS-Abbrüche bei einer Statusabfrage werden einmal nach 250 ms
wiederholt, wenn der erste Versuch weniger als 1,5 Sekunden gedauert hat.
Längere Timeouts und Schreibbefehle werden nicht automatisch wiederholt.
Ein Verbindungsabbruch vor dem TLS-Aufbau erlaubt beim expliziten Ein-Befehl
ebenfalls WLAN-Wecken. PIN- und Zertifikatsfehler erlauben dies nicht.

Wenn die HTTPS-Steuerung ausfällt, wird zusätzlich die öffentliche Systemantwort
auf HTTP-Port 1925 höchstens eine Sekunde lang abgefragt. Dabei werden keine
Zugangsdaten übertragen. Antwortet diese Schnittstelle, bleibt die Verbindung
online, der Betrieb aber unbekannt. Die Diagnose unterscheidet den Ausfall der
verschlüsselten Steuerung von einem insgesamt nicht erreichbaren TV. Dies ersetzt
keine Energiezustandsmessung und repariert keinen hängenden Dienst im TV.

TV-Ein bleibt auch bei nicht erreichbarer API verfügbar, damit WLAN-Wecken möglich
ist. Fehlende Kopplung wird als Diagnose angezeigt. Die Steuerung verwendet das
konkret am TV geprüfte Zertifikat; bei einem Zertifikatswechsel wird die Verbindung
abgewiesen. Keine globale Abschaltung von TLS-Prüfungen.

Statusvariablen sind separat (`PhilipsTvOnline`, `PhilipsTvPower`, `PhilipsTvError`,
`PhilipsTvUpdated` sowie kompatible Medienfelder). Master und abgeleitete Ansichten
verwenden dieselbe TV-Instanz. Ein deaktivierter Shield-Status deaktiviert den TV nicht.

Validierung: PHP-/JavaScript-Syntax, Digest-Challenge, separate TV-Warteschlange,
explizite On/Standby-Payloads, Wake-Paket, Ablehnung ungültiger Befehle,
keine Wake-Wiederholung bei Anmeldefehlern, TV-Darstellung und vorhandene
Shield-/Layout-Tests. Lesender Zugriff am echten TV mit Zertifikatsprüfung erfolgreich.
PIN-Kopplung und Schaltung wurden vom Nutzer bestätigt. Das Einschalten aus längerem
Standby muss getestet werden; WLAN-Wecken kann vom TV bzw. Access Point abhängen.

## HDMI 3 und automatische TV-Kopplung

Nach einem Ein-Befehl wird HDMI 3 einmal angewählt, sobald die API den TV als
eingeschaltet meldet. Während des Aufwachens wird bis zu 90 Sekunden versucht.
Zusätzlich steht bei eingeschaltetem TV die kleine Schaltfläche „HDMI 3“ bereit.
Der verwendete Eingang HW7 wurde am echten TV als HDMI 3 bestätigt.

`ShieldWohnzimmer-TV-Kopplung.php` vollständig in ein neues PHP-Skript in Symcon
kopieren und einmal manuell ausführen. Es legt ein eigenes Ereignis am Skript an,
das auf vollständige Statusmeldungen der Wohnzimmer-Shield in Master 52627 reagiert.
Wach schaltet den TV ein, Standby schaltet ihn aus. Unbekannte, veraltete oder
offline gemeldete Zustände lösen kein Ausschalten aus. Bei Wiederverbindung wird
einmal synchronisiert; unveränderte Statusmeldungen erzeugen keine neuen Befehle.
Die bestehenden Node-RED-Flows bleiben unverändert. Wegen der Abfrageintervalle
kann die Reaktion bis zu etwa 30 Sekunden plus TV-Startzeit dauern.

Protokollgrundlagen: [Philips-Integration](https://www.home-assistant.io/integrations/philips_js/)
und [ha-philipsjs](https://github.com/danielperna84/ha-philipsjs).
