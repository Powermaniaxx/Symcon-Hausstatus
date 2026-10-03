# Sven Hausstatus 0.11

IP-Symcon-Modul ab Version 9.0. Dieses Update erweitert die bestehende Fassung 0.10. Zentrale Instanz: **52627**. Kachelbereich: **55503**. Hausstatus-Link: **29867**.

## Neu in 0.11

Die vorhandene Startseite behält ihre Reihenfolge. Anwesenheit/Alarm und Haustür sind kompakter. **Esstisch** steht innerhalb des vorhandenen Wohnzimmerlichtblocks; **Herz** bleibt eine eigene Lampe. PV zeigt auf der Startseite nur die aktuelle Leistung. Unter Wetter kommen der aktuelle Regenstatus und Regenphasen der letzten 24 Stunden hinzu.

Raumseiten bündeln die Werte eines Geräts in einem gemeinsamen Block. Schalten, Helligkeit, Lautstärke und häufig benötigte Werte bleiben direkt sichtbar. Farbe, Übergang, zusätzliche Einstellungen und Verbrauchsdetails stehen unter **Details**. Auch dort bleiben die vorhandenen Aktionen bedienbar; aufgeklappte Details bleiben bei Hintergrundaktualisierungen geöffnet. Fehlende Quellen werden angezeigt und bei zugeklappten Details im Hinweiszähler berücksichtigt.

Temperaturen erscheinen in zusammengehörigen Zeilen: **Sollwerte links einstellen, Istwerte rechts ablesen**, auch mobil. Sollwerte nutzen die vorhandenen Grenzen, Schritte und Einheiten. In der zentralen Temperaturkonfiguration lässt sich pro Raum eine Sollvariable auswählen. Ohne Auswahl werden passende Solltemperaturen der konfigurierten Raumgeräte oder desselben Thermostats verwendet. Fehlt eine passende Sollquelle, zeigt die Seite weiterhin den Istwert und „Keine Sollquelle“.

Die Gestaltung orientiert sich an [Apples Empfehlungen zur Informationshierarchie und schrittweise sichtbaren Details](https://developer.apple.com/videos/play/wwdc2025/359/), [Home Assistants gruppierten Sections](https://www.home-assistant.io/dashboards/sections/) und [Googles Empfehlungen für ausreichend große Bedienflächen](https://developer.android.com/develop/ui/compose/accessibility/api-defaults). Die bestehende Symcon-Übersicht bleibt dafür die Grundlage.

## Bestehende Installation aktualisieren

1. In Symcon **Module Control** das Repository [Symcon-Hausstatus](https://github.com/Powermaniaxx/Symcon-Hausstatus) aktualisieren. Im Hauptverzeichnis muss **library.json** Version **0.11**, Build **11** anzeigen. Der vollständige Modulordner enthält auch **RoomSupport.php**, **ComfortSupport.php**, **RainSupport.php** und **room_defaults.json**.
2. Instanz **52627** öffnen und **Änderungen übernehmen**. Die Instanzkonfiguration schließen und erneut öffnen. Die Visualisierung neu laden, damit die neue HTML-Fassung geladen wird. Der Tooltip am Aktualisieren-Button zeigt **Hausstatus 0.11**.
3. Falls die Raumseiten und **PV-Details** aus 0.10 bereits eingerichtet sind, ist für die neue Darstellung kein Einrichtungsskript erforderlich. Esstisch und Regen verwenden die unten genannten Vorgaben automatisch. Die Sollquellen bei Bedarf unter **09 · Temperaturen** auswählen.
4. Zum erstmaligen Einrichten, erneuten Einlesen vorhandener Raumverknüpfungen oder Umbenennen des eigenen Bereichs in **Raumsteuerung**: **docs/Wohnansicht_0_11_KOMPLETT.php** vollständig in ein **neues temporäres PHP-Skript** kopieren und einmal manuell ausführen. Danach die Visualisierung neu laden. Die alte Datei 49024 wird dafür nicht verwendet.

Das komplette Einrichtungsskript verwendet zuerst vorhandene Raumverknüpfungen unter **52891** und liest ergänzend **10577**, sofern vorhanden. Weitere dort enthaltene Räume und Geräte werden übernommen. Bereits konfigurierte Raumquellen behalten Vorrang; Vorgaben ergänzen nur fehlende Ziele. Eine erneute Ausführung verwendet dieselben eigenen Kacheln und Links. Die Ausgabe nennt die gelesenen Kategorien und übernommenen Quellen.

Bestehende native Raum- und Gerätebereiche bleiben erhalten. **49024 und die alte Visualisierung unter 30848 werden nicht bearbeitet oder ausgeführt.** Das vorhandene Lichtskript wird beim Modulupdate nicht ersetzt. Alte Einrichtungsskripte sind für die bisherigen Fassungen beigefügt; für dieses Update die vollständige Fassung **Wohnansicht_0_11_KOMPLETT.php** verwenden.

## Esstisch und Regen

| Quelle | ID |
| --- | ---: |
| Esstisch · Hue-Gruppe | **54491** |
| Esstisch · Status | **58764** |
| Esstisch · Helligkeit | **57302** |
| Herz · eigene Lampeninstanz | **56035** |
| Regenstatus · RAINING | **54692** |
| Bisherige Regenmenge | **54690** |

Esstisch lässt sich unabhängig von der Wohnzimmergruppe ein-/ausschalten und dimmen. **DiningEnabled** schaltet die Bedienung frei; Quellen stehen unter **03 · Wohnzimmerlicht**. Die vorhandenen Hue-Aktionen werden verwendet. Esstisch setzt nicht den manuellen Zustand der Wohnzimmerautomatik. Die Einstellungen **DiningInstance**, **DiningState** und **DiningBrightness** bleiben getrennt von deren Quellen.

**Raining** benötigt eine Boolean-Variable. **RainArchive** 0 verwendet bei genau einem Archive Control dieses Archiv; bei mehreren ein Archiv auswählen. **RainLogging** aktiviert die Aufzeichnung der gewählten Regenvariable. Startseite und Wetterdetail zeigen aktuellen Regenstatus, Zeitband und aufklappbare Regenzeiten der letzten **24 Stunden** in **Europe/Berlin**. Eine Phase vor dem Zeitfenster wird als solche gekennzeichnet; eine noch laufende Archivphase reicht bis zum Abfrageende. Wiederholte gleiche Meldungen erzeugen keine zusätzlichen Regenphasen. Fehlende Daten und ein erreichtes Abfragelimit werden angezeigt. Nicht aufgezeichnete Vergangenheit wird nicht nachträglich erzeugt. Nach erstmaliger Aktivierung steht deshalb noch kein vollständiger 24-Stunden-Verlauf zur Verfügung.

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

Temperaturen verwenden weiterhin **Rooms**, ergänzt um das optionale Feld **Setpoint**. Die bisherigen **Name**- und **Variable**-Felder bleiben erhalten. Temperatursollwerte sind ausschließlich in der zugehörigen Raumseite und der Temperaturansicht bedienbar. Wetter zeigt Wetterzustand, Wind, Regenmenge, Warnstufe, Sonnenaufgang und Sonnenuntergang sowie den Regenverlauf. Bewegung bleibt ein eigener Detailbereich.

**Aktualisieren** in jeder HTML-Kachel wartet auf die zugehörige Rückmeldung, auch bei unveränderten Werten. Nach Erfolg erscheint eine Uhrzeit. Fehler und eine fehlende Antwort nach acht Sekunden werden daneben angezeigt. Der Verlauf wird frisch aus dem Archiv gelesen. Der Button liest die aktuellen Symcon-Variablenwerte; Moduldateien werden über Module Control aktualisiert. Quellenmeldungen und der 30-Sekunden-Timer aktualisieren die Anzeige zusätzlich.

„Befehl übergeben“ verschwindet nach drei Sekunden. Die Meldung bestätigt die Übergabe an die vorhandene Aktion. Fehler bleiben sichtbar. Für lokale Ansicht und Aktivierung gelten weiterhin **View**, **ConfigSource** und **ActiveView**. Die neue Raumansicht hat **View 12**; **RoomFilter** bestimmt nur lokal den Raum. Alte Ansichtsnummern und gespeicherte Eigenschaftsnamen bleiben erhalten.

## Prüfung

Die JavaScript-Logik wird mit einem simulierten DOM geprüft: Aktualisierung in allen zwölf Ansichten, passende Rückmeldungen, unveränderte und geänderte Werte, Fehler, Timeout, erneuter Versuch, Türabbruch und Reihenfolge der Übersicht. Dazu kommen typgerechte Raum- und Esstischbefehle, Prozentrohwerte, Lautstärke, Farbe, native Bedienung, Gerätebündelung, getrenntes Herz/Esstisch, geöffnete Details, Soll-/Ist-Zuordnung, fehlende Sollquellen und Regenphasen mit unbekannter Vorgeschichte, Zeitfenstergrenzen, gleichen Wiederholungen und fehlenden Archiven. Die PHP-Syntax wurde mit einem PHP-Parser geprüft. JSON, UTF-8, Quellenzuordnung, erforderliche Dateien und der Erhalt aller 46 bisherigen Konfigurationsfelder wurden ebenfalls geprüft.

Eine Symcon-Laufzeit, echte Browserdarstellung und die physischen Geräte sind hier nicht verfügbar. Zusätzliche Raumquellen werden beim manuellen Einrichtungslauf in Symcon eingelesen. Die tatsächliche Darstellung auf dem Handy und Geräteaktionen sind dort zu prüfen.

Offizielle Dokumentation: [HTML SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/), [Variablendarstellung](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/), [Schieberegler](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/slider/), [Aufzählung](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/aufzaehlung/).

Regenarchiv: [AC_GetLoggedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggedvalues/) und [AC_SetLoggingStatus](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-setloggingstatus/).
