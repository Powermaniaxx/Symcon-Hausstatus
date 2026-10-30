# Shield-Zustand an die neue Philips-TV-Instanz koppeln

1. In der zentralen Hausstatus-Instanz unter Wohnzimmer die **Direkte Philips-TV-Instanz** auswählen und Änderungen übernehmen. Die TV-Instanz muss bereits gekoppelt und aktiviert sein.
2. Den Inhalt von [ShieldWohnzimmer-TV-Kopplung.php](ShieldWohnzimmer-TV-Kopplung.php) in das **bestehende Kopplungsskript** in Symcon übernehmen. Dadurch bleiben Skript-ID und zugehörige Ereignis-/Cache-Objekte erhalten.
3. Oben im Skript die Hausstatus-Hauptinstanz `$MASTER_ID` prüfen. Für das ursprüngliche System ist sie `52627`; bei anderen Anwendern deren eigene ID eintragen. `$TV_INSTANCE_ID = 0` verwendet automatisch die im Hausstatus ausgewählte TV-Instanz. Eine ausdrücklich gesetzte Instanz-ID überschreibt diese Auswahl.
4. Einmal manuell ausführen. Das Skript richtet sein Ereignis ein und speichert den aktuellen gültigen Shield-Zustand. Dabei wird kein TV-Befehl gesendet.
5. Erst einen anschließenden Wechsel der Shield von `awake` nach `standby` bzw. zurück prüfen.

Die Shield-Quelle wird oben über `$SHIELD_SOURCE_ID` eingestellt. Im vorbereiteten Skript ist die vom Nutzer genannte **26475** eingetragen. Bei anderen Installationen die eigene Shield-Instanz angeben; `0` verwendet die alten Statuswerte unter der Hausstatus-Hauptinstanz. Eine bekannte Statusvariable oder ein Link kann ebenfalls angegeben werden. Die Quelle wird nicht stillschweigend durch eine andere ersetzt.

Das Skript ermittelt Online-, Betriebszustands- und Zeitstempelvariable anhand bekannter Idents und prüft ihre Typen. Es unterstützt `ShieldOnline`/`ShieldPower`/`ShieldUpdated` sowie übliche direkte Modulvariablen wie `Online`, `PowerState` bzw. `Power` und `Updated`. Power kann ein bestätigter Boolean oder ein bekannter String wie `awake`/`standby` bzw. `On`/`Off` sein. Ein unbekannter Wert bedeutet niemals automatisch Aus. Fehlt eine sichere Zuordnung, werden nur Namen, IDs, Idents und Typen der verfügbaren Variablen ausgegeben; der TV wird nicht geschaltet.

Auslöser ist der ermittelte Statuszeitstempel (bisher `ShieldUpdated`). Die Statusdaten dürfen höchstens **150 Sekunden** alt sein, passend zum Hausstatus bei 60-Sekunden-Abfragen. Ein fehlender echter Zeitstempel wird nicht durch die aktuelle Uhrzeit ersetzt. Die TV-Bedienung geht weiterhin an die neue Boolean-Aktionsvariable `Power` unter der ausgewählten Philips-TV-Instanz. Ihre numerische ID wird automatisch ermittelt.

Beim manuellen Start zeigt das Skript Quelle, verwendete Variablen-IDs, Werte und Alter der Statusmeldung. Ist der Shield-Status ungültig, nennt es den jeweiligen Grund. Pausiert die bisherige Shield-Abfrage bei geschlossenem WebFront, muss sie zuerst wieder einen aktuellen Status liefern. Das bloße Erhöhen der Zeitgrenze ersetzt keine laufende Statusquelle.

Der TV-Status löst diese Kopplung nicht aus. Bei Netzwerkfehlern, unbekanntem, veraltetem oder offensichtlich zukünftigem Shield-Status wird nicht geschaltet und der letzte gültige Zustand bleibt erhalten. Ein unveränderter Shield-Zustand sendet keinen weiteren Befehl. Erst nach erfolgreichem Aktionsaufruf wird der gespeicherte Übergang aktualisiert; dies bestätigt den Versand, nicht die Ausführung am Gerät.

Das Skript muss im bestehenden Symcon-Skript ersetzt werden. Keine zweite Kopplung parallel anlegen. Das Skript benötigt für die Shield-Statusquelle Hausstatus; die eigenständige Philips-TV-Bibliothek benötigt diesen Helfer nicht.
