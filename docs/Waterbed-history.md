# Wasserbett: Verlauf und Tageswerte (v0.126)

## Aktueller Ladeweg ab v0.126

Das Diagramm gehört wieder direkt zur normalen Raumantwort wie vor dem getrennten Nachladen. Es gibt keine zweite Historienanfrage, keinen Ladebutton und keinen timerabhängigen Ladezustand. Die zwischenzeitlichen Nachladeänderungen aus v0.122/v0.123 sind zurückgenommen. Der doppelte Wasserbett-Seitencache und die ungenutzten alten Tagesgrenzabfragen entfallen. Diagramm und Jahresvergleich werden innerhalb der Raumantwort unabhängig gegen Fehler abgesichert; aktuelle Werte bleiben sichtbar. Der bestehende Diagrammcache und die verlustfreie Speicherung großer Zustandsdaten bleiben erhalten.

Tag/Woche/Monat mit Heizphasen, Jahres-Tagesmittel, W/kWh, Tageswerttabelle und Jahresvergleich bleiben verfügbar. Die Tests prüfen jetzt den initialen Raumaufruf mit 45.000 Heizwechseln und das direkte Rendern ohne weitere Bridge-Anfrage.

## Bedienung

- Tag, Woche und Monat: W zeigt archivierte Leistungswechsel als Stufenkurve. Jede aufgezeichnete Heizstufe bleibt erhalten; es werden keine Tagesverbräuche als Watt dargestellt.
- Monat: vollständige Zeitachse vom 1. bis zum letzten Kalendertag. Woche/Monat bieten eine Tagesauswahl für den detaillierten Verlauf von 00:00 bis 24:00. Die Vergrößerung 1×/2×/4× ermöglicht horizontales Scrollen.
- Jahr: W zeigt Tagesmittel mit der Zeitachse Jan bis Dez. Aktuelles Jahr und Vorjahr sind auswählbar. Zukünftige Tage bleiben leer.
- kWh zeigt Verbrauchsbalken je Stunde (Tag), Tag (Woche/Monat) oder Monat (Jahr). Der Jahresvergleich enthält beide Jahre mit Monatsbalken und Jahressummen.
- Die Hauptachse zeigt ausschließlich W oder kWh. Sie beginnt bei 0, passt sich den Daten mit lesbaren Schritten an und hat dezente Zwischenlinien. Werte werden nicht an einer festen Obergrenze abgeschnitten.
- Unter jedem Zeitraum befindet sich genau eine aufklappbare Tageswerttabelle mit Datum, mittlerer Leistung in W und Verbrauch in kWh. Laufende Tage sind als „bisher“ markiert; fehlende Werte stehen als —. Im Jahresvergleich gibt es stattdessen die Monatsvergleichstabelle.
- Sven und Susi haben einen separaten aufklappbaren Temperaturverlauf in °C mit einzeln schaltbaren Kurven.
- Maus, Fingertipp oder Pfeiltasten zeigen Werte des Hauptdiagramms. Zeitraum, Einheit, Jahr, Vergrößerung und Temperaturauswahl werden lokal gespeichert.

## Datenbasis

Leistung: ID 16905, ohne Umrechnung in kW. Verbrauch: Zähler ID 17732, konfigurierte Wh werden in kWh umgerechnet. Temperaturen: konfigurierte Sven-/Susi-IDs (30213/43240). Aktuelle Leistung wird separat vom archivierten Verlauf angezeigt.

Tag/Woche/Monat verwenden Rohwerte aus dem Archiv und übernehmen den letzten bekannten Wert vor Periodenbeginn. Nur unmittelbar gleiche Werte werden zusammengefasst; keine Glättung der Schaltvorgänge. Abfragen sind paginiert bis 50.000 Einträge; bei Erreichen dieser Grenze bleibt der unbekannte ältere Abschnitt leer und ein Hinweis erscheint.

Jahresmittel kommen aus den täglichen Standardaggregaten von ID 16905. Ist diese Variable fälschlich als Zähler aggregiert, werden Tagesmittel zeitgewichtet aus Rohwerten berechnet. Die Tageswerttabelle für kurze Zeiträume verwendet ebenfalls zeitgewichtete Rohwerte. Unvollständige Archivabdeckung liefert keinen erfundenen Tagesmittelwert. Kalendertage berücksichtigen Sommer-/Winterzeit.

Verbrauch stammt ausschließlich aus den Zähleraggregaten. Tageswerte werden zusätzlich für die Jahrestabelle gelesen. Jahresverbrauch und Vorjahresvergleich behalten ihre bestehende Berechnung. Cache: 5 Minuten, bei Datums-/Konfigurationswechsel ungültig. Alle neuen Archivzugriffe sind lesend; Steuerungen und Aktionen bleiben unverändert.

## Prüfung

`php tests/waterbed-history.php`, `php tests/waterbed-annual.php`, `node tests/waterbed-chart.js`.
Die Tests prüfen Schaltstufen, Archiv-Paginierung, Tagesmittel, Zeitumstellung, Einheiten, fehlende Werte, Tagesdetails/-tabellen, Jahresvergleich, Skalen, Temperatureinblendung und gespeicherte Einstellungen. Zusätzlich im Browser mit synthetischen Daten auf Desktop und Smartphone geprüft. Ein Live-Test gegen die private Symcon-Installation ist nicht enthalten.


## v0.122: Raumaufbau und Historie getrennt

Der erste Schlafzimmer-Aufruf liest nur aktuelle Werte. Die Archivhistorie wird erst nach dem Zeichnen des sichtbaren Wasserbett-Bereichs in einer getrennten PageRead-Anfrage geladen. Navigation oder Bedienung kann diese Anfrage ablösen; verspätete Antworten werden verworfen. Ergebnisse werden fünf Minuten zwischengespeichert, ein abgelaufener Cache löst beim Raumwechsel keine neue Archivabfrage aus. Fehler bleiben im Wasserbett-Bereich und verhindern nicht das Laden des Raums. Ein erneuter Versuch ist über „Diagramm und Tageswerte laden“ möglich.

ApplyChanges leert die Wasserbett-Caches jeder Instanz. Alle Seiten verwenden dieselbe HTML-Vorlage mit einer Versionsangabe aus library.json. Die vorhandenen Versionsbeschriftungen werden nach dem Einrichten der Unterseiten erneut aktualisiert, damit deren Einrichtung keine alten Namen hinterlässt. Bereits geöffnete Ansichten müssen nach dem Modulupdate neu geöffnet werden.

Zusätzliche Prüfungen: `php tests/waterbed-page-loading.php` (mbstring benötigt) und `node tests/waterbed-page-loading.js`. Sie prüfen den archivfreien Raumaufruf, Fehlerbegrenzung, Cache-Ablauf, getrenntes Nachladen, erneute Versuche und Navigation während einer laufenden Historienanfrage.


## v0.123: Nachladen trotz Sensoraktualisierung und Raumtitel

Automatische Hintergrund-/Sensoraktualisierungen verdrängen keine laufende Historienanfrage mehr. Nur bewusste Navigation, Bedienung oder manuelles Aktualisieren darf sie ablösen. Der automatische Ladeversuch gilt erst dann als gestartet, wenn die Anfrage tatsächlich gesendet wurde.

Die Raumübersicht wird auch mit einem Versionszusatz im Instanz- oder Linknamen erkannt. Versionsbeschriftungen werden anhand der Zugehörigkeit zur Hausstatus-Hauptinstanz aktualisiert, nicht anhand fester Ordner. Vorhandene Raumseiten und Links außerhalb der bisherigen Visualisierungsordner werden dadurch ebenfalls erfasst. Kategorien, fremde Instanzen, Linkziele, Sichtbarkeit und Anordnung bleiben unverändert.

Regressionstests: `php tests/room-version-navigation.php` und `node tests/waterbed-page-loading.js`, einschließlich Sensorereignissen während einer laufenden Historienanfrage.


## v0.125: Große Archivdaten ohne Symcon-Pufferwarnung

Symcon warnt bei einem einzelnen Buffer ab 256 KiB und schneidet Inhalte oberhalb von 1 MiB ab (https://www.symcon.de/de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/module/setbuffer/). Ein Test mit 45.000 archivierten Heizwechseln reproduziert einen 1,14-MB-Diagrammpuffer. Das bisherige direkte SetBuffer löst damit eine native Warnung aus und kann den JSON-Cache beschädigen.

Diagramm-Cache, Wasserbett-Seitencache und LastState werden nun bei Bedarf verlustfrei in Blöcken von höchstens 192 KiB abgelegt. Die Daten werden dabei weder geglättet noch gekürzt. Große Inhalte werden vor dem Aufteilen als Base64 codiert, sodass einzelne Blöcke gültige API-Zeichenketten bleiben. Ein kleines Manifest enthält Generation, Länge und SHA-256. Erst nach vollständigem Schreiben werden neue Blöcke freigegeben; fehlende oder beschädigte Blöcke werden als Cache-Miss behandelt. Alte einfache Buffer bleiben lesbar. Ersetzen und Invalidieren räumen die bisherigen Blöcke auf.

Prüfung: `php tests/large-buffer.php`, `php tests/waterbed-buffer-limit.php` und `php tests/waterbed-page-transfer.php` (letzterer benötigt mbstring). Der Übertragungstest durchläuft die echten HandlePageRequest-/Waterbed-Methoden mit synthetischem Archiv über die JSON-Antwort bis zur folgenden Hintergrundaktualisierung; jeder SetBuffer-Aufruf wird gegen die native Warnschwelle geprüft. Alle 45.000 Leistungswechsel bleiben erhalten. Die private Symcon-Detailmeldung wurde nicht ausgelesen; ein Live-Test der Installation steht aus.
