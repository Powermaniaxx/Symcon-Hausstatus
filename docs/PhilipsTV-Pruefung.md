# Prüfstand Philips TV – Hausstatus 0.132 / Philips TV 1.0.0

Ausgangsstand: Powermaniaxx/Symcon-Hausstatus `ba86d39f23cf4258fc2bae45a4e3bfb8b26467ce` (0.131, Build 132). Neue Fassungen: Hausstatus 0.132, Build 133, und separate Philips-TV-Bibliothek 1.0.0, Build 1. Beide werden in ihren eigenen Repositories veröffentlicht. Noch nicht in einer realen Symcon-Installation bzw. am TV geprüft.

## Bestanden

- PHP-Syntax aller Moduldateien und Symcon-Verzeichnisstruktur mit einem Modul je Repository.
- `philips-direct.php` (separates Philips-Repository): Konfiguration, echte Befehlsplanung mit nachgebildeter TV-API, keine Statusrunde vor POST, getrennte Bestätigung, Lautstärkeskala mit Minimum ungleich 0, schnell aufeinanderfolgende Audio-Befehle, Werteprüfung, Authentifizierungsfehler, Teilfunktionen, nicht unterstützte Endpunkte, Standby-Pause und Wake-Signal, veraltete Daten, Vorrang von Befehlen gegenüber laufenden Abfragen.
- `philips-house.php`: tatsächlicher Hausstatus-RequestAction-Einstieg, zentrale Konfigurationsquelle und abgeleitete Kacheln, unveränderte Befehlstypen, direkter TV-Status, State-Abonnement, alte Philips-Flow-Kommandos unterdrückt, ungültige Instanz und Deaktivierung.
- `philips-transport.php` (separates Philips-Repository): tatsächlicher Transportcode mit nachgebildeten cURL-Funktionen; API-6-HTTPS-URL, Digest, getrennte Zugangsdatenoptionen, Zertifikatsprüfung, IPv6, Zeitlimits, keine Weiterleitungen, HTTP- und JSON-Fehler, leere/HTML-Bestätigung, keine Kennwörter aus TV-Fehlerantworten in Diagnose.
- `philips-direct.js`: gesamte JavaScript-Syntax, richtige Ziele aller neuen Kachelaktionen, Lautstärkeregler, aufklappbare Fernbedienung, Bedienzustände bei deaktiviertem/unerreichbarem/veraltetem TV, Textausgabe statt Ausführen von TV-Inhalten.
- Headless-Chrome mit echter Kachel-CSS und den echten Renderer-/Bedienfunktionen: 320 und 390 Pixel Hochformat, 844 Pixel Querformat, Desktop; kein horizontaler Überlauf, mindestens 40 Pixel hohe neue Touch-Tasten, funktionierende Ereignisse, Erhalt des Fernbedienungs-Klappzustands.
- Bestehende Prüfungen für Heizungszentrale, Heizprofil, Startseitenverwaltung (PHP), Befehlsantwort/Entprellung (PHP), Anwesenheitswoche, Räume/Terrasse, Mediengeräte und Schlafzimmer, Shield/TV-Kopplung, Variablensichtbarkeit, Unterseitenversionen, Visualisierungsspiegelung, Waschmaschinen-Zähler/Verlauf und Wasserbett bestanden. Ebenso Cast-, Philips-Altweg-, Shield-Layout-/Power-/Parser- und Farbsteuerungstests.

## Bereits im unveränderten Ausgangsstand fehlgeschlagen

Die folgenden Fehler wurden gegen separat gespeicherte Originaldateien des obigen Commits reproduziert. Diese Philips-Änderung bearbeitet die betroffenen Funktionen nicht:

- `laundry.php`: Erwartung „Heating pause must still show running“.
- `home-environment-layout.js`: widersprüchliche Boolean-Erwartung in Zeile 44.
- `homepage-parts.js`: fehlendes DOM-Element in der isolierten Testumgebung.
- `interaction-performance.js`: fehlende Variable `roomSensorRefreshPending` in der isolierten Testumgebung.
- `philips-standby.js`: Speichern des lokalen Shield-Teststatus liefert false in dieser Windows-Testumgebung.

`shield-rpc.js` konnte seinen lokalen Testserver wegen eines vom Ausführungsumfeld abgewiesenen Zugriffs auf 127.0.0.1 nicht erreichen. Die betroffene RPC-Implementierung ist unverändert.

## Noch am Gerät prüfen

Mit den vorhandenen Kopplungsdaten einmal Status sowie Ein/Aus, Lautstärke, Stumm, Ambilight und Fernbedienung testen. Erreichbarkeit nach längerem Standby prüfen. Angezeigte App-/Eingangsdaten und die vom konkreten Modell angebotenen Teilfunktionen bestätigen. Der Transporttest prüft Request-Optionen und Fehlerbehandlung, keine reale TLS-Verbindung zum Fernseher.
