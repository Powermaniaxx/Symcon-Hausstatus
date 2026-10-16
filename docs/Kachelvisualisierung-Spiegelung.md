# Master, Sven und Susi spiegeln

`Kachelvisualisierung-Spiegelung.php` ersetzt das vollständige bisherige
Spiegelungsskript. In das bestehende Symcon-PHP-Skript kopieren, speichern und
einmal manuell ausführen. Bestehende Ereignisse bleiben am selben Skript erhalten.
Das alte Skript darf nicht zusätzlich weiterlaufen, weil es Susis Links wieder
auf die ausführlichen Originalansichten richten würde.

Quellkategorie bleibt 55503. Das ist die Kategorie der Master-Visualisierung;
die zentrale Hausstatus-Modulinstanz ist davon unabhängig weiterhin 52627.

Sven erhält Links auf die aktuelle freigegebene Master-Struktur einschließlich
der neuen Einzelkacheln. Susi erhält dieselbe Struktur, für Hausstatus-Kacheln
aber eigene Ansichten mit derselben zentralen Datenquelle und `ShieldCompact=true`.
Dadurch fehlen dort die ausführlichen Shield-Medieninformationen; die übrigen
Inhalte bleiben sichtbar. Die gemeinsame Darstellung und Quelle werden nicht
geändert. Sophies Kategorie und ihre eigenen Kacheln werden nicht synchronisiert.

Die vorhandenen Root-Kennungen MirrorVisualisierungSven und
MirrorVisualisierungSusi werden wiederverwendet. Es werden keine Objekte gelöscht.
Nur eigene veraltete MirrorLink-/MirrorCat-Objekte werden ausgeblendet; fremde
Kacheln bleiben bestehen. Automatische Aufrufe bleiben still, manuelles Ausführen
zeigt die Statistik. Vorhandene Grenzen und Prüfmodus sind weiterhin enthalten.

Susis zusätzliche Ansichten liegen in der ausgeblendeten technischen Kategorie
SVHSSusiMirrorViews neben der Master-Kategorie; sichtbar sind ihre Verknüpfungen
in Susis bisheriger Visu-Struktur. Erneutes Ausführen aktualisiert diese Ansichten
und erzeugt keine Duplikate.
