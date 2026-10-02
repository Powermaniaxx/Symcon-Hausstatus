# Sven Hausstatus 0.7

PHP-Modul fuer Symcon ab 9.0 mit HTML-SDK. Es zeigt Hausstatus, Temperaturen, Wetter, PV und Bewegungsmelder an und bietet konfigurierbare Bedienung fuer Licht, Cinema 40 und die Haustuer. Die Kommunikation verwendet die Anmeldung der Kachelvisualisierung. Keine WebHooks oder gespeicherten Kennwoerter.

## Aenderung in 0.7

Anwesenheit und Alarm stehen immer zuerst, die Haustuer immer direkt danach. Diese Reihenfolge gilt auch mobil. Letzte Oeffnung und letzte Schliessung stehen direkt im oberen Haustuerbereich unter den Bedienelementen. Der bisherige separate Haustuerbereich unten entfaellt. Werte und ihre Aktualisierung kommen weiterhin aus denselben konfigurierten Variablen. In der optionalen einzelnen Haustueransicht stehen die Zeitpunkte beim Schlossstatus. Die Cinema-Bedienung liest jetzt auch moderne Variablendarstellungen ueber IPS_GetVariablePresentation. Ein leeres klassisches Profil verhindert deshalb den Lautstaerkeregler nicht mehr, wenn eine gueltige Schieberegler-Darstellung vorhanden ist. Lautstaerke: 45376 (Master Volume, Float mit Aktion). Quelle: 16889 (Input Source, Integer mit Aktion). Die Grenzen und Schritte werden aus der aktuellen Darstellung oder dem Legacy-Profil gelesen. Ohne passende Daten zeigt die Kachel einen konkreten Grund. Keine festen dB-Grenzen werden geraten und keine AVR-Variablendarstellungen ueberschrieben.

## Aktuelle Einrichtung: gemeinsame kompakte Kachel

Bei bereits funktionierender gemeinsamer Kachel nur die Moduldateien aktualisieren und Instanz 52627 einmal uebernehmen. Kein Einrichtungsskript erneut ausfuehren und Skript 49024 nicht ersetzen. Falls noch Einzelkacheln angezeigt werden, docs/Kacheln_zusammenfassen_KOMPLETT.php in einem neuen temporaeren Symcon-Skript einmal ausfuehren. Instanz 52627 bleibt zentrale Konfiguration.

## Aktualisierung der vorhandenen Installation

Das ZIP enthaelt direkt library.json, den Ordner HausstatusBedienung und docs. ZIP entpacken. Im bestehenden Repository https://github.com/Powermaniaxx/Symcon-Hausstatus library.json und HausstatusBedienung durch die neuen Dateien ersetzen und die Aenderungen committen. Weder die ZIP-Datei selbst hochladen noch eine zusaetzliche Ordnerebene erstellen. library.json muss im Hauptverzeichnis liegen und Version 0.7 / Build 7 anzeigen. README.md kann ebenfalls ersetzt werden.

In Symcon unter Kern-Instanzen Modules / Module Control auf Aktualisierung pruefen klicken und anschliessend beim Repository den Update-Button ausfuehren. Bei einem Haken erkennt Symcon auf dem ausgewaehlten Repository-Zweig keinen neueren Commit. Dann pruefen, ob die neuen Dateien wirklich auf genau diesem Zweig gespeichert wurden. Zum Abgleich das Zahnrad am Repository verwenden.

Bestehende Instanz 52627 behalten. Danach diese Instanz oeffnen und Aenderungen uebernehmen, anschliessend die Kachelansicht neu laden. Das Einrichtungsskript nicht erneut ausfuehren und die Instanz nicht neu anlegen. Die Kachel nutzt weiterhin Kategorie 55503 und Link 29867; die alte Visualisierung wird nicht bearbeitet. Der Aktualisieren-Button in der HTML-Kachel holt lediglich aktuelle Werte, keine Moduldateien. Der nun unten rechts platzierte Aktualisieren-Button ueberlappt die native Ueberschrift nicht mehr. Sein Tooltip zeigt in dieser Version Hausstatus 0.7.

Wenn die Aktualisierung scheitert, die Fehlermeldung und die Repository-Zeile in Module Control festhalten. Keine Daten oder Instanzen loeschen.

## Meldungen und Darstellung

Befehl uebergeben wird nach drei Sekunden ausgeblendet. Das bedeutet, dass die Bedienaktion bearbeitet bzw. an das Aktionsskript weitergegeben wurde; es bestaetigt nicht automatisch den physischen Geraetezustand. Dieser wird weiterhin aus der Statusvariable gelesen. Fehler und fehlende Rueckmeldungen bleiben sichtbar. Ein neuer Befehl verwirft den vorherigen Ausblend-Timer. Die interne Ueberschrift Hausstatus entfaellt, da die native Kachel bereits so heisst. Das mobile Raster hat zwei Spalten.

## Haustuer

In Instanz 52627 Tueroeffnung mit Sicherheitsfrage aktivieren. Vorgabe: Integer-Bedienvariable 30053, Boolean-Freigabe 33983 und Tuerkontakt 47467. Die Freigabe wird direkt mit Freigeben aktiviert und mit Sperren zurueckgenommen. Freigeben allein sendet keinen Oeffnungsbefehl. Ein vorhandenes Aktionsskript der Freigabevariable wird verwendet; bei einer einfachen Boolean-Merkvariable wird nur deren Wert gesetzt. Der Oeffnen-Wert wird eindeutig aus dem Variablenprofil ermittelt; das Modul verwendet nicht ungeprueft den Geraetewert 2. Es ruft ueber RequestAction das vorhandene benutzerdefinierte Aktionsskript dieser Bedienvariable auf.

Die Frage lautet Haustuer wirklich oeffnen? Abbrechen sendet keinen Oeffnungsbefehl. Die Bestaetigung ist einmalig und 20 Sekunden gueltig. Vor Ausfuehrung werden die Freigabe und Konfiguration erneut geprueft. Andere native Tuerbedienelemente und eigenstaendig ausgefuehrte Skripte erhalten dadurch keine Sicherheitsfrage. Ein PHP-Skript kann alleine keinen Dialog in der Visualisierung anzeigen.

## Bewegungsmelder und 24-Stunden-Verlauf

Vorbelegt sind Flur 26325, Wohnzimmer Bewegung 58943, Wohnzimmer Praesenz 37345 und Terrasse 34118. Fuer Schlafzimmer Praesenz und Keller die passenden Boolean-Variablen in der Liste auswaehlen. Weitere Melder lassen sich hinzufuegen.

Archiv 0 bedeutet automatische Auswahl bei genau einem Archive Control. Bei mehreren Archiven das gewuenschte Archiv explizit waehlen. Die aktivierte Option MotionLogging aktiviert die Archivierung der ausgewaehlten Boolean-Variablen. Vorhandene Daten bleiben erhalten. Das Abschalten dieser Option deaktiviert bereits vorhandene Archivierungen nicht.

Die Anzeige zeigt aktuellen Zustand, Bewegungsphasen als Balken und aufklappbare Zeitpunkte aller archivierten Zustandswechsel der letzten 24 Stunden. Uhrzeiten erscheinen in Europe/Berlin. Dauerhaft erkannte Praesenz bleibt als Balken sichtbar. Nicht archivierte Vergangenheit kann nicht rekonstruiert werden. Erfasst werden Sensorzustaende, nicht jede einzelne koerperliche Bewegung. Das Archiv schreibt asynchron; der Verlauf kann kurz hinter dem aktuellen Zustand liegen. Archivdaten werden 30 Sekunden zwischengespeichert, bei Sensormeldungen neu abgefragt. Ein Abfragelimit von 10000 Eintraegen pro Sensor wird angezeigt.

## Weitere Quellen und Bedienung

Alle Quellen sind in der Instanz konfigurierbar. Vorgaben: Anwesenheit 12936, Schlossstatus 14438, Alarm innen 14477, Wohnzimmerlicht Status 57731, Helligkeit 31102, Cinema Status 45754, PV aktuell 55194 und Tagesenergie 50290. Cinema-Control und Anzahl Geraetewarnungen muessen ausgewaehlt werden. Ein unbekannter Wert wird nicht als Alles OK ausgegeben. Die Raumtemperaturen lassen sich in der Liste aendern. Die bisherige Vorgabe fuer Buerokeller 17053 muss bei Bedarf korrigiert werden.

Cinema-Bedienung braucht eine schaltbare Boolean-Variable. Die zuvor genannte AVR-ID 10950 ist vor Ort auf Typ und Aktion zu pruefen. Statusanzeige und Bedienvariable koennen verschieden sein.

Lichtbedienung ist anfangs deaktiviert. Bei aktiver Lichtautomatik zuerst ein Licht-Bedienskript anbinden, das den manuellen Befehl ausfuehrt UND der Automatik den manuellen Eingriff meldet. Direkte Hue-Bedienung erzeugt keinen Tastendruck auf den Fernbedienungsvariablen 19469 / 56892. Der vollstaendige Anschluss ist in docs/Wohnzimmer_Lichtautomatik_3_3_KOMPLETT.php enthalten. Damit den kompletten Inhalt des bestehenden Skripts 33054 ersetzen, speichern und einmal manuell ausfuehren. Anschliessend in Instanz 52627 genau Skript 33054 als Licht-Bedienskript waehlen und Aenderungen uebernehmen. Ohne ausgewaehltes Bedienskript bleiben die Lichtbuttons gesperrt. Die Erweiterung basiert auf deiner gespeicherten Lichtautomatik 3.2; falls dein live verwendetes Skript spaeter geaendert wurde, vor dem Ersetzen diese Aenderungen abgleichen.

Das ausgewaehlte Licht-Bedienskript bekommt ueber IPS_RunScriptEx folgende Parameter: $_IPS['COMMAND'] ist Light oder Brightness, $_IPS['VALUE'] ist Boolean fuer Licht bzw. Integer 1 bis 100 fuer Helligkeit und $_IPS['SOURCE'] ist HausstatusBedienung. Die Moduldateien schreiben keinen erfundenen Automatik-Zustand und simulieren keinen Fernbedienungstastendruck.

## Erstinstallation

Nur fuer eine noch nicht vorhandene Installation: Repository in Module Control hinzufuegen und docs/Einrichten_Kachel_KOMPLETT.php in einem neuen temporaeren Symcon-Skript einmal ausfuehren. Dieses richtet die Kachel ein und stoppt den alten Aktualisierungstimer 49024. Bei der bestehenden Instanz 52627 diesen Schritt ueberspringen.

## Pruefung

JSON und JavaScript-Syntax sowie die Bedienlogik im simulierten DOM wurden geprueft: Darstellung, Unicode, Textbehandlung, Aktionen, Fehler, Rueckmeldungen, automatisches Ausblenden, Abbrechen und Bestaetigen der Tuerfrage, Zuordnung zur anfragenden Anzeige und Bewegungsintervalle. Ein lokaler PHP-/Symcon-Laufzeittest war nicht verfuegbar. Die neue Version muss nach Installation in Symcon geprueft werden.

## Schnittstellen

https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/
https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/module-control/
https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggedvalues/


## Einrichtungshinweise zu 0.6

Die Tuer zeigt bei gesperrter Bedienung den konkreten Grund. Bei Tuerfreigabe aus zuerst in der Hausstatus-Kachel Freigeben waehlen. Nur falls die Moduloption Tueroeffnung aktiviert ist und die Bedienvariable 30053 ein vorhandenes benutzerdefiniertes Aktionsskript und eine eindeutige Oeffnen-Assoziation hat, wird danach der Button freigegeben. Bleibt er gesperrt, die angezeigte Begruendung pruefen. Die Freigabe wird nur durch ausdrueckliches Freigeben eingeschaltet; eine Aenderung der Freigabe verwirft laufende Oeffnungsbestaetigungen.

Geraetewarnungen: Im Feld die Integer-Variable Anzahl Geraetewarnungen auswaehlen. Dein gespeicherter zentraler Batteriewaechter erzeugt diese Variable als Kind seines Skripts. Nicht den Waechter selbst und nicht die String-Variable Letzte Geraetemeldung waehlen. Die konkrete ID ist unbekannt. Ohne Quelle wird Nicht eingerichtet angezeigt.

Lichtbefehle der Kachel setzen jetzt den manuellen Modus innerhalb der Lichtautomatik selbst. Ein schaltet auf Weiss 100 Prozent, der Regler schaltet manuell mit der gewaehlten Helligkeit ein. Aus aktiviert die vorhandene 30-Minuten-Sperre. Timer, Fernbedienung und Kachel verwenden dieselbe Semaphore. Der neue Befehlseinstieg verarbeitet keine manuell ausgefuehrte Einrichtung als Schaltbefehl.


## Optionale Einzelkacheln

Nur wenn einzelne Kacheln gewuenscht sind: docs/Kacheln_trennen_KOMPLETT.php in ein NEUES temporaeres Symcon-Skript kopieren, speichern und einmal manuell ausfuehren. Dieses verwendet Basis 55503 und die bestehende zentrale Instanz 52627. Es legt getrennte Kacheln fuer Anwesenheit und Alarm gemeinsam, Haustuer mit Freigabe und Oeffnung gemeinsam, Geraete, Wohnzimmerlicht, Cinema 40, PV-Anlage, Bewegungsmelder, Raumtemperaturen und Wetter an. Der bisherige Sammelkachel-Link 29867 wird erst nach erfolgreicher Einrichtung ausgeblendet. Alle Objekte bleiben erhalten. Andere bereits vorhandene Kacheln werden nicht bearbeitet.

Die neuen Instanzen lesen ihre Quellen und Bedienoptionen aus 52627. Auch Quelle und Lautstaerke dort auswaehlen. Aenderungen uebernehmen in 52627 uebernimmt anschliessend die abhaengigen Kacheln neu, damit ihre Quellenmeldungen registriert werden. Erneute Ausfuehrung des Aufteilungsskripts verwendet dieselben Identifikatoren und erzeugt keine weiteren Duplikate. View und ConfigSource sind lokale Einstellungen der jeweiligen Kachel; Quellenfelder in einer abhaengigen Instanz werden nicht verwendet. Ein Selbstverweis oder mehrere verkettete Konfigurationsquellen wird abgelehnt.

CinemaSource ist mit 0 vorbelegt. CinemaVolume verwendet die bestaetigte ID 45376. Die bestaetigte Cinema-Quelle ist 16889. In bestehenden Instanzen diese Variable im Quellenfeld auswaehlen, falls das Feld noch leer ist. Die Quelle wird mit dem formatierten Variablenwert angezeigt. Eine Auswahl erscheint bei einer schaltbaren Integer-Variable mit Optionen in der Aufzaehlungsdarstellung oder Legacy-Profil-Assoziationen; eine String-Statusvariable bleibt eine Anzeige. Lautstaerke wird in der von Symcon gelieferten Einheit angezeigt. Ein Regler erscheint bei schaltbarer Integer-/Float-Variable mit gueltigen Grenzen in der aktuellen Schieberegler-Darstellung oder im Legacy-Profil. Grenzen und Schrittweite werden auch vor dem Befehl geprueft. Schritt 0 wird bei Float als kontinuierlicher Regler behandelt. Es wird weder ein fester Prozentbereich angenommen noch eine Lautstaerkequelle aus einem anderen AVR geraten.

Das Aufteilungsskript ordnet die Warnanzahl automatisch zu, wenn genau eine Integer-Variable namens Anzahl Geraetewarnungen (mit Umlaut) als Kind eines PHP-Skripts existiert und bisher keine Quelle ausgewaehlt war. Bei mehreren Treffern oder fehlender Variable erfolgt keine Zuordnung; das Skript meldet dies. Der Batteriewaechter wird dadurch nicht ausgefuehrt.


## Kompakte gemeinsame Kachel ab 0.6

Die Gesamtdarstellung hat oben einen gemeinsamen Bereich fuer Anwesenheit und Alarm, kompakte Statusfelder fuer Geraete/PV und darunter getrennte Bedienbereiche fuer Haustuer, Licht und Cinema. Es gibt keine gestreckten gleich hohen Statuskarten. Mobil stehen Anwesenheit und Alarm zuerst, danach die Haustuer, dann Licht und Cinema und anschliessend Geraete und PV. Bei besonders schmalen Ansichten stehen Licht und Cinema untereinander.

Nur beim Wechsel von Einzelkacheln zur Gesamtansicht docs/Kacheln_zusammenfassen_KOMPLETT.php als neues temporaeres Skript einmal manuell ausfuehren. Beim bestehenden Gesamt-Hausstatus entfaellt dieser Schritt. Es blendet den bisherigen Link 29867 zur zentralen Instanz 52627 wieder ein, setzt die Gesamtansicht und blendet ausschliesslich die mit dem Aufteilungsskript erzeugten Links aus. Die ausgeblendeten Ansichtsinstanzen bleiben erhalten; ihre Aktualisierungstimer werden angehalten. Bei erneuter Anwendung des Aufteilungsskripts werden die Instanzen samt Timern wieder aktiviert. Fremde native Kacheln werden nicht bearbeitet.

Der Cinema-Lautstaerkeregler wird angezeigt, sobald in 52627 die passende schaltbare Lautstaerkevariable mit gueltiger Schieberegler-Darstellung oder Legacy-Profil ausgewaehlt ist. Beim Ziehen wird der gewaehlte Wert angezeigt; der Befehl wird beim Loslassen gesendet. Die bestaetigte Lautstaerkevariable ist 45376. Wenn die Variable oder gueltige Profilgrenzen fehlen, wird kein erfundener Regler angezeigt. Zum Ermitteln der noch fehlenden IDs kann docs/Cinema_Variablen_pruefen_KOMPLETT.php einmal manuell ausgefuehrt werden. Dieses liest nur und aendert keine Geraete.

## Darstellungen ab 0.7

Dokumentation der verwendeten API und Parameter:
https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/
https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/slider/
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/aufzaehlung/
