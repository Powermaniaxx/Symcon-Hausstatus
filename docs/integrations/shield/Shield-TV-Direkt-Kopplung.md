# Shield-Zustand an die neue Philips-TV-Instanz koppeln

1. In der zentralen Hausstatus-Instanz unter Wohnzimmer die **Direkte Philips-TV-Instanz** auswählen und Änderungen übernehmen. Die TV-Instanz muss bereits gekoppelt und aktiviert sein.
2. Den Inhalt von [ShieldWohnzimmer-TV-Kopplung.php](ShieldWohnzimmer-TV-Kopplung.php) in das **bestehende Kopplungsskript** in Symcon übernehmen. Dadurch bleiben Skript-ID und zugehörige Ereignis-/Cache-Objekte erhalten.
3. Oben im Skript die Hausstatus-Hauptinstanz `$MASTER_ID` prüfen. Für das ursprüngliche System ist sie `52627`; bei anderen Anwendern deren eigene ID eintragen. `$TV_INSTANCE_ID = 0` verwendet automatisch die im Hausstatus ausgewählte TV-Instanz. Eine ausdrücklich gesetzte Instanz-ID überschreibt diese Auswahl.
4. Einmal manuell ausführen. Das Skript richtet sein Ereignis ein und speichert den aktuellen gültigen Shield-Zustand. Dabei wird kein TV-Befehl gesendet.
5. Erst einen anschließenden Wechsel der Shield von `awake` nach `standby` bzw. zurück prüfen.

Auslöser bleibt `ShieldUpdated` der Hauptinstanz, weil dieser Zeitstempel den vollständig empfangenen Shield-Status markiert. `ShieldOnline` und `ShieldPower` sind die anderen Eingangsvariablen. Die TV-Bedienung geht an die neue Boolean-Aktionsvariable `Power` unter der Philips-TV-Instanz. Ihre numerische Variablen-ID wird anhand des Idents ermittelt und muss nicht von Hand eingetragen werden.

Der TV-Status löst diese Kopplung nicht aus. Bei Netzwerkfehlern, unbekanntem, veraltetem oder offensichtlich zukünftigem Shield-Status wird nicht geschaltet und der letzte gültige Zustand bleibt erhalten. Ein unveränderter Shield-Zustand sendet keinen weiteren Befehl. Erst nach erfolgreichem Aktionsaufruf wird der gespeicherte Übergang aktualisiert; dies bestätigt den Versand, nicht die Ausführung am Gerät.

Das Skript muss im bestehenden Symcon-Skript ersetzt werden. Keine zweite Kopplung parallel anlegen. Das Skript benötigt für die Shield-Statusquelle Hausstatus; die eigenständige Philips-TV-Bibliothek benötigt diesen Helfer nicht.
