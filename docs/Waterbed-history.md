# Wasserbett-Verlauf ab v0.112

Die Raumansicht verwendet kompakte Karten mit den gemeinsamen Rundungen und klaren Bereichstasten. Bestehende Bedienaktionen bleiben unverändert.

Im Schlafzimmer stehen für das Wasserbett Kalenderzeiträume Tag, Woche, Monat und Jahr zur Wahl. W zeigt mittlere Leistung aus ID 16905, kWh den Verbrauch je Intervall aus ID 17732. Tag verwendet Stundenwerte, Woche und Monat Tageswerte, Jahr Monatswerte. Sven (standardmäßig 30213) und Susi (standardmäßig 43240) werden als Temperaturkurven auf der rechten °C-Achse dargestellt. Ihre Quellen folgen den vorhandenen Temperatureinstellungen.

Die Diagrammquellen 16905 und 17732 sind unabhängig von den bisherigen konfigurierbaren Statuskarten. Der bestehende Jahresvergleich verwendet weiterhin den konfigurierten Gesamtzähler. Die alte 7-Tage-Tabelle entfällt ab v0.114; die Zeitraumauswahl im Diagramm ersetzt sie.

Die vier Diagrammvariablen müssen bereits archiviert sein. ID 17732 benötigt den Archiv-Aggregationstyp **Zähler**. Bei Standardaggregation erscheint ein Hinweis statt eines falschen Verbrauchs. Die vorhandene Einstellung WaterbedEnergyUnit bestimmt, ob Verbrauchsdaten bereits in kWh vorliegen oder von Wh umgerechnet werden. Die Erweiterung verändert keine Archivierung oder Geräteaktionen. Laufende Intervalle sind unvollständig; fehlende Werte werden nicht als Null dargestellt.

Symcon definiert Avg bei Zähleraggregation als Summe positiver Deltas, bei Standardaggregation als Mittelwert: https://www.symcon.de/de/service/dokumentation/modulreferenz/kern-instanzen/archive-control/ac-getaggregatedvalues/

Die Ergebnisse werden fünf Minuten zwischengespeichert. Pro Neuabfrage entstehen höchstens 16 Aggregationsabfragen. Fehler einer einzelnen Quelle lassen die anderen Kurven verfügbar.

Prüfung: PHP-Syntax, isolierte Archivtests (Quellen, Einheiten, Cache, fehlende Werte, Aggregationstyp), Frontendtests für alle acht Zeitraum/Einheit-Kombinationen, bestehende Bedien-/Licht-/Heizungs-/Shield-Tests und Browserprüfung bei 840 und 390 Pixeln. Browserdaten sind simuliert; ein Test gegen das laufende Symcon-System ist nicht erfolgt.

Ab v0.115 gibt es eine gemeinsame Diagrammansicht: Tag, Woche, Monat, Jahr oder Jahresvergleich. Der Jahresvergleich ist keine zusätzliche Grafik unter dem Verlauf mehr. Die Raumgestaltung verwendet dieselben weißen Flächen, grauen Titelbalken, Rahmen und Rundungen wie die Hausstatus-System- und PV-Kacheln.

Ab v0.116 haben Monat und Jahr acht statt vier Wertintervalle. Die Monatsansicht zeigt Datumsmarken alle zwei Tage, die Jahresansicht einzelne Monatsnamen. Die Datumsgrenzen folgen Europe/Berlin einschließlich Zeitumstellung. Auf schmalen Bildschirmen lässt sich das feinere Diagramm horizontal scrollen. Die Messwertaggregation bleibt unverändert.
