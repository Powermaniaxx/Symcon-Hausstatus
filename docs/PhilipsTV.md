# Philips-TV-Anbindung im Hausstatus

Das Philips-TV-Modul wird als eigene Bibliothek installiert. Die aktuelle Dokumentation wird im separaten Repository gepflegt:

- [Übersicht und Installation](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon#readme)
- [Voraussetzungen und Gerätekompatibilität](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon/blob/main/docs/Voraussetzungen.md)
- [PIN-Kopplung, Einrichtung und Diagnose](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon/blob/main/docs/Einrichtung.md)

Das Modul benötigt JointSpace API 6 über HTTPS mit Digest-Anmeldung. Es bietet derzeit keine markenübergreifende TV-Anbindung. Das optionale Aktivierungssignal darf dagegen z. B. vom AVR, von einer Shield oder einer eigenen Statusvariable stammen; es setzt nur die Abfragen fort.

Für die Anzeige in der Haussteuerung Hausstatus ab 0.132 verwenden. In der zentralen Hausstatus-Instanz unter **Wohnzimmer → Direkte Philips-TV-Instanz** die zuvor gekoppelte TV-Instanz auswählen. Kacheln mit dieser Konfigurationsquelle übernehmen die Auswahl. Ohne Instanzauswahl bleibt der bisherige Philips-Node-RED-Weg erhalten.

Für eine separate zustandsabhängige Shield/TV-Kopplung die [neutrale Skriptvorlage und Anleitung](integrations/shield/Shield-TV-Direkt-Kopplung.md) verwenden. Öffentliche Vorlagen enthalten keine persönlichen Objekt-IDs; diese werden in der eigenen Installation eingetragen.
