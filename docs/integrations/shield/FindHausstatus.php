<?php
declare(strict_types=1);
// Vollständiges, ausschließlich lesendes Symcon-Konsolenskript.
foreach (IPS_GetInstanceListByModuleID('{9E33E109-4881-4E78-9906-38CAC2F1E210}') as $id) {
    $source = (int)IPS_GetProperty($id, 'ConfigSource');
    echo $id . ' | ' . IPS_GetName($id) . ' | View=' . IPS_GetProperty($id, 'View')
        . ' | ' . ($source === 0 ? 'ZENTRAL' : 'Quelle=' . $source) . PHP_EOL;
}
