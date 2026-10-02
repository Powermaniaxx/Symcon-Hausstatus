# Sven Hausstatus 0.1

Eigenes PHP-Modul fuer Symcon ab 9.0 mit HTML-SDK. Es stellt den Hausstatus dar und bietet Wohnzimmerlicht Ein/Aus, einen Helligkeitsregler von 1 bis 100 Prozent und Cinema 40 Ein/Aus. Es erstellt keinen WebHook und speichert keine Kennwoerter. Die Kommunikation nutzt die Anmeldung der Kachelvisualisierung.

## Installation ueber GitHub und Module Control

1. ZIP entpacken. Der Ordner `SvenHausstatus` enthaelt `library.json`, `HausstatusBedienung` und `docs`.
2. Ein Git-Repository fuer das Modul anlegen. Den INHALT des Ordners `SvenHausstatus` dort hochladen: `library.json` muss direkt im Hauptverzeichnis des Repositories liegen. Es darf keine zusaetzliche Ordnerebene darueber geben.
3. In Symcon unter Kern-Instanzen die Instanz `Modules` / `Module Control` oeffnen. Ueber `+` die Repository-Adresse hinzufuegen. Das Repository ist in diesem Paket noch nicht veroeffentlicht; es gibt daher noch keine fertige Installations-URL.
4. Den kompletten Inhalt von `docs/Einrichten_Kachel_KOMPLETT.php` in ein NEUES temporaeres PHP-Skript in Symcon kopieren, speichern und einmal ausfuehren. Das Skript meldet die neue Instanz-ID.
5. Die neue Instanz oeffnen. Quellen kontrollieren. Bedienung getrennt fuer Licht und Cinema aktivieren. Kachelansicht neu laden.

Die Einrichtung verwendet die vorhandene Kategorie 55503 und den Link 29867. Der Link wird auf die neue Instanz umgestellt. Damit wird die bisherige HTML-Variable nicht mehr fuer diese Kachel verwendet. Nach erfolgreicher Einrichtung stoppt das Skript ausschliesslich den Timer des bisherigen Kachel-Skripts 49024. Die alte Visualisierung unter 30848 wird nicht bearbeitet. Die Instanz wird ausgeblendet, damit sie nicht zusaetzlich zum vorhandenen Link als zweite Kachel erscheint; zum Bearbeiten ggf. ausgeblendete Objekte in der Konsole anzeigen.

## Vorbelegte Variablen

| Funktion | Vorgabe |
|---|---:|
| Anwesenheit | 12936 |
| Schlossstatus | 14438 |
| Tuerkontakt | 47467 |
| Tuerfreigabe | 33983 |
| Alarm innen | 14477 |
| Wohnzimmerlicht Status / Schalten | 57731 |
| Wohnzimmerlicht Helligkeit | 31102 |
| Cinema Status | 45754 |
| Cinema schaltbare Power-Variable | 0, bewusst auszuwaehlen |
| Anzahl Geraetewarnungen | 0, optional auszuwaehlen |
| PV aktuell / heute | 55194 / 50290 |

Alle Variablen sowie die Raumtemperaturen koennen in der Konfiguration geaendert werden. Die ID 10950 wurde zuvor fuer den AVR genannt, die ID 45754 fuer den Status. Es ist nicht live geprueft, ob 10950 eine schaltbare Boolean-Variable ist. Diese deshalb in Symcon pruefen und dann als Cinema-Control auswaehlen. Eine Instanz-ID oder eine Variable ohne Aktion ist ungeeignet; das Modul lehnt sie ab. Ungueltige Statusquellen werden als unbekannt angezeigt.

## Lichtautomatik und manuelle Bedienung

Die Lichtbedienung ist anfangs deaktiviert. Der direkte Aufruf der Hue-Lichtvariablen erzeugt keinen Tastendruck auf den Fernbedienungsvariablen 19469 / 56892. Die bisherige Lichtautomatik erkennt einen solchen Befehl daher nicht automatisch als manuellen Eingriff. Ohne Anschluss an die Automatik koennte das Licht spaeter wieder automatisch umgeschaltet werden.

Fuer diesen Anschluss besitzt das Modul das Feld `Licht-Bedienskript fuer die Automatik`. Das ausgewaehlte Skript bekommt ueber `IPS_RunScriptEx` diese Parameter:

- `$_IPS['COMMAND']`: `Light` oder `Brightness`.
- `$_IPS['VALUE']`: Boolean fuer Licht oder Integer 1 bis 100 fuer Helligkeit.
- `$_IPS['SOURCE']`: `HausstatusBedienung`.

Dieses Skript muss den Befehl ausfuehren UND der Lichtautomatik den manuellen Eingriff melden. Das Modul schreibt absichtlich keinen erfundenen JSON-Zustand in die Automatik und simuliert keinen Fernbedienungstastendruck. Ein passendes Bedienskript ist noch nicht enthalten; dafuer wird die aktuelle vollstaendige Lichtautomatik benoetigt. Bei aktiver Morgen-/Nachtlichtautomatik die Lichtbedienung erst nach diesem Anschluss aktivieren. Cinema kann unabhaengig davon eingerichtet werden.

## Verhalten und Grenzen

Die Anzeige wird bei Aenderung konfigurierter Variablen aktualisiert, zusaetzlich alle 30 Sekunden. Ein Button sendet einen ausdruecklichen Ein- oder Aus-Befehl. Der Status wird aus der Geraetevariable gelesen; ein abgesendeter Befehl wird nicht als bestaetigter Geraetezustand ausgegeben. Die Helligkeit wird erst beim Loslassen des Reglers gesendet. Befehle akzeptieren nur die vorgesehenen Typen und Werte. Fehlende Aktionen deaktivieren die entsprechenden Bedienelemente.

Die neue Tueroeffnung ruft nach einer Sicherheitsfrage das bestehende Aktionsskript der Integer-Bedienvariable 30053 auf. Die bestehende Boolean-Freigabe 33983 muss aktiv sein. Die Bestaetigung ist einmalig und 20 Sekunden gueltig; vor der Ausfuehrung wird die Freigabe erneut geprueft. Andere native Tuerbedienelemente erhalten dadurch keine Sicherheitsfrage. Der Buerokeller hat als bisherige Vorgabe 17053, dieselbe Thermostatquelle wie im gelieferten Kachel-Skript; bei einer anderen tatsaechlichen Quelle die Liste korrigieren.

## Pruefung

JSON-Dateien und JavaScript-Syntax sind geprueft. Die JavaScript-Bedienlogik wurde mit einem simulierten DOM getestet: Darstellung, Textbehandlung, gesendete Aktionen, gesperrte Controls, Fehler, Befehl-Rueckmeldung und Unicode. Kein PHP-Laufzeittest und kein Test in einem echten Symcon-System waren in der Arbeitsumgebung moeglich. Version 0.1 ist deshalb fuer die erste Einrichtung und den Test auf deinem System vorgesehen.

## Dokumentation der verwendeten Symcon-Schnittstellen

https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/
https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/module-control/
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/struktur/


## Aktualisierung auf 0.2

ZIP entpacken und library.json sowie den Ordner HausstatusBedienung im bestehenden GitHub-Repository ersetzen und committen. Anschliessend in Symcon Module Control das Modul aktualisieren. Vorhandene Instanz 52627 behalten; das alte Einrichtungsskript nicht erneut ausfuehren. In Instanz 52627 Tueroeffnung aktivieren und Bedienvariable 30053 pruefen. Bestehende Freigabe 33983 wird weiter benoetigt.

Die interne Ueberschrift entfaellt, weil die Kachel bereits Hausstatus heisst.

Bewegungsmelder: Flur 26325, Wohnzimmer Bewegung 58943, Wohnzimmer Praesenz 37345, Terrasse 34118 sind vorbelegt. Fuer Schlafzimmer Praesenz und Keller die Boolean-Variablen auswaehlen; deren IDs sind noch unbekannt. Weitere Melder koennen zur Liste hinzugefuegt werden. Bei mehreren Archiven das gewuenschte Archiv explizit auswaehlen. Die aktivierte Option MotionLogging schaltet die Archivierung der ausgewaehlten Boolean-Variablen ein; vorhandene Daten bleiben erhalten. Deaktivieren der Option schaltet bestehende Archivierung nicht aus.

Die Anzeige zeigt den aktuellen Zustand, einen Balkenverlauf und aufklappbare Zeitpunkte aller Zustandswechsel der letzten 24 Stunden in Europe/Berlin. Dauerhaft erkannte Praesenz bleibt als Balken sichtbar. Nicht archivierte Vergangenheit kann nicht nachtraeglich rekonstruiert werden. Aufgezeichnet werden Sensorzustaende, nicht jede einzelne koerperliche Bewegung. Archivdaten werden maximal alle 30 Sekunden erneut geladen (bei Sensormeldungen frueher); das Archiv schreibt asynchron. Ein Abfragelimit von 10000 Eintraegen pro Sensor wird kenntlich gemacht.

Validierung: JavaScript-Syntax und Bedienlogik mit Mock-DOM geprueft. Kein lokaler PHP-/Symcon-Laufzeittest verfuegbar; die neue Version muss nach Installation in Symcon geprueft werden.
