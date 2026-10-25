# TV-Abfragen bei gemeinsamem Standby pausieren

Wenn der TV zuletzt Standby gemeldet oder einen Standby-Befehl über seine API
bestätigt hat und die Wohnzimmer-Shield aktuell im Standby ist, öffnet der
TV-Poller keinerlei Netzwerkverbindung zum TV: weder HTTPS noch HTTP/Ping/WOL.
Ein weiterer Aus-Befehl wird in diesem Zustand ohne Netzwerkzugriff quittiert.

Der Wohnzimmer-Poller speichert dazu seine bereits vorhandene ADB-Beobachtung
in `/data/shield/wohnzimmer-status.json`. Sie darf maximal 150 Sekunden alt sein,
muss online/standby melden und darf nicht in der Zukunft liegen. Fehlende,
fehlerhafte, offline oder veraltete Shield-Daten sperren die TV-Abfrage nicht.
Der letzte TV-Zustand liegt separat neben der Kopplungsdatei. Ein Fehler bei
einem tatsächlichen TV-Verbindungsversuch verwirft einen alten Standby-Zustand.

Wenn die Shield aufwacht, wird die TV-Abfrage beim nächsten regulären TV-Zyklus
wieder zugelassen. Ein expliziter TV-Ein-Befehl wird auch während des Ruhemodus
ausgeführt und hebt die alte Standby-Annahme auf. Die vorhandene Fehlerpause bei
wiederholten Netzwerkfehlern gilt danach weiterhin. HDMI-Umschaltung bleibt aus.

Die Visu zeigt „Abfrage pausiert“ und „Standby (zuletzt gemeldet)“. Die Zeitangabe
bezeichnet dann die letzte Statusmeldung an Symcon, keine neue Messung am TV.
Es wird kein frischer TV-Onlinezustand behauptet. Wird der TV separat mit seiner
Fernbedienung eingeschaltet, während die Shield im Standby bleibt, erkennt dieser
bewusst abfragefreie Modus das erst wieder nach einer Freigabe (Shield wach oder
expliziter TV-Ein-Befehl).

Installation: Hausstatus-Modul von main aktualisieren. Auf dem Zima die
vollständigen `poll.js` und `philips-tv.js` in `/data/shield` ersetzen. Der bisherige
Update-Befehl lädt diese Dateien sowie `cast-media.js`; er kann unverändert erneut
ausgeführt werden. Bestehende Flows bleiben bestehen. Die Dateien müssen beide
aktuell sein, damit der TV-Poller den gespeicherten Shield-Status findet.
