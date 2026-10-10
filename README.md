# Hausstatus 0.39 für IP-Symcon

Ein konfigurierbares Dashboard für die Symcon-Kachelvisualisierung: Anwesenheit und Alarm, Haustür, Licht, Medien, PV, Beschattung, Bewegung, Temperaturen, Wetter und Netzwerk. Das Modul verwendet vorhandene Symcon-Variablen, deren Darstellungen und Bedienaktionen. Jede Installation wählt ihre eigenen Quellen und Beschriftungen.

## Installation

1. Dieses Repository über **Module Control** in Symcon hinzufügen: `https://github.com/Powermaniaxx/Symcon-Hausstatus`.
2. Eine eigene Kategorie für das Dashboard und darin eine Instanz **Hausstatus mit Bedienung** anlegen.
3. In **Kachel und Konfiguration** als Inhalt **Übersicht** wählen. **Gemeinsame Einstellungen** leer lassen: Diese Instanz verwaltet die Quellen für das Dashboard.
4. Die benötigten Variablen in den einzelnen Einstellungsbereichen auswählen. Neue Instanzen haben keine vorausgewählten Geräte- oder Variablen-IDs. Bewegung, Temperaturen, WLAN und Raumgeräte beginnen mit leeren Listen.
5. Unter **Texte und Beschreibungen** Bezeichnungen und optionale Hinweise anpassen. Die anfänglichen Namen sind Beispiele und lassen sich ändern, etwa „Wohnzimmerlicht“, „Esstisch“ oder „Cinema 40“. Bei Boolean-Anzeigen können zusätzlich eigene Texte für An und Aus hinterlegt werden. Leere Zustandstexte behalten die bisherige Darstellung.
6. **Änderungen übernehmen** und die Kategorie in der Kachelvisualisierung öffnen. Im Darstellungseditor **Instanzspezifische Darstellung** wählen, falls dort eine Liste ausgewählt ist.

Voraussetzung ist Symcon ab **9.0**. Unterseiten verwenden HTML-Vollbild nur, wenn Symcon mindestens **9.1** und die passende SDK-Konstante bereitstellt. Sonst bleibt der normale HTML-Kacheltyp aktiv. Unter Symcon 9.0 öffnet **Details öffnen** bei PV die Inhalte direkt innerhalb dieser Kachel. Dadurch benötigt die PV-Seite keine native Vollbildansicht. Räume werden über den vorhandenen Menüpunkt **Räume** geöffnet; die Startseite enthält keinen zusätzlichen Raumzugang. Die Startseite verwendet weiterhin den normalen Kacheltyp.

## Einstellungen und geeignete Quellen

| Bereich | Quellen und Bedienung |
| --- | --- |
| Anwesenheit und Alarm | Boolean-Statusvariablen. Eigene An-/Aus-Texte sind möglich. |
| Individuelle Kacheln (0.38) | Direkt in „Hausstatus mit Bedienung“ einen der 19 Kacheltypen wählen. Unter „2 · Zusätzliche Variablen für diese Kachel“ eigene vorhandene Variablen mit optionalen Anzeigenamen hinzufügen (max. 32 pro Kachel). Diese erscheinen als reine lesende Statusfelder. Der neue Typ „Eigene Kachel“ zeigt ausschließlich die ausgewählten eigenen Variablen. Quellen der Master-Instanz sind optional weiterhin über „Gemeinsame Einstellungen“ nutzbar. |
| Anwesenheitsverlauf (neue Detailkachel) | Hausstatus und Alarm innen werden zusammen mit zwei bestehenden persönlichen Boolean-Variablen, beispielsweise Sven 49185 und Susi 48195, als reine Statusanzeige mit 24-Stunden-Archivverlauf. Keine Schaltaktion, keine neuen Variablen, keine automatische Änderung der Archivierung. Neue Hausstatus-Instanz mit Inhalt „Detail: Sven & Susi“ erstellen, als gemeinsame Konfiguration die zentrale Instanz wählen und in dieser die beiden Originalvariablen sowie bei Bedarf das Archiv festlegen. Ab Version 0.36 können unter „Anwesenheitsverlauf Sven & Susi“ zusätzlich zwei eigene Fotos aus der Symcon-Mediathek ausgewählt werden (JPEG, PNG, WebP oder GIF, maximal 1 MB je Bild). Ohne hochgeladene Profilbilder erscheinen zwei unterschiedliche, fest eingebaute gezeichnete Avatare für Sven und Susi. Eigene Profilbilder erscheinen ebenfalls rund mit grünem Rand bei Anwesenheit und grauem Rand bei Abwesenheit. Hausstatus und Alarm sind stattdessen kompakte reine Statusfelder ohne Avatare und zeigen ihren eigenen Archivverlauf; bis zu acht weitere Alarmvariablen können über eine Liste ergänzt werden. Anschließend die Kachel in „Variablen“ einfügen und die bisherigen schaltbaren Variablen-Kacheln dort ausblenden. |
| Haustür | Schlossstatus, eigener Türkontakt, letzte Öffnung und Schließung, Boolean-Freigabe und vorhandene Öffnen-Aktion. |
| Hauptlicht | Boolean-Status und Integer-Helligkeit von 1 bis 100. Bedienung über vorhandene Variablenaktionen oder ein optionales Bedienskript. |
| Zusätzliche Lampe | Eigene Status- und Helligkeitsvariable; optional eine Lichtinstanz zur Ermittlung ihrer Untervariablen. Raumname und Bezeichnung frei wählen. |
| Medien | Status, Power-Aktion, Quellenwahl und Lautstärke. Die HEOS-Kachel zeigt getrennte Listen für Radio und NAS-Playlisten. Eigene schaltbare Auswahlvariablen unter **Mediengerät** auswählen. Sender und Playlisten stammen dynamisch aus deren Variablendarstellungen; neue Einträge erfordern keine Moduländerung. Die bisherige HEOS-Gesamtauswahl bleibt als Rückfall erhalten (0=Aus, 1–3=Radio, ab 4=NAS). Optionen, Einheit, Grenzen und Schritte kommen von den tatsächlichen Variablendarstellungen. |
| Gerätewarnungen | Integer-Variable mit der Anzahl der Warnungen. Eine Zählvariable wählen, kein PHP-Skript. |
| PV | Aktuelle Leistung auf der Startseite; Tagesenergie und zusätzliche Geräte auf der Detailseite. Der PV-Bereichsname muss mit den Einträgen in der Raumgeräteliste übereinstimmen. |
| Markise und Dachfenster | Position, eigener Zustand und Automatik; für Dachfenster optional Nachtbetrieb. Regler übernehmen die vorhandenen Grenzen und Schritte. |
| Bewegung | Liste aus eigener Bezeichnung und Boolean-Bewegungsvariable; optional Archive Control für den Verlauf. |
| Temperaturen | Liste aus Raumname, Isttemperatur und optionaler Sollvariable. Zusätzlich eine frei wählbare Heizungsprofilvariable. |
| Wetter | Zustand, Wind, Regenmenge, Warnstufe, Sonnenzeiten und Boolean-Regenstatus. |
| Netzwerk / FritzBox | Verbindung, aktuelle Datenraten und Auslastung, Geräteanzahl, Laufzeit, Modell und Software-Version; zusätzliche WLAN-Liste. |

**Texte und Beschreibungen** legt die angezeigten Namen fest; die Variablen werden im jeweils zugehörigen Bereich ausgewählt. Die Texte ändern keine Gerätewerte. Eigene Beschreibungen erscheinen unmittelbar in der zugehörigen Kachel. Raum- und Gerätenamen werden direkt in den Listen gepflegt. Umlaute werden als UTF-8 gespeichert.

## Räume und Unterseiten

Unter **Räume und weitere Geräte** pro Eintrag den **Raum / Bereich**, die **Gruppe**, eine **Beschriftung** und eine **Quelle** auswählen. Die Quelle kann eine Variable oder Geräteinstanz sein. Bei Geräteinstanzen liest das Modul die sichtbaren Untervariablen ein. **Bedienen** bestimmt, ob vorhandene Aktionen verfügbar sind. Gruppennamen wie **Licht**, **Heizung**, **Medien** und **Sensoren** ordnen den Inhalt für die tägliche Bedienung.

Raumseiten zeigen zuerst Licht und Raumklima, anschließend Medien und Sensoren. Häufig verwendete Schalter und Regler bleiben sichtbar; technische Zusatzwerte stehen in aufklappbaren Details. Soll- und Isttemperatur stehen nebeneinander, auch mobil. Ohne Sollquelle bleibt der Istwert sichtbar. Türbefehle bleiben dem Haustürbereich mit Freigabe und Sicherheitsfrage vorbehalten.

Unterseiten lassen sich als weitere Instanzen desselben Moduls anlegen. In **Inhalt dieser Kachel** den Bereich auswählen und als **Gemeinsame Einstellungen** die eigene zentrale Instanz wählen. Für eine einzelne Raumseite den Inhalt **Räume und weitere Geräte** und den passenden **Raumname** einstellen. Die Namen müssen mit den Raumlisten übereinstimmen.

Unter Symcon 9.0 benötigt die eingebettete PV-Detailansicht keine zusätzliche Instanz. Raumseiten liegen im Menü. Die bestehende Kategorie und ihre Raumlinks werden durch das Entfernen des Raumzugangs aus der Startseite nicht verändert.

Die Netzwerkseite ist ein eigener Inhalt. Sie ergänzt die Startseite nicht um weitere Karten. Die PV-Startkarte bleibt auf aktuelle Leistung und Detailzugang beschränkt. Der kompakte Wetterblock enthält Wetter und Wind sowie den Regenverlauf. Anwesenheit/Alarm und Haustür bleiben an erster und zweiter Stelle.

## Heizungsprofil

Unter **Temperaturen** die vorhandene **Heizungsprofilvariable** auswählen. Die Temperaturansicht zeigt den aktuellen Wert. Eine vorhandene Auswahlaktion wird mit ihren tatsächlichen Optionen bedienbar; ohne Aktion bleibt die Anzeige lesbar. Andere Darstellungen sind über die native Symcon-Bedienung erreichbar. Die Variable wird nicht angelegt und nicht durch eine Modulvariable ersetzt.

Solltemperaturen werden pro Raum zugeordnet. Ohne explizite Sollquelle sucht das Modul passende Solltemperaturen aus den gewählten Raumgeräten oder demselben Thermostat. Bedienelemente verwenden dessen Grenzen und Schrittweite.

## FritzBox und WLAN-QR-Codes

Die FritzBox-Anbindung selbst erfolgt über die bereits vorhandenen Symcon-FritzBox-Module. Hausstatus liest deren Variablen und verbindet sich nicht separat mit dem Router.

Unter **Netzwerk / FritzBox** die gewünschten Quellen auswählen. **Download / Upload** verwenden die aktuellen Raten; die maximale Anschlussrate ist eine andere Quelle. Einheiten stammen aus der gewählten Variable. In **WLAN und vorhandene QR-Code-Bilder** pro Netz Bezeichnung, Status, SSID, aktive Geräte und ein vorhandenes **Bild-Medienobjekt** auswählen. Die Bezeichnungen können beispielsweise „WLAN 2,4 GHz“, „WLAN 5 GHz“ oder „Gastnetz“ sein.

Die Detailseite zeigt die wichtigsten Netzwerkinformationen und kompakte WLAN-Karten. **WLAN-QR-Code anzeigen** klappt das vorhandene PNG- oder JPEG-Bild auf. Es wird aus Symcon gelesen und innerhalb der Detailseite eingebettet; es gibt keinen externen QR-Code-Dienst. Wer Zugriff auf diese Seite hat, kann den WLAN-Code sehen. Passwörter werden nicht als separate Konfigurationsfelder benötigt. Ein fehlendes oder ungeeignetes Bild wird als Hinweis angezeigt.

## Lichtautomatik und Haustür

Für das Hauptlicht kann ein vorhandenes Bedienskript ausgewählt werden. Es erhält über `IPS_RunScriptEx` die Parameter `COMMAND` (`Light` oder `Brightness`), `VALUE` (Boolean beziehungsweise Integer-Prozentwert) und `SOURCE`. Ist ein Skript ausgewählt, wird ausschließlich dieses verwendet. So bleibt der manuelle Schutz einer vorhandenen Lichtautomatik erhalten. Ohne Skript verwendet das Modul die eigenen Variablenaktionen; Status ist Boolean, Helligkeit Integer von 1 bis 100. Andere Lampen und native Dimmerformate können in den Raumgeräten ergänzt werden.

Die Türöffnung benötigt eine ausdrücklich gewählte Boolean-Freigabe und eine Integer-Bedienvariable mit **vorhandenem benutzerdefiniertem Aktionsskript**. Im Variablenprofil muss genau eine Aktion **Öffnen**, **Tür öffnen** oder **Open** vorhanden sein. Die Sicherheitsfrage muss bestätigt werden; die Bestätigung ist kurzzeitig gültig und nur einmal nutzbar. Freigeben öffnet die Tür noch nicht. Das Modul schreibt nicht direkt in den Schlossaktor.

Der derzeitige Schlossstatus erwartet `1 = verriegelt`, `2 = aufgeschlossen`; der Türkontakt erwartet `true` oder `1 = offen` und `false` oder `0 = geschlossen`. Andere Gerätewerte müssen über passende bestehende Statusvariablen bereitgestellt werden. Bedienfreigaben werden separat aktiviert.

## Verläufe und Aktualisieren

Bewegungs- und Regenverläufe zeigen archivierte Zustandswechsel der letzten **24 Stunden** in **Europe/Berlin**. Bei genau einem Archive Control kann die Archivauswahl leer bleiben; bei mehreren ein Archiv auswählen. Die Archivierungsoption aktiviert die Aufzeichnung der gewählten Boolean-Variablen. Noch nicht aufgezeichnete Vergangenheit wird nicht nachträglich erzeugt.

Die Werte aktualisieren sich bei Variablenmeldungen und spätestens durch den regelmäßigen Modulabruf. **Aktualisieren** fragt Werte und Verläufe erneut ab und bestätigt auch unveränderte Werte. Ein Modulupdate wird dagegen in **Module Control** geladen. Die Texte „Befehl übergeben“ und erfolgreiche Aktionsmeldungen verschwinden nach kurzer Zeit.

## Bestehende Installation aktualisieren

Repository in **Module Control** aktualisieren, anschließend in der **eigenen zentralen Hausstatus-Instanz Änderungen übernehmen** und die Visualisierung neu laden. Dadurch werden auch die zugeordneten aktiven Unterseiten übernommen. `library.json` und der Aktualisieren-Tooltip zeigen **0.32**, Build **33**.

Gespeicherte Quellen, Listen, Freigaben und ausgewählte Bedienskripte werden nicht mit den neuen leeren Vorgaben überschrieben. Die leeren Vorgaben gelten für neu angelegte Instanzen. Neue Textfelder übernehmen die bisherigen Beschriftungen als Ausgangspunkt. Bestehende Raum- und Gerätelisten bleiben erhalten; neue Bereiche wie Heizungsprofil und Netzwerk werden ausdrücklich zugeordnet.

**0.32 beseitigt doppelte kombinierte Startseiten-Kacheln dauerhaft.** Auch wenn Symcon nach einem früheren Wiederherstellen Instanz und Präsentationslink gleichzeitig sichtbar gemacht hat, wird bei jedem Übernehmen erneut genau eine Darstellung sichtbar gehalten.

**0.31 korrigiert die kombinierte Startseite.** Wenn Instanz und Präsentationslink auf dieselbe Hausstatus-Instanz zeigen, bleibt nur eine sichtbare Kachel. In der kombinierten Ansicht steht Wetter jetzt direkt unter Markise und Dachfenster; Bewegung und Temperaturen folgen danach.

**0.30 macht alle Schieberegler auf Touch-Geräten scrollsicher.** Vertikales Wischen über einem Regler scrollt die Seite weiter und verändert keinen Wert. Erst eine klare horizontale Bewegung aktiviert den Regler; übertragen wird der Wert beim Loslassen. Bereits vorhandene getrennte Startseiten-Kacheln werden beim Übernehmen außerdem neu positioniert, damit Wetter direkt unter Markise und Dachfenster einsortiert wird.

**0.16 entfernt den Raumzugang aus der Startseite.** Der vorhandene Menüpunkt **Räume** und seine Links bleiben erhalten. PV-Details öffnen unter Symcon 9.0 weiterhin innerhalb der Hausstatus-Kachel; **← Startseite** führt zurück. Die sichtbare Unterseite wird regelmäßig neu gelesen; **Aktualisieren** fragt sie sofort ab. Raumregler behalten ihre vorhandenen Variablenaktionen und Bedienskripte.

Seitenanfragen werden als JSON-Text über die Visualisierungsverbindung übertragen und im Modul geprüft. Fehler beim Lesen, Prüfen oder Codieren der Seitendaten werden als Fehlermeldung zurückgegeben und in der Instanz gespeichert. Falls **Mehr** im nativen Fehlerbalken nicht geöffnet werden kann, gibt es in der Modulinstanz die Aktion **Unterseiten prüfen / Fehlerdetails**. Sie zeigt die letzte am Modul angekommene Seitenanfrage, gespeicherte Fehler und das Ergebnis einer Serverprüfung für PV und die konfigurierten Räume. Die Prüfung sendet keine Gerätebefehle und verändert keine Quellen oder Instanzeinstellungen. Eine erfolgreiche Serverprüfung bestätigt die Browserübertragung nicht.

Das native Maximieren einer Instanz oder eines Instanzlinks kann unter Symcon 9.0 die normale Symcon-Liste anzeigen; eine eigene HTML-Vollbildansicht steht dort nicht zur Verfügung. Unterseiten mit einer unterstützten Vollbild-SDK-Version verwenden weiterhin die native Navigation.

**0.14 korrigierte die ungeprüfte Vollbildumstellung aus 0.13.** Statt auf jeder Unterseite grundsätzlich Typ 2 zu setzen, prüft das Modul Version und SDK-Unterstützung. Das gilt auch für Bewegung, Temperaturen, PV und Raumseiten. HTML-Erzeugungsfehler erscheinen als Hinweis; die Diagnose zeigt Version, Darstellungsart und die Größe der tatsächlich erzeugten HTML-Ausgabe. Die Darstellungswahl **Liste / Instanzspezifisch** im Browser kann das Modul nicht selbst ändern.

Bei weiterhin leeren Ansichten zuerst den Öffnungsweg prüfen: Unter Symcon 9.0 **Details öffnen** bei PV innerhalb der Hausstatus-Kachel verwenden; Räume über den vorhandenen Menüpunkt öffnen. Der Tooltip von **Aktualisieren** muss nach dem Update **Hausstatus 0.29** anzeigen. Änderungen in der zentralen Instanz übernehmen und die Visualisierung vollständig neu laden, damit auch das aktualisierte HTML geladen wird.

Offizielle SDK-Referenzen: [Visualisierungskonstanten](https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/konstanten/), [SetVisualizationType](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/module/setvisualizationtype/), [openObject](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/html-sdk/openobject/) und [IPS_GetMediaContent](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/medienverwaltung/ips-getmediacontent/).

## Änderungen in 0.18

Raumseiten zeigen oben die wichtigsten Raumwerte und darunter jeweils einen auswählbaren Bereich, beispielsweise **Licht**, **Klima** oder **Medien**. Jedes Gerät steht in einer eigenen kompakten Zeile. Schalter bleiben direkt erreichbar; weitere Werte und Einstellungen lassen sich pro Gerät aufklappen. Temperaturtasten sind nochmals kleiner und benötigen weniger Platz.

Die PV-Kachel der Startseite zeigt zusätzlich die AC-Leistung und den Produktionsstatus jedes bereits eingerichteten Wechselrichters. Dafür werden aus dem konfigurierten PV-Raum die zusammengehörigen Werte **Leistung AC** und **Wechselrichter produziert** übernommen. **Produziert: Ja** erscheint grün, **Produziert: Nein** rot; ein fehlender oder ungültiger Status bleibt grau und heißt **Unbekannt**. Der Status wird aus der Produktionsvariable gelesen, nicht aus der Leistung abgeleitet. Bestehende Quellen, Geräte und Bedienskripte bleiben erhalten. Räume bleiben über das Menü erreichbar.

## Änderungen in 0.19

Raumbefehle, einschließlich HEOS-Auswahl, werden als JSON-Text an das Modul übergeben. Das Modul liest den Text ein und prüft anschließend weiterhin den Datentyp und die erlaubten Werte der Zielvariable. Auch Türbestätigung und Türöffnung verwenden diesen Übertragungsweg; Freigabe und Sicherheitsabfrage bleiben erforderlich. Vorhandene PHP-Aufrufe mit Arrays bleiben unterstützt.

## Änderungen in 0.20

Die HEOS-Auswahl steht im Mediengerät-Bereich der Startseite und in der Raumansicht direkt beim zugehörigen Receiver, statt unter weiteren Werten. Eine eindeutige HEOS-Radio-/Playlist-Auswahl wird aus vorhandenen Raumeinträgen erkannt. Alternativ lässt sich die Variable unter Mediengerät ausdrücklich auswählen. Mehrere mögliche Quellen müssen dort zugeordnet werden. Die bestehende Variablenaktion bleibt zuständig.

Direkte Raumregler senden den skalaren Wert und die Ziel-ID im Befehlsnamen. Der Server prüft weiterhin Raumzugehörigkeit, Datentyp und Optionen. „Bedienung prüfen / HEOS-Fehlerdetails“ zeigt in der betroffenen Rauminstanz die zuletzt empfangene Aktion und den gespeicherten Bedienfehler, ohne einen Gerätebefehl auszuführen. Die Fußzeile zeigt die geladene HTML-Version auch auf dem Handy.

## Änderungen in 0.21

Unter Mediengerät lässt sich eine separate Variable „HEOS – aktueller Status (nur Anzeige)“ zuordnen. Ihr formatierter Symcon-Wert wird in der Cinema-Kachel als „HEOS aktuell“ und in der Raumansicht direkt beim Receiver angezeigt. Die Radio-/Playlist-Auswahl bleibt die Bedienung und wird nicht als Wiedergabestatus ausgegeben. Änderungen der Statusvariable aktualisieren die Anzeige automatisch. Die Statuszeile ist nicht schaltbar. Ohne konfigurierte Statusquelle wird kein Wiedergabestatus aus der Auswahl abgeleitet.

## Änderungen in 0.22

Türfreigabe: „Freigeben“ ist grün, „Sperren“ rot. „Tür öffnen“ wird bei aktiver Freigabe grün. Bei Licht und Receiver zeigen die Ein-/Aus-Buttons den bestätigten Zustand grün beziehungsweise rot. Dies gilt auch für den Esstisch und die Raumregler, einschließlich weiterer Marantz-Receiver. Die Schalter in den Raumansichten verwenden dieselben Farben. Unbekannte Zustände bleiben neutral; Bedienung und Sicherheitsprüfung bleiben unverändert.

## Änderungen in 0.23

Raumseiten haben oben eine kompakte Raumauswahl. Die Auswahl lädt ausschließlich den gewählten Raum in derselben Ansicht, statt alle Räume untereinander anzuzeigen. Ohne festen Raumfilter beginnt die Ansicht mit dem ersten eingerichteten Raum. Ein vorhandener Raumfilter bleibt der Ausgangsraum. Der Wechsel verändert keine Instanzeinstellungen und löst keine Geräteaktionen aus. Im bestehenden Menübereich „Räume“ bleibt eine gemeinsame Raumansicht sichtbar; weitere zugehörige Raumkacheln werden dort ausgeblendet. Ihre Instanzen und Konfigurationen bleiben erhalten. Andere Objekte und fremde Dashboards werden nicht verändert. Die Startseite bleibt unverändert.

## Änderungen in 0.24

Ab Symcon 9.1 blendet das Modul den nativen Vergrößerungspfeil seiner Kacheln und der auf diese Instanzen zeigenden Links aus. Die Raumauswahl und internen Detailseiten bleiben erreichbar. Unter Symcon 9.0 bleibt der Pfeil sichtbar: Die erforderliche Funktion steht dort nicht zur Verfügung, und das Kachel-HTML kann den äußeren Symcon-Kopf nicht ändern. Es werden keine fremden Kacheln verändert. [Offizielle Referenz: IPS_SetHiddenMaximize](https://www.symcon.de/de/service/dokumentation/befehlsreferenz/objektverwaltung/ips-sethiddenmaximize/).

## Änderungen in 0.25

Der Menüpunkt „Räume“ startet mit der Raumauswahl und einer kompakten Übersicht: je Raum vorhandene Temperatur, Bewegung und Lichtstatus. Geräte und Regler erscheinen erst nach Auswahl eines einzelnen Raums. „Übersicht · Raum auswählen“ führt zurück zur Zusammenfassung. Die Übersicht ist nur lesbar; fehlende Werte werden nicht erfunden. Separate Raumseiten behalten ihren Ausgangsraum. Die Startseite bleibt unverändert.

## Änderungen in 0.26

Eine optionale Startseiten-Kachel „Zusatzheizung“ enthält den Schalter eines Elektroheizkörpers, Minimal- und Maximaltemperatur sowie den bestehenden Manuell-/Automatik-Schalter. Die Aktivierung des vorhandenen Wochenplans liegt in einem aufklappbaren Bereich. Quellen und Bezeichnung werden unter „Zusatzheizung auf der Startseite“ frei zugeordnet; keine persönlichen Variablen-IDs sind vorbelegt. Regler verwenden die vorhandenen Variablendarstellungen und Aktionen. Ohne Aktion bleiben Werte lesbar. Temperaturgrenzen werden auf Bereich, Schrittweite und Minimal ≤ Maximal geprüft. Eine neue Heizungsautomatik oder ein neuer Zeitplan wird nicht erzeugt.

## Einzelne Startseiten-Kacheln ab 0.29

In der zentralen Instanz unter **Übersicht und Detailkacheln** die **Zielkategorie der Startseite** wählen, **Startseite in einzeln verschiebbare Symcon-Kacheln aufteilen** aktivieren und Änderungen übernehmen. Die Kategorie muss in der eigenen Visualisierung sichtbar sein. Die Quellen bleiben in der zentralen Instanz gespeichert; die einzelnen Kacheln erben sie. Anwesenheit/Alarm, Haustür, Wohnzimmerlicht, Esstisch, Cinema, Zusatzheizung, Gerätewarnungen, PV, Außensteuerung und Wetter erhalten eigene Instanzen, wenn Quellen eingerichtet sind. Bewegung und Temperaturen folgen der bisherigen Detail-Einstellung. Räume bleiben im Menü.

Die Kacheln werden direkt in der gewählten Kategorie angelegt. Bestehende eigene Einzelinstanzen aus 0.27/0.28 werden wiederverwendet und dorthin verschoben. Eigene Bezeichnungen und Positionen bleiben erhalten. Ohne eindeutiges Ziel oder bei fremden Kennungen bleibt die gemeinsame Kachel sichtbar; der Fehler steht in der Instanz-Zusammenfassung und unter **Startseiten-Kacheln / Ziel prüfen**. Die gemeinsame Kachel wird erst nach dem vollständigen Anlegen aller eingerichteten Bereiche ausgeblendet.

Zum Zurückkehren den Schalter für die Aufteilung deaktivieren und Änderungen übernehmen. Sichtbarkeitseinstellungen werden dauerhaft gespeichert und bleiben nach einem Neustart erhalten. Instanzen, Quellen, Geräte und Automationen werden nicht gelöscht.

## Downgrade direkt in Symcon

Unter **Module Control** beim Hausstatus auf das **Zahnrad** klicken und den Zweig **rollback-0.26** wählen. Danach in der zentralen Hausstatus-Instanz **Änderungen übernehmen** und die Visualisierung neu laden. Der Zweig enthält das gemeinsame Layout aus 0.26 mit der Wiederherstellung der Sichtbarkeit und dauerhaft gespeicherten Rückkehrdaten (Wartungsstand 0.26.2). Er wird unabhängig von main bereitgehalten.

Zur aktuellen Version über dasselbe Zahnrad wieder **main** wählen. Die Quellen bleiben erhalten. Das Downgrade betrifft dieses Modul, nicht die installierte Symcon-Version. Alte ungepflegte Code-Stände ohne Wiederherstellung sind nicht als Rückkehrweg vorgesehen.

[Offizielle Dokumentation: Module Control – Zweig wechseln](https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/module-control/)
