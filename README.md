# Sven Hausstatus 0.10

IP-Symcon-Modul ab Version 9.0. Dieses Update erweitert die bestehende Fassung 0.9. Zentrale Instanz: **52627**. Kachelbereich: **55503**. Hausstatus-Link: **29867**.

## Neu in 0.10

- **Markise und Dachfenster:** Im vorhandenen gemeinsamen Startseitenblock stehen jetzt eigene Statuszeilen. Dachfenster verwendet **25259**, Markise/Rollo **35313**. Position und Automatik bleiben daneben verfügbar.
- **Räume und Geräte:** Eigene HTML-Raumseiten mit den bisherigen Raumverknüpfungen, ergänzt um die bekannten Licht-, Heizungs-, Medien-, Wasserbett-, Küchen- und Terrassenquellen. PV erhält eine eigene Detailkachel.
- **Bedienung:** Schalter, hinterlegte Auswahlwerte, Regler und RGB-Farbe verwenden die vorhandenen Variablenaktionen und Darstellungen. Andere Bedienoberflächen öffnen sich über „Bedienung öffnen“ direkt in Symcon.
- **Umlaute:** Beschriftungen, Meldungen, Einrichtungsausgaben und diese Anleitung verwenden richtige Umlaute. Das neue Einrichtungsskript korrigiert auch bekannte alte Schreibweisen in den übernommenen Raum- und Meldernamen. Technische Eigenschaftsnamen, Objektkennungen und bestehende Dateinamen bleiben kompatibel.

Die gemeinsame Startseite bleibt wie in 0.9 aufgebaut: Anwesenheit/Alarm zuerst, Haustür danach, auch mobil. Schloss und Türkontakt bleiben getrennte Zustände. Wohnzimmerlicht, Cinema 40, Gerätewarnungen, PV sowie Markise/Dachfenster folgen darunter. Bewegung, Temperaturen und Wetter bleiben eigene Detailbereiche. Die funktionierende Aktualisierung aus 0.9 bleibt erhalten.

## Bestehende Installation aktualisieren

1. ZIP entpacken. Im [Repository Symcon-Hausstatus](https://github.com/Powermaniaxx/Symcon-Hausstatus) **library.json**, den vollständigen Ordner **HausstatusBedienung**, **README.md** und **docs** durch diese Fassung ersetzen und committen. Auch die neuen Dateien **RoomSupport.php** und **room_defaults.json** im Modulordner hochladen. Keine ZIP-Datei und keine zusätzliche Ordnerstufe hochladen. Im Hauptverzeichnis steht Version **0.10**, Build **10**.
2. In Symcon **Module Control** das Repository aktualisieren. Danach Instanz **52627** öffnen und **Änderungen übernehmen**. Die Instanzkonfiguration schließen und erneut öffnen. Die Visualisierung neu laden, damit die neue HTML-Fassung geladen wird. Der Tooltip am Aktualisieren-Button zeigt **Hausstatus 0.10**.
3. **docs/Raeume_und_Punkte_0_10_KOMPLETT.php** vollständig in ein **neues temporäres PHP-Skript** kopieren und einmal manuell ausführen. Dieses komplette Skript richtet die Raumseiten und PV-Details ein und ordnet die beiden Statusquellen zu.
4. Visualisierung neu laden. Die Raumseiten stehen im neuen Bereich **„Räume und Geräte“**, PV in **„PV-Details“**. Quellen und Bedienoptionen bleiben zentral in **52627 → „11 · Räume und weitere Geräte“** einstellbar. Jede Rauminstanz verwendet 52627 als Konfigurationsquelle und ihren eigenen Raumfilter.

Das Einrichtungsskript verwendet zuerst die vorhandenen Raumverknüpfungen unter **52891** und liest ergänzend die älteren Raumverknüpfungen unter **10577**, sofern diese Kategorien existieren. Zusätzliche dort vorhandene Räume und Geräte werden ebenfalls übernommen. Vorhandene konfigurierte Raumquellen behalten Vorrang; die bekannten Vorgaben ergänzen nur fehlende Ziele. Eine erneute Ausführung verwendet dieselben eigenen Kacheln und Links. Die Ausgabe nennt die gelesenen Kategorien und übernommenen Quellen. Fehlende bekannte Quellen werden als solche angezeigt.

Bestehende native Raum- und Gerätebereiche bleiben erhalten. Das Skript verändert die Gerätequellen nicht und sendet keine Gerätebefehle. **Skript 49024 und die alte Visualisierung unter 30848 werden nicht bearbeitet oder ausgeführt.** Auch das vorhandene Lichtskript wird beim Modulupdate nicht ersetzt.

Das erstmalige **Einrichten_Kachel_KOMPLETT.php** ist für diese bestehende Installation nicht erforderlich. **Hausstatus_Detailkacheln_0_8_KOMPLETT.php** nur vor der neuen Einrichtung verwenden, falls Bewegung/Temperaturen/Wetter noch fehlen. Nach der Einrichtung von 0.10 würde das alte Detailskript die separate PV-Kachel wieder pausieren; deshalb anschließend das neue 0.10-Skript verwenden.

## Raumseiten und vorhandene Funktionen

Die sichtbaren Raumverknüpfungen ergänzen die folgenden bekannten Vorgaben aus der bisherigen Visualisierung:

| Bereich | Ergänzte Inhalte |
| --- | --- |
| Wohnzimmer | Hue-Gruppe und Einzellampen, Zusatzheizung mit Automatik und Temperaturgrenzen, Bewegung/Präsenz, Cinema 40 mit Quelle und Lautstärke, Surround-Modus und HEOS |
| Schlafzimmer | Licht, Präsenz, Wasserbett mit Heiztemperaturen, Leistung, Energie und Kosten für verschiedene Zeiträume |
| Flur | Temperatur, Bewegung und Helligkeit |
| Ankleidezimmer, Bad, Büro, Büro Keller, Sportraum, Keller | Bekannte Temperaturen sowie weitere vorhandene Raumverknüpfungen; Keller zusätzlich Bewegung |
| Küche | Boiler und weitere vorhandene Raumverknüpfungen |
| Terrasse | Bewegung, Dauerlicht und Steckdosenbetrieb |
| PV-Details | Leistung und Tagesenergie aus der bestehenden PV-Kachel, AC- und Panelleistung, Gesamtenergie, Wechselrichtertemperaturen sowie HM-800, HM-1500 und OpenDTU |

**RoomEntries** ist die gemeinsame Liste mit Raum, Gruppe, Beschriftung, Quelle und Bedienfreigabe. Eine Quelle kann eine Variable, Geräteinstanz, ein Skript oder Medium sein. Bei Geräteinstanzen werden sichtbare und aktive Variablen ergänzt; eigene HTML-Boxen werden nicht als Quelltext eingeblendet. Sind keine passenden Variablen vorhanden, bleibt die native Geräteansicht erreichbar. Nicht benötigte Zeilen können aus dieser Liste entfernt werden. **RoomControlsEnabled** sperrt oder aktiviert die Raumgerätebedienung insgesamt. Die Auswahl „Bedienen“ steuert jede einzelne Quelle.

Direkte HTML-Bedienung setzt eine vorhandene aktive Variablenaktion voraus. Schalter übergeben Boolean, Auswahlfelder ausschließlich die hinterlegten Werte mit ihrem ursprünglichen Typ. Regler übernehmen Grenzen, Schrittweite und Einheit aus der Variablendarstellung oder dem Legacy-Profil. Prozentwerte werden nur für die Anzeige umgerechnet; an die Aktion geht der unveränderte Rohwert. RGB verwendet Integer von 0 bis 16777215. Andere Farbcodierungen und spezielle Darstellungen werden in ihrer nativen Symcon-Oberfläche geöffnet.

Die Wohnzimmergruppe und ihre Helligkeit verwenden weiterhin das gewählte Licht-Bedienskript. Einzellampen und weitere Geräte nutzen ihre eigenen vorhandenen Aktionen; sie setzen nicht zusätzlich den manuellen Zustand der Wohnzimmerautomatik. Für deren manuellen Schutz die Gruppenbedienung verwenden. Die Cinema- und Markisen-/Dachfensterfreigaben gelten auch für die entsprechenden Raumvariablen. Türbefehle und die Türfreigabe werden in generischen Raumseiten gesperrt und bleiben im vorgesehenen Haustürbereich mit Sicherheitsfrage.

## Markise, Dachfenster und Haustür

| Quelle | ID |
| --- | ---: |
| Schlossstatus | 14438 |
| Türkontakt | 47467 |
| Türfreigabe | 33983 |
| Tür-Bedienvariable | 30053 |
| Letzte Öffnung | 19534 |
| Letzte Schließung | 55355 |
| Markise Position | 20434 |
| Markise Automatik | 44425 |
| Markise/Rollo Status | **35313** |
| Dachfenster Position | 37131 |
| Dachfenster Automatik | 30082 |
| Dachfenster über Nacht | 44013 |
| Dachfenster Status | **25259** |

Die Statuszeilen zeigen jeweils den formatierten Wert der angegebenen Variable und werden unabhängig von Position und Automatik gelesen. **OutdoorEnabled** aktiviert die Bedienung. Positionsregler benötigen Integer-/Float-Variablen mit Aktion und gültiger Reglerdarstellung; Automatikschalter benötigen Boolean mit Aktion. Bestehende Motorsteuerungen und Automatikskripte werden weiterverwendet.

**DoorEnabled** aktiviert Türfreigabe und Öffnen. Freigeben allein öffnet nicht. Die vorhandene Integer-Bedienvariable 30053 benötigt ihr benutzerdefiniertes Aktionsskript und im Legacy-Profil eine eindeutige Öffnen-Assoziation. Der Öffnen-Wert wird aus diesem Profil gelesen. Ein offener Türkontakt sperrt das Öffnen. „Tür öffnen“ zeigt eine Sicherheitsfrage; Abbrechen sendet keinen Befehl. Die Bestätigung gilt einmalig für die anfragende Anzeige und 20 Sekunden. Freigabe und Öffnungsplan werden vor der Ausführung erneut geprüft. Andere vorhandene native Türbedienelemente werden durch diese Frage nicht verändert.

## Cinema, Licht und Gerätewarnungen

CinemaVolume: **45376**, Master Volume, Float. CinemaSource: **16889**, Input Source, Integer. CinemaState: **45754**. CinemaControl ist die gewählte vorhandene Boolean-Power-Variable; **CinemaEnabled** aktiviert die Bedienung. In bestehenden Instanzen bleiben die gewählten IDs erhalten. Die Lautstärke verwendet die nativen Reglergrenzen und Schritte. Ein Befehl wird erst beim Loslassen gesendet. Die Quellenwahl zeigt die hinterlegten Optionen.

**LightEnabled** aktiviert Wohnzimmerlicht und Helligkeit. Das vorhandene Skript **33054** in Fassung 3.3 wird als **LightCommandScript** gewählt. Die beigefügte **Wohnzimmer_Lichtautomatik_3_3_KOMPLETT.php** ist ein vollständiger Ersatz, falls diese Fassung noch fehlt; vorhandene spätere Anpassungen zuvor abgleichen. Ein setzt den manuellen Modus und Weiß, Aus setzt die 30-Minuten-Sperre. In 0.10 wurden nur Kommentare und sichtbare Meldungen dieser Skriptdatei auf Umlaute umgestellt; die Automatiklogik wurde nicht geändert.

**BatteryWarnings** muss die Integer-Zählvariable „Anzahl Gerätewarnungen“ aus dem Batteriewächter sein, nicht das Skript. Ohne Quelle erscheint „Nicht eingerichtet“. Die PV-Übersicht verwendet weiterhin 55194 und 50290.

## Bewegung, Temperaturen, Wetter und Aktualisieren

Bewegung verwendet die konfigurierte **MotionSensors**-Liste. Bekannte Quellen: Flur 26325, Wohnzimmer Bewegung 58943, Wohnzimmer Präsenz 37345, Terrasse 34118, Schlafzimmer Präsenz 22412 und Keller 16931. **MotionArchive** 0 wählt bei genau einem Archive Control automatisch dieses Archiv. **MotionLogging** aktiviert die Archivierung der gewählten Boolean-Variablen. Angezeigt werden Zustand, Bewegungsphasen und Zustandswechsel der letzten 24 Stunden in Europe/Berlin. Nicht aufgezeichnete Vergangenheit wird nicht nachträglich erzeugt. Ein erreichtes Abfragelimit wird angezeigt.

Temperaturen verwenden weiterhin **Rooms**. Wetter zeigt Wetterzustand, Wind, Regen, Warnstufe sowie Sonnenaufgang und Sonnenuntergang. Diese drei Detailbereiche bleiben unabhängig von den neuen Raumgeräteansichten.

**Aktualisieren** in jeder HTML-Kachel wartet auf die zugehörige Rückmeldung, auch bei unveränderten Werten. Nach Erfolg erscheint eine Uhrzeit. Fehler und eine fehlende Antwort nach acht Sekunden werden daneben angezeigt. Der Verlauf wird frisch aus dem Archiv gelesen. Der Button liest die aktuellen Symcon-Variablenwerte; Moduldateien werden über Module Control aktualisiert. Quellenmeldungen und der 30-Sekunden-Timer aktualisieren die Anzeige zusätzlich.

„Befehl übergeben“ verschwindet nach drei Sekunden. Die Meldung bestätigt die Übergabe an die vorhandene Aktion. Fehler bleiben sichtbar. Für lokale Ansicht und Aktivierung gelten weiterhin **View**, **ConfigSource** und **ActiveView**. Die neue Raumansicht hat **View 12**; **RoomFilter** bestimmt nur lokal den Raum. Alte Ansichtsnummern und gespeicherte Eigenschaftsnamen bleiben erhalten.

## Prüfung

Die JavaScript-Logik wurde mit einem simulierten DOM geprüft: Aktualisierung in allen zwölf Ansichten, passende Rückmeldungen, unveränderte und geänderte Werte, Fehler, Timeout, erneuter Versuch, Türabbruch und Reihenfolge der Übersicht. Hinzu kommen die neuen Raumsteuerungen mit unveränderten Boolean-, Auswahl-, Float- und RGB-Werten, native Bedienung sowie die unabhängigen Statuszeilen für Markise und Dachfenster. JSON, neue Quelldateien, UTF-8 und der Erhalt aller 43 bisherigen Konfigurationsfelder wurden geprüft.

Eine PHP-/Symcon-Laufzeit, echte Browserdarstellung und die physischen Geräte sind hier nicht verfügbar. Die tatsächlich vorhandenen zusätzlichen Raumquellen werden erst beim manuellen Einrichtungslauf in Symcon eingelesen und in dessen Ausgabe aufgeführt.

Offizielle Dokumentation: [HTML SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/), [Variablendarstellung](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/), [Schieberegler](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/slider/), [Aufzählung](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/aufzaehlung/).
