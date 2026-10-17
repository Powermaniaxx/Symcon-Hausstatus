# Master-Layout einmal einstellen – Sven und Susi übernehmen es

## Feste Zuordnung

| Rolle | Tile-Visualisierung | Startkategorie |
|---|---:|---:|
| Master (einzige Layoutvorlage) | 12231 | 55503 |
| Sven | 34154 | 13714 |
| Susi | 44331 | 23271 |

**Sophie 46811 wird nicht berücksichtigt.**

## Status

Die bestehenden Gitter enthalten unterschiedliche IDs und ältere Layout-Referenzen.
Eine 1:1-Kopie der JSON-Gitterkonfiguration wäre deshalb falsch.
Die Skripte vergleichen das jeweils aktuelle Master-Gitter mit den über
`MirrorCat<Master-ID>` und `MirrorLink<Master-ID>` eindeutig zugeordneten Kacheln.

Übernommen werden für **Desktop, Tablet und Telefon**, jeweils **Hoch- und
Querformat**, die Rasterbreite, die Standardgrößen und die individuellen
Kachelgrößen und Positionen der eindeutig gemappten Objekte. Eigene oder nicht
zugeordnete Zielkacheln und `individualConfig` bleiben unangetastet.
Es werden keine Instanzen oder Verknüpfungen erzeugt.

## Installation / Test

1. **Vollständiges Prüfs​kript** [Kachelgroessen-Sync-Pruefen.php](Kachelgroessen-Sync-Pruefen.php)
   in Symcon als neues PHP-Skript speichern und einmal ausführen.
   Das Skript verändert keine Kachel und meldet pro Ziel die zugeordneten
   und die noch fehlenden Kacheln.
2. **Nur wenn bei Sven und Susi keine Zielkacheln fehlen**, das vollständige
   [Kachelgroessen-Sync-Ausfuehren.php](Kachelgroessen-Sync-Ausfuehren.php)
   in ein zweites Symcon-PHP-Skript kopieren und einmal ausführen.
   **Vor jedem tatsächlichen Speichern** erzeugt es eine JSON-Sicherung von
   Master, Sven und Susi unter dem Verzeichnis
   `Hausstatus_Visu_Grid_Backups` neben dem PHP-Skript.
   Ohne erfolgreiche Sicherung findet **kein** Schreibzugriff statt.
3. Nach erfolgreichem Test das **aktive Synchronisierungsskript**
   mit einem zyklischen Symcon-Ereignis verbinden, beispielsweise alle 15
   Minuten. Beim unveränderten Layout schreibt es nichts; andernfalls
   werden nur Änderungen übernommen.

**Nicht** das alte Skript `Kachelvisualisierung-Spiegelung.php` zusätzlich
aktivieren: Es kann neue Susi-Instanzen oder andere doppelte
Präsentationsobjekte erzeugen. Die Layoutskripte legen selbst keine Objekte an.

Für die Layoutübertragung verwendet das Schreibskript
`VISU_SaveGridConfiguration($zielInstanzID, json_encode($grid))`.
Der vollständige Gitterinhalt bleibt erhalten, soweit die beabsichtigten
Master-Änderungen und die eindeutig gemappten Zielobjekte ihn nicht verändern.

Bei fehlenden Zielkacheln wird **abgebrochen**. Zuerst den Objektbaum
angleichen, danach den Prüflauf wiederholen.
