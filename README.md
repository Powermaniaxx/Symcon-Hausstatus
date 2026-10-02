# Sven Hausstatus 0.8

Symcon-Modul ab Version 9.0 mit HTML-Bedienung. Zentrale Instanz: 52627. Kachel-Basis: 55503. Hausstatus-Link: 29867. Vorhandene Quellen und Bedienoptionen bleiben in der zentralen Instanz konfiguriert.

## Aenderungen in 0.8

Anwesenheit und Alarm stehen zuerst, Haustuer direkt danach, auch mobil. Schlossstatus und Tuerkontakt werden jetzt unabhaengig angezeigt: Verriegelt/Aufgeschlossen kommt von 14438, Offen/Geschlossen von 47467. Ein offener Tuerkontakt ersetzt den Schlossstatus nicht mehr. Letzte Oeffnung und Schliessung stehen weiterhin im Haustuerbereich. Freigabe und Sicherheitsfrage bleiben Bestandteil der Tueroeffnung.

Der obere Bereich nutzt gleiche Kartenbreiten, Abstaende, Ueberschriften, Bedienelementhoehen und gemeinsame Zeilenhoehen. Mobil stehen Anwesenheit/Alarm und Haustuer jeweils ueber die gesamte Breite. Licht und Cinema stehen nebeneinander, auf sehr schmalen Anzeigen untereinander.

Markise und Dachfenster stehen zusammen in einem Block. Er zeigt Position, Status und Automatik, beim Dachfenster zusaetzlich Ueber Nacht. Positionsregler verwenden die aktuellen Grenzen und Schritte aus der Variablendarstellung oder dem Legacy-Profil. Prozentwerte werden nur fuer die Anzeige umgerechnet; an die vorhandene Aktion geht der urspruengliche Variablenwert. Ohne passende Variable oder Aktion bleibt das Element eine Anzeige mit Begruendung. Es werden keine Positionswerte, Motor-Richtungen oder Aktionswerte geraten.

Bewegung, Temperaturen und Wetter koennen als drei eigene Kacheln eingerichtet werden. Der zentrale Hausstatus blendet diese Detailbereiche dann aus. Bereits mit 0.5 erzeugte Detailinstanzen werden wiederverwendet; weitere vorhandene Raum- und Geraetekacheln unter 55503 werden nicht ausgeblendet, geloescht oder ersetzt.

## Bestehende Installation aktualisieren

ZIP entpacken. Im Repository https://github.com/Powermaniaxx/Symcon-Hausstatus library.json und den Ordner HausstatusBedienung durch die neuen Dateien ersetzen und committen. README.md und docs koennen ebenfalls aktualisiert werden. Keine ZIP-Datei und keine zusaetzliche Ordnerstufe hochladen. library.json liegt im Hauptverzeichnis und zeigt Version 0.8, Build 8.

In Symcon Module Control das Repository aktualisieren. Danach Instanz 52627 oeffnen und Aenderungen uebernehmen. Bestehende Instanz und Quellen behalten. Der Aktualisieren-Button in der Kachel holt Werte, keine neuen Moduldateien; sein Tooltip zeigt Hausstatus 0.8.

Fuer die gewuenschte Aufteilung anschliessend docs/Hausstatus_Detailkacheln_0_8_KOMPLETT.php vollstaendig in ein NEUES temporaeres PHP-Skript kopieren und einmal manuell ausfuehren. Nicht Skript 49024, das Lichtskript oder ein anderes vorhandenes Skript damit ersetzen. Dieses Skript laesst Hausstatus sichtbar, richtet Bewegung/Temperaturen/Wetter als eigene Kacheln ein und blendet nur die frueher vom Modul erzeugten anderen Einzelkacheln aus. Die vorhandenen Raum- und Geraetebereiche werden in seiner Ausgabe aufgefuehrt. Keine Geraete werden dabei geschaltet.

Das erstmalige Einrichtungsskript docs/Einrichten_Kachel_KOMPLETT.php fuer diese vorhandene Installation nicht erneut ausfuehren. Die alte Visualisierung unter 30848 wird nicht bearbeitet. Die neue Einrichtung verwendet ausschliesslich 55503.

## Quellen fuer Markise, Dachfenster und Haustuer

Die Vorgaben stammen aus der gespeicherten bisherigen Visualisierung. In 52627 sind sie aenderbar.

| Quelle | ID |
| --- | ---: |
| Schlossstatus | 14438 |
| Tuerkontakt | 47467 |
| Tuerfreigabe | 33983 |
| Tuer-Bedienvariable | 30053 |
| Letzte Oeffnung | 19534 |
| Letzte Schliessung | 55355 |
| Markise Position | 20434 |
| Markise Automatik | 44425 |
| Markise Status | 35313 |
| Dachfenster Position | 37131 |
| Dachfenster Automatik | 30082 |
| Dachfenster ueber Nacht | 44013 |
| Dachfenster Status | 25259 |

Positionsquellen muessen Integer-/Float-Variablen mit Bedienaktion und gueltiger Schieberegler-Darstellung oder Legacy-Profil sein. Automatikquellen muessen Boolean-Variablen mit Bedienaktion sein. Falls eine alte ID stattdessen eine Instanz oder ein Skript bezeichnet, im Quellenfeld die tatsaechliche Bedienvariable auswaehlen. Das Modul schaltet ueber RequestAction und nutzt damit vorhandene Geraete- oder benutzerdefinierte Aktionen. Es ersetzt keine vorhandene Markisen- oder Dachfensterautomatik. OutdoorEnabled schaltet die Bedienung in dieser Kachel frei oder sperrt sie.

## Tuerbedienung

DoorEnabled aktiviert Tuerfreigabe und Tueroeffnung. Freigeben allein sendet keinen Oeffnungsbefehl. Das Modul nutzt die Boolean-Freigabevariable 33983; eine vorhandene Aktion wird aufgerufen, eine reine Merkvariable nur gesetzt. Die Integer-Bedienvariable 30053 muss ein vorhandenes benutzerdefiniertes Aktionsskript und im Legacy-Profil eine eindeutige Oeffnen-Assoziation besitzen. Der Oeffnen-Wert wird aus diesem Profil gelesen, nicht fest angenommen.

Tuer oeffnen zeigt eine Sicherheitsfrage. Abbrechen sendet keinen Befehl. Die Bestaetigung ist einmalig, fuer die anfragende Anzeige bestimmt und 20 Sekunden gueltig. Freigabe und Plan werden vor der Ausfuehrung erneut geprueft. Ein als offen gemeldeter Tuerkontakt sperrt die Oeffnung. Andere native Tuerbedienelemente oder selbst ausgefuehrte Skripte bekommen dadurch keine zusaetzliche Frage.

## Cinema und Licht

CinemaVolume: 45376, Master Volume, Float mit Aktion. CinemaSource: 16889, Input Source, Integer mit Aktion. In bestehenden Instanzen bleiben die gewaehlten IDs erhalten; bei leerem Quellenfeld 16889 auswaehlen. Statusvorgabe: 45754. CinemaControl ist die vorhandene schaltbare Boolean-Power-Variable. CinemaEnabled aktiviert die Bedienung.

Moderne Schieberegler-Darstellungen und Aufzaehlungen werden ueber IPS_GetVariablePresentation gelesen. Klassische Profile funktionieren ebenfalls. Der Lautstaerkeregler nutzt native Grenzen, Schritte und Einheit. Fehlen geeignete Daten, zeigt die Kachel den Grund. Schritt 0 bedeutet bei Float einen kontinuierlichen Regler. Ein Befehl wird erst beim Loslassen gesendet. Die Quellenwahl verwendet ausschliesslich hinterlegte Integer-Optionen.

LichtEnabled aktiviert die Lichtbedienung. Bei aktiver Lichtautomatik docs/Wohnzimmer_Lichtautomatik_3_3_KOMPLETT.php als vollstaendigen Ersatz fuer Skript 33054 verwenden und danach 33054 als LightCommandScript waehlen. Falls die live verwendete Automatik inzwischen geaendert wurde, diese Aenderungen zuvor abgleichen. Ein setzt den manuellen Modus und Weiss; Aus setzt die vorhandene 30-Minuten-Sperre. Das Modul simuliert keine Fernbedienungstastendruecke und schreibt keinen erfundenen Automatikzustand.

## Bewegung, Temperaturen und Wetter

Vorgaben fuer Bewegung: Flur 26325, Wohnzimmer Bewegung 58943, Wohnzimmer Praesenz 37345, Terrasse 34118, Schlafzimmer Praesenz 22412 und Keller 16931. Eigene Eintraege und Auswahlen bleiben erhalten. Das Detail-Einrichtungsskript ergaenzt nur bekannte, bisher leere Schlafzimmer-/Keller-Eintraege, wenn die jeweilige Boolean-Variable existiert.

MotionArchive 0 waehlt bei genau einem Archive Control dieses Archiv. Bei mehreren Archiven das gewuenschte waehlen. MotionLogging aktiviert die Archivierung der ausgewaehlten Boolean-Variablen; sein Abschalten beendet bestehende Archivierungen nicht. Angezeigt werden aktueller Zustand, Bewegungsphasen und aufklappbare Zustandswechsel der vergangenen 24 Stunden in Europe/Berlin. Nicht aufgezeichnete Vergangenheit kann nicht rekonstruiert werden. Das Archiv schreibt asynchron. Pro Melder werden maximal 10000 Eintraege abgefragt und ein erreichtes Limit angezeigt.

Temperaturen verwenden die bestehende konfigurierbare Rooms-Liste. Wetter nutzt Wetterzustand, Wind, Regen, Warnstufe, Sonnenaufgang und Sonnenuntergang. Weitere vorhandene Raumsteuerungen bleiben in den bestehenden nativen Raumkacheln verfuegbar; die Temperaturkachel ersetzt diese nicht.

## Weitere vorhandene Inhalte

Der obere Hausstatus enthaelt weiterhin Geraetewarnungen, PV, Wohnzimmerlicht und Cinema. BatteryWarnings muss die Integer-Variable Anzahl Geraetewarnungen aus dem Batteriewaechter sein, nicht das Skript selbst. Bei fehlender Quelle zeigt das Modul Nicht eingerichtet statt Alles OK. PV verwendet 55194 und 50290.

Die neue Aufteilung betrifft nur dieses Modul und seine eigenen Links. Raumsteuerungen, weitere Licht- und Heizungsgeraete, Wasserbett, weitere Medienfunktionen und vorhandene PV-Details werden nicht aus ihren bestehenden Bereichen entfernt. Ob ausserhalb der gespeicherten bisherigen Fassung weitere live ergaenzte Inhalte existieren, konnte hier nicht vollstaendig inventarisiert werden; das Skript laesst fremde vorhandene Objekte stehen.

## Meldungen und alternative Ansichten

Befehl uebergeben verschwindet nach drei Sekunden. Es bestaetigt die Uebergabe an die Aktion, nicht automatisch den physischen Geraetezustand. Fehler bleiben sichtbar. Werte werden durch Quellenmeldungen und regelmaessig aktualisiert.

Optional: docs/Kacheln_zusammenfassen_KOMPLETT.php stellt alle Detailbereiche wieder innerhalb der gemeinsamen Kachel dar. docs/Kacheln_trennen_KOMPLETT.php teilt auch den oberen Hausstatus in Einzelkacheln auf. Fuer die jetzt gewuenschte Darstellung stattdessen Hausstatus_Detailkacheln_0_8_KOMPLETT.php verwenden. View, ConfigSource und ActiveView bestimmen lokale Ansicht, zentrale Konfiguration und Aktivierung. Alle Quellen und Bedienoptionen bleiben in 52627.

## Pruefung und Grenzen

JSON und JavaScript-Syntax sowie die Bedienlogik wurden in einem simulierten DOM geprueft: unabhaengige Schloss-/Kontaktzustaende, Reihenfolge, Einzelauswahl der Detailbereiche, Tuerfrage, Befehlsrueckmeldungen, Kinoquelle, Float-Regler und unveraenderte Rohwerte bei Prozentanzeigen. Die CSS-Regeln wurden auf passende Kartenbreiten und Umbruchregeln geprueft. Eine echte Browserdarstellung, PHP-/Symcon-Laufzeit und physische Geraete waren lokal nicht verfuegbar. Neue Quellen und Befehle muessen vor Ort geprueft werden.

https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/
https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/
https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/slider/
https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/aufzaehlung/
