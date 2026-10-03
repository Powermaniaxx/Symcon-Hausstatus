# Sven Hausstatus 0.12

IP-Symcon-Modul ab Version 9.0. Dieses Update erweitert die bestehende Fassung 0.11. Zentrale Instanz: **52627**. Kachelbereich: **55503**. Hausstatus-Link: **29867**.

## Neu in 0.12

Die Startseite zeigt die PV-Anlage als kleine Kachel mit der **aktuellen Leistung** und **Details öffnen**. Die große zusätzliche PV-Detailkachel wird beim Übernehmen der zentralen Einstellungen ausgeblendet. Der Button öffnet dieselbe weiterhin aktive Detailansicht mit Erträgen, Wechselrichtern, OpenDTU, Messwerten und Bedienung. Alle bisherigen Quellen bleiben erhalten. **Der Wetterblock einschließlich Regenverlauf bleibt unverändert.**

Raumseiten sind für die tägliche Bedienung gegliedert: **Licht und Raumklima zuerst**, anschließend Medien und weitere Geräte. Eine kurze Statuszeile zeigt verfügbare Temperatur, Bewegung und Luftfeuchte. Lampen erscheinen als kompakte Karten mit eindeutigen Namen, Schalter und Dimmer. Aktive Lampen werden zusätzlich durch Zustand und Farbe gekennzeichnet. Pro Gerät bleiben höchstens vier häufige Werte oder Bedienelemente sichtbar; technische Zusatzwerte stehen unter **Weitere Werte** beziehungsweise **Weitere Einstellungen**. Geräte ohne Alltagsfunktionen werden in einem gemeinsamen aufklappbaren Bereich zusammengefasst.

**Heizung:** Sollwerte und Isttemperatur gehören in einen gemeinsamen Block. Die linke Spalte enthält die vorhandenen Sollregler mit Minus/Plus, rechts steht der gemessene Wert. Grenzen und Schrittweite kommen vom echten Thermostat. Mobil bleiben Soll und Ist nebeneinander; die Funktionsbereiche folgen untereinander. Ohne Sollquelle bleibt die Isttemperatur sichtbar.

**Medien:** Ein/Aus, Quelle und Lautstärke stehen gemeinsam beim Gerät. Sensoren bleiben von der Bedienung getrennt; interne Statuscodes und weitere Helligkeits-/Batteriewerte sind in den Details erreichbar. Aufgeklappte Details bleiben bei Hintergrundaktualisierungen geöffnet. Fehlende Quellen werden als Hinweise angezeigt. Esstisch und Herz bleiben getrennte Lampen.

Die Anordnung setzt [Home Assistants Empfehlung für zusammengehörige Bereiche auf einem regelmäßigen Raster](https://www.home-assistant.io/dashboards/sections/) und [Apples Empfehlung für wesentliche Inhalte zuerst und zusätzliche Optionen bei Bedarf](https://developer.apple.com/videos/play/wwdc2025/359/) um. Bedienflächen erhalten ausreichend Platz entsprechend [Googles Empfehlungen für Touch-Bedienung](https://developer.android.com/develop/ui/compose/accessibility/api-defaults). Quellen und Variablenaktionen werden dafür weiterverwendet.

## Bestehende Installation aktualisieren

1. In Symcon **Module Control** das Repository [Symcon-Hausstatus](https://github.com/Powermaniaxx/Symcon-Hausstatus) aktualisieren. **library.json** zeigt Version **0.12**, Build **12**. Der vollständige Modulordner enthält auch **NavigationSupport.php**.
2. Instanz **52627** öffnen und **Änderungen übernehmen**. Das blendet ausschließlich die korrekt zugeordnete eigene PV-Detailverknüpfung **SVHSTileLink_7** auf der Startseite aus. Die Instanz **SVHSTile_7** bleibt aktiv und ist über **Details öffnen** erreichbar. Ein anders belegter Link wird nicht verändert.
3. Konfiguration schließen und die Visualisierung neu laden. Der Tooltip am Aktualisieren-Button zeigt **Hausstatus 0.12**. Bereits vorhandene eigene Raumseiten bekommen die neue Darstellung automatisch. Der eigene Bereich heißt jetzt **Räume**, sofern er bisher einen der ursprünglichen Namen „Raumsteuerung“ oder „Räume und Geräte“ trug. **Räume öffnen** führt direkt zu diesen HTML-Raumseiten.
4. Fehlen die eigenen Raumseiten oder die PV-Detailinstanz, **docs/Wohnansicht_0_12_KOMPLETT.php** vollständig in ein **neues temporäres PHP-Skript** kopieren und einmal manuell ausführen. Anschließend die zentrale Instanz übernehmen und die Visualisierung neu laden. Das vollständige Skript erhält bestehende Quellen, liest weitere Raumverknüpfungen ein und verwendet dieselben eigenen Kacheln und Links erneut.

Die bestehenden nativen Raumseiten aus „Räume und Punkte“ bleiben erhalten; die neu gestalteten HTML-Seiten stehen im eigenen Bereich **Räume**. Quellen und Bedienfreigaben werden zentral in **52627** eingestellt. Bei fehlenden Thermostatsollwerten unter **09 · Temperaturen** pro Raum die vorhandene Sollvariable auswählen.

Das Einrichtungsskript liest Raumverknüpfungen unter **52891** und ergänzend **10577**, sofern vorhanden. Bereits konfigurierte Quellen behalten Vorrang; bekannte Vorgaben ergänzen nur fehlende Ziele. Die Ausgabe nennt die gelesenen Kategorien und übernommenen Quellen. **49024, die alte Visualisierung unter 30848, Gerätequellen und das vorhandene Lichtskript werden nicht bearbeitet oder ausgeführt.** Die alten Einrichtungsskripte sind für frühere Fassungen beigefügt; für dieses Update **Wohnansicht_0_12_KOMPLETT.php** verwenden. Das alte 0.11-Skript würde die große PV-Detailverknüpfung erneut auf der Startseite einblenden.

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

Die JavaScript-Logik wird mit einem simulierten DOM geprüft: Aktualisierung in allen zwölf Ansichten, passende Rückmeldungen, unveränderte und geänderte Werte, Fehler, Timeout, erneuter Versuch, Türabbruch und Reihenfolge der Übersicht. Dazu kommen die neue direkte Navigation zu Räumen und PV-Details, einzelne Lampenschalter, gruppierte Zusatzwerte, Plus/Minus mit echten Thermostatgrenzen sowie typgerechte Raum- und Esstischbefehle, Prozentrohwerte, Lautstärke, Farbe, native Bedienung, Gerätebündelung, getrenntes Herz/Esstisch, geöffnete Details, Soll-/Ist-Zuordnung, fehlende Sollquellen und Regenphasen mit unbekannter Vorgeschichte, Zeitfenstergrenzen, gleichen Wiederholungen und fehlenden Archiven. Die PHP-Syntax wurde mit einem PHP-Parser geprüft. JSON, UTF-8, Quellenzuordnung, erforderliche Dateien und der Erhalt aller 46 bisherigen Konfigurationsfelder wurden ebenfalls geprüft.

Eine Symcon-Laufzeit, echte Browserdarstellung und die physischen Geräte sind hier nicht verfügbar. Zusätzliche Raumquellen werden beim manuellen Einrichtungslauf in Symcon eingelesen. Die tatsächliche Darstellung auf dem Handy und Geräteaktionen sind dort zu prüfen.

Offizielle Dokumentation: [HTML SDK](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/html-sdk/), [Variablendarstellung](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/variablenverwaltung/ips-getvariablepresentation/), [Schieberegler](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/presentations/slider/), [Aufzählung](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/darstellungen/aufzaehlung/).

Regenarchiv: [AC_GetLoggedValues](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getloggedvalues/) und [AC_SetLoggingStatus](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-setloggingstatus/).
