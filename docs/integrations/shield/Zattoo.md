# Zattoo: Sender und Sendung

Die Android-Mediensitzung der getesteten Zattoo-Version liefert keinen Titel.
Die Cast-Schnittstelle der Wohnzimmer-Shield liefert dagegen den Sender über
`media.customData.watchInfo.cid`, die Sendung über `media.metadata.title` und
weitere Angaben über `media.metadata.subtitle`. Am Gerät bestätigt: zdfneo,
Bares für Rares, Folge 2156.

Symcon-Modul aktualisieren und Änderungen beim Master 52627 übernehmen. Im
Node-RED-Datenordner `/data/shield` die vollständige `cast-media.js` hinzufügen
und `poll.js` ersetzen. Vorhandene Flows bleiben bestehen; keine neuen Pakete.
Die Abfrage gilt für alle drei Shields, wenn Zattoo im Vordergrund und die Shield
wach ist. Maximal zwei zusätzliche Sekunden. Keine Cast-Schaltbefehle.

Die ausführliche Anzeige bekommt Sender, Sendung und Details. Susi bleibt kompakt.
Neue Statusvariablen: ShieldChannel/ShieldSubtitle sowie die entsprechenden
ShieldDressing- und ShieldBedroom-Variablen. Unbekannte Senderkennungen werden
unverändert angezeigt. Cast-Ausfälle ändern weder ADB-Online- noch Power-Status;
Sender und Details werden dann geleert. Standby, App-Wechsel und der bestehende
60-Sekunden-Watchdog entfernen alte Senderdaten.

Die lokalen Prüfungen testen Statusabfragen ohne Wiedergabebefehle, fragmentierte
Cast-Antworten, Senderfelder, Fehlerbehandlung und die kompakte Darstellung.
