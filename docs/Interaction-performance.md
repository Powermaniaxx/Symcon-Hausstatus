# Kachelreaktion ab 0.109

Die Prüfung von RequestAction, Frontend und Aktualisierung zeigte drei konkrete Mehrarbeiten:

- Vor der Befehlsbestätigung wurde der vollständige BaseState inklusive anderer Kachelbereiche aufgebaut, während die Bedienung gesperrt blieb.
- Nach 1,2 Sekunden ohne Bestätigung forderte die Oberfläche zusätzlich FreshState an und verwarf dabei auch Archiv-Caches.
- Jedes geänderte Variablenereignis baute unmittelbar einen vollständigen Zustand auf; mehrere Rückmeldungen eines Schaltvorgangs vervielfachten diese Arbeit.

Die normale Befehlsantwort enthält jetzt zuerst nur die Bestätigung des abgeschlossenen Geräteaufrufs. Die Zustandsberechnung folgt ereignisgesteuert über einen einmaligen 100-ms-Timer. Während dieser aussteht, starten weitere Ereignisse keinen neuen Timer und verschieben seine Frist nicht. Die Oberfläche sperrt vorhandene Bedienelemente vor dem Senden, ohne zuvor die komplette Ansicht neu aufzubauen.

Command-Anfragen enthalten eine zufällige Kennung. Nur die auslösende Browserkachel wertet die zugehörige Bestätigung oder Fehlermeldung als Antwort auf ihre Bedienung. Der bestehende RoomValue-Transport und alle Geräteprüfungen werden weiterverwendet. Bestehende PHP-Aufrufer können ihre bisherigen Aktionen weiterhin unverändert aufrufen.

Für verzögerte Geräterückmeldungen liest die auslösende sichtbare Kachel nach 400, 1500 und 3500 ms erneut. Diese begrenzten Lesevorgänge erhalten Archiv-Caches, senden keinen Gerätebefehl und zeigen keinen erfundenen Schaltzustand. Eine neue Bedienung ersetzt ausstehende Nachlesevorgänge; beim Verbergen der Seite werden sie entfernt. Manuelles Aktualisieren verwirft Archiv-Caches weiterhin ausdrücklich. Eingebettete Raumseiten behalten ihre korrelierten Antworten und lesen nach einem Befehl ebenfalls begrenzt nach; ihre vorhandene 30-Sekunden-Abfrage bleibt bestehen.

Ohne lebenden Viewer wird kein Aktualisierungstimer gestartet. Der Timer deaktiviert sich vor jeder Zustandsberechnung selbst. Die vorhandene 75-Sekunden-Viewer-Gültigkeit und der Watchdog bleiben bestehen; es kommt keine dauerhafte Hintergrundabfrage hinzu.

Türfreigabe, Bestätigungsdialog, Tokenprüfung, Ablaufzeit und Geräteaktionswege bleiben erhalten. Eine Befehlsbestätigung bedeutet weiterhin „Befehl übergeben“, nicht „Hardwarezustand bestätigt“.

Prüfung: `node tests/interaction-performance.js`, `php tests/interaction-performance.php`, vorhandene PHP-/Frontend-Regressionstests und PHP-Syntaxprüfung. Die Tests verwenden isolierte SDK-/Browser-Fixtures ohne reale Geräte. Eine Zeitmessung am produktiven Symcon und an realer Hardware steht noch aus; es wird keine gemessene Geschwindigkeitssteigerung behauptet.

Im breiteren Testlauf bestehen zwei bereits auf unverändertem `main` reproduzierte Fehler: `homepage-parts.js` fehlt ein Mock für `upsSection`, und `laundry.php` vergleicht einen aus JSON gelesenen Cachewert typstreng mit dem ursprünglichen Zahlenwert. `shield-rpc.js` benötigt einen lokalen Testserver, dessen Socket-Zugriff in dieser Umgebung gesperrt ist. Diese unabhängigen Probleme wurden durch die Performanceänderung nicht behoben.
