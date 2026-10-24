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

Die Watt-Achse beginnt immer bei 0, beschriftet jede 10-W-Stufe und hat feine Zwischenlinien in 5-W-Abständen. Ohne Leistungsdaten beginnt der leere Rahmen bei 0–10 W; fehlende Archivwerte sind ausdrücklich gekennzeichnet. Die obere Grenze wird anhand von Verlauf und aktuellem Wert auf eine 10-W-Stufe aufgerundet.
