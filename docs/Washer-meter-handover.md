# Waschmaschine: Zählerwechsel

Ab Version 0.111 kombiniert die Verbrauchsanzeige alte und neue Leistungsarchive zeitlich getrennt: Variable 15161 bis zum 24.10.2026 ca. 13:35 Uhr (Europe/Berlin), Variable 50687 danach. Der Zeitpunkt basiert auf der Angabe des Nutzers „vor 20min“ um ca. 13:55 Uhr. Die gemeinsamen Einstellungen WasherLegacyPower und WasherMeterChangedAt werden über ConfigSource auch für gespiegelte Kacheln gelesen.

Heute, Gestern, diese Woche, diesen Monat, dieses Jahr und letztes Jahr sind Näherungswerte aus Stundenmittelwerten. Eine Stunde am Wechselzeitpunkt wird anteilig aufgeteilt. Vorjahresabfragen erfolgen separat, damit zwei Kalenderjahre nicht an das 10000-Stunden-Limit einer einzelnen Abfrage stoßen. Fehlende Archivdaten bleiben unbekannt oder werden als mögliche Lücken gekennzeichnet; sie werden nicht aus dem Gesamtstand erfunden. Die alte Variable und ihr Archiv sollten erhalten bleiben. Das Modul ändert weder Gerätezähler noch Archivdaten und schaltet keine Steckdose.

Der separate Gesamtverbrauch bleibt 7,26 kWh Altbestand plus neuer Gesamtzähler 40662. Dieser Altbestand wird nicht zusätzlich auf Tages- oder Jahreswerte addiert. Die einmalige Korrektur von 6,15 auf 7,26 kWh bleibt enthalten.

Isolierter Test: php tests/washer-history.php. Prüft Wechsel innerhalb einer Stunde, alte/neue Zeiträume, Vorjahr, Cache, Tageswechsel und fehlendes Archiv. Produktive Archivabdeckung wurde ohne Zugriff auf den laufenden Symcon nicht geprüft. Es gibt keine neuen Hintergrundtimer; Archivwerte werden bei Zustandsabfragen gelesen und 15 Minuten gepuffert.
