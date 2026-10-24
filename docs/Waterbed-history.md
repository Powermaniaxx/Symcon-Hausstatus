# Wasserbett-Verlauf ab v0.112

Die Raumansicht verwendet kompakte Karten mit den gemeinsamen Rundungen und klaren Bereichstasten. Bestehende Bedienaktionen bleiben unverändert.

Im Schlafzimmer stehen für das Wasserbett Kalenderzeiträume Tag, Woche, Monat und Jahr zur Wahl. W zeigt mittlere Leistung aus ID 16905, kWh den Verbrauch je Intervall aus ID 17732. Tag verwendet Stundenwerte, Woche und Monat Tageswerte, Jahr Monatswerte. Sven (standardmäßig 30213) und Susi (standardmäßig 43240) werden als Temperaturkurven auf der rechten °C-Achse dargestellt. Ihre Quellen folgen den vorhandenen Temperatureinstellungen.

Die Diagrammquellen sind 16905 und 17732. Ab v0.117 verwendet auch die Karte „Aktuelle Leistung“ direkt ID 16905. Die übrigen Statuskarten bleiben konfigurierbar. Der bestehende Jahresvergleich verwendet weiterhin den konfigurierten Gesamtzähler. Die alte 7-Tage-Tabelle entfällt ab v0.114; die Zeitraumauswahl im Diagramm ersetzt sie.

Die vier Diagrammvariablen müssen bereits archiviert sein. ID 17732 benötigt den Archiv-Aggregationstyp **Zähler**. Bei Standardaggregation erscheint ein Hinweis statt eines falschen Verbrauchs. Die vorhandene Einstellung WaterbedEnergyUnit bestimmt, ob Verbrauchsdaten bereits in kWh vorliegen oder von Wh umgerechnet werden. Die Erweiterung verändert keine Archivierung oder Geräteaktionen. Laufende Intervalle sind unvollständig; fehlende Werte werden nicht als Null dargestellt.

Symcon definiert Avg bei Zähleraggregation als Summe positiver Deltas, bei Standardaggregation als Mittelwert: https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getaggregatedvalues/

Die Ergebnisse werden fünf Minuten zwischengespeichert. Pro Neuabfrage entstehen höchstens 16 Aggregationsabfragen. Fehler einer einzelnen Quelle lassen die anderen Kurven verfügbar.

Prüfung: PHP-Syntax, isolierte Archivtests (Quellen, Einheiten, Cache, fehlende Werte, Aggregationstyp), Frontendtests für alle acht Zeitraum/Einheit-Kombinationen, bestehende Bedien-/Licht-/Heizungs-/Shield-Tests und Browserprüfung bei 840 und 390 Pixeln. Browserdaten sind simuliert; ein Test gegen das laufende Symcon-System ist nicht erfolgt.

Ab v0.115 gibt es eine gemeinsame Diagrammansicht: Tag, Woche, Monat, Jahr oder Jahresvergleich. Der Jahresvergleich ist keine zusätzliche Grafik unter dem Verlauf mehr. Die Raumgestaltung verwendet dieselben weißen Flächen, grauen Titelbalken, Rahmen und Rundungen wie die Hausstatus-System- und PV-Kacheln.

Ab v0.117 zeigt die Jahresachse immer Januar bis Dezember, die Monatsachse immer den 1. bis zum letzten Kalendertag (28/29/30/31). Die Datumsgrenzen folgen Europe/Berlin einschließlich Zeitumstellung. Monat und Jahr haben acht Wertintervalle. Auf schmalen Bildschirmen lässt sich das feinere Diagramm horizontal scrollen. Zukünftige Zeiträume bleiben ohne Messwerte.

Leistung ID 16905 verwendet bei Standardarchivierung die aggregierten Watt-Mittelwerte. Ab v0.118 werden bei Zählerarchivierung stattdessen zeitgewichtete Mittelwerte aus Rohdaten berechnet. Positive Zähler-Deltas werden niemals als Watt ausgegeben. Je Zeitraum werden höchstens 10.000 Rohwerte und ein Grenzwert gelesen; beim Erreichen des Limits bleiben unvollständige ältere Intervalle leer. Der aktuelle Leistungswert wird direkt gelesen; historische Stunden-, Tages- oder Monatsmittel können davon abweichen. Das Modul ändert keine Archiveinstellungen und nimmt keine pauschale Division durch 60 vor.

Ohne archivierte Leistungswerte orientiert sich die Watt-Skala am aktuellen Wert (beispielsweise 0–60 W bei aktuell 51 W). Leistung und Temperatur sind ausdrücklich an ihren getrennten Achsen beschriftet.

Ab v0.119 beginnt die Watt-Achse weiterhin bei 0 und wählt die Schrittweite automatisch für höchstens etwa sieben Beschriftungen. Bei etwa 51 W bleiben 10-W-Schritte mit 5-W-Zwischenlinien; bei etwa 260 W erscheinen 50-W-Schritte mit 25-W-Zwischenlinien (0–300 W). Größere Werte erhalten entsprechend größere Schritte. Fehlende Archivwerte sind ausdrücklich gekennzeichnet. Die obere Grenze folgt Verlauf und aktuellem Wert.


## v0.120
Tag/Woche/Monat/Jahr und W/kWh bleiben in einer Ansicht. Feste Achsen: Leistung 0–2000 W, Verbrauch Tag 0–10 kWh, Woche 0–70 kWh, Monat 0–150 kWh, Temperatur rechts 0–35 °C. Die Jahresansicht in kWh zeigt je Kalendermonat zwei einzelne Balken für Vorjahr und aktuelles Jahr (2025/2026), Skala 0–150 kWh/Monat. Die beiden Jahressummen stehen getrennt darüber. Keine kumulierten Monatswerte. Jahr in W zeigt weiterhin Monatsmittel der Leistung.

Alle Verbrauchsverläufe und der Jahresvergleich stammen aus ID 17732 mit Zähleraggregation. Monatswerte verwenden die archivierten Monatsverbräuche; Jahressummen addieren diese Monate. Bei fehlenden vergangenen Monaten bleibt die vollständige Jahressumme unbekannt. Zukünftige Monate bleiben leer. Sven und Susi lassen sich unabhängig per Schaltfläche ein-/ausblenden. Zeitraum, Einheit und Sichtbarkeit werden je Browser und Ansichtsadresse lokal gespeichert; gesperrter Browserspeicher verhindert die Bedienung nicht.

Das vorhandene helle Raum-/Kacheldesign bleibt erhalten. Mobile Diagramme scrollen innerhalb der Kachel. Werte oberhalb fester Achsengrenzen werden am Rand begrenzt; Tooltip und Monatstabelle enthalten die tatsächlichen Messwerte.

Validierung: Diagrammtests für acht Modi, Monatsbalken, feste Achsen, Schalter und gespeicherte Einstellungen; PHP-Archivtests einschließlich Monatsaggregation, Wh-Umrechnung, Datenlücken und Cache; vorhandene Licht-/Raum-/Shield-Layouttests; Browserprüfung bei 840/390 px einschließlich Neuladen. Browserdaten sind simuliert; keine Live-Prüfung am Symcon-Server.


Nach der abschließenden Aufteilung: **Jahr** bietet eine Jahreswahl (2025/2026) für einzelne Monatsbalken in kWh oder Monatsmittel in W. Leistung und Temperaturen folgen dem gewählten Jahr. **Jahresvergleich** zeigt beide Jahre nebeneinander in kWh und getrennte Jahressummen; die optionalen Temperaturkurven gehören hier zum aktuellen Jahr. Die Jahreswahl wird ebenfalls gespeichert. Für das Vorjahr werden zusätzlich vier Archivserien gelesen, damit sind es höchstens 20 Aggregationsabfragen pro fünf Minuten zuzüglich zwei Monatsverbrauchsabfragen für die Jahressummen (30-Minuten-Cache).

Alle fünf Ansichten haben identische Diagrammabmessungen (viewBox 640 × 260, Mindestbreite 640 px). Separate Größen für Monat/Jahr entfallen. Die Browserprüfung vergleicht die tatsächlichen Breiten und Höhen aller Ansichten bei 840 und 390 px.

Ein zusätzlich ausgeführter bestehender Test tests/homepage-parts.js scheitert sowohl mit dem unveränderten Ausgangsmodul v0.119 als auch mit v0.120: Im Test-DOM fehlt presenceHistorySection. Dieser bereits vorhandene Testfehler gehört nicht zum Wasserbett-Umbau. Alle Wasserbett-, Licht-, Raum-, Shield-Layout-, Shield-Parser-, Philips- und Interaktionstests, die hier ausgeführt wurden, bestehen.
