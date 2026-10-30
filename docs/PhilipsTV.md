# Philips-TV-Anbindung im Hausstatus

Das Philips-TV-Modul wird als eigene Bibliothek installiert. Die aktuelle Dokumentation wird im separaten Repository gepflegt:

- [Übersicht und Installation](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon#readme)
- [Voraussetzungen und Gerätekompatibilität](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon/blob/main/docs/Voraussetzungen.md)
- [PIN-Kopplung, Einrichtung und Diagnose](https://github.com/Powermaniaxx/Philips-TV-Modul-for-Symcon/blob/main/docs/Einrichtung.md)

Das Modul benötigt JointSpace API 6 über HTTPS mit Digest-Anmeldung. Es bietet derzeit keine markenübergreifende TV-Anbindung. Das optionale Steuersignal darf dagegen z. B. vom AVR, von einer Shield oder einer eigenen Betriebszustandsvariable stammen. Standardmäßig setzt es nur die Abfragen fort. Ab Philips-TV-Modul 1.2.0 können zusätzlich TV-Einschalten bei aktivem Signal und TV-Standby bei inaktivem Signal unabhängig voneinander aktiviert werden; beide Optionen sind zunächst aus.

Für die Anzeige in der Haussteuerung Hausstatus ab 0.132 verwenden. In der zentralen Hausstatus-Instanz unter **Wohnzimmer → Direkte Philips-TV-Instanz** die zuvor gekoppelte TV-Instanz auswählen. Kacheln mit dieser Konfigurationsquelle übernehmen die Auswahl. Ohne Instanzauswahl bleibt der bisherige Philips-Node-RED-Weg erhalten.

Für eine separate zustandsabhängige Shield/TV-Kopplung die [neutrale Skriptvorlage und Anleitung](integrations/shield/Shield-TV-Direkt-Kopplung.md) verwenden. Öffentliche Vorlagen enthalten keine persönlichen Objekt-IDs; diese werden in der eigenen Installation eingetragen.
