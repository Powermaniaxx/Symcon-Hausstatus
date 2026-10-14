<?php
declare(strict_types=1);

// Read-only Symcon fixtures: never connects to appliances or writes real variables.
class IPSModuleStrict
{
    public int $InstanceID = 100;
    public function Create(): void {}
    public function __call(string $name, array $args): mixed
    {
        if (str_starts_with($name, 'RegisterProperty')) { $GLOBALS['properties'][$this->InstanceID][$args[0]] = $args[1]; }
        return null;
    }
    public function ReadPropertyInteger(string $name): int { return (int)IPS_GetProperty($this->InstanceID, $name); }
    public function ReadPropertyString(string $name): string { return (string)IPS_GetProperty($this->InstanceID, $name); }
    public function ReadPropertyBoolean(string $name): bool { return (bool)IPS_GetProperty($this->InstanceID, $name); }
}
function IPS_GetProperty(int $id, string $name): mixed { return $GLOBALS['properties'][$id][$name]; }
function IPS_InstanceExists(int $id): bool { return isset($GLOBALS['properties'][$id]); }
function IPS_GetInstance(int $id): array { return ['ModuleInfo' => ['ModuleID' => '{9E33E109-4881-4E78-9906-38CAC2F1E210}']]; }
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['variables'][$id]); }
function IPS_GetVariable(int $id): array { return $GLOBALS['variables'][$id]; }
function GetValue(int $id): mixed { return $GLOBALS['variables'][$id]['raw']; }
function IPS_GetScriptList(): array { return array_keys($GLOBALS['scripts']); }
function IPS_GetObjectIDByIdent(string $ident, int $parent): int|false { return $GLOBALS['scripts'][$parent][$ident] ?? false; }
function IPS_GetChildrenIDs(int $parent): array { return array_values($GLOBALS['scripts'][$parent]); }
function IPS_EventExists(int $id): bool { return isset($GLOBALS['events'][$id]); }
function IPS_GetEvent(int $id): array { return $GLOBALS['events'][$id]; }
require __DIR__ . '/../HausstatusBedienung/module.php';
function invoke(object $instance, string $method): mixed { return (new ReflectionMethod($instance, $method))->invoke($instance); }
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }

$module = new HausstatusBedienung(); $module->Create();
$started = time() - 4200;
$GLOBALS['variables'] = [
    15161 => ['VariableType' => 2, 'raw' => 1.2],
    12920 => ['VariableType' => 1, 'raw' => 0],
    1001 => ['VariableType' => 0, 'raw' => true, 'VariableChanged' => $started, 'VariableUpdated' => time()],
    1002 => ['VariableType' => 0, 'raw' => false, 'VariableChanged' => $started]
];
$GLOBALS['scripts'] = [500 => ['WaschmaschineLaeuft' => 1001, 'event' => 2001], 501 => ['TrocknerLaeuft' => 1002, 'event' => 2002]];
$GLOBALS['events'] = [2001 => ['EventType' => 0, 'EventActive' => true, 'TriggerVariableID' => 15161],
    2002 => ['EventType' => 0, 'EventActive' => true, 'TriggerVariableID' => 12920]];
$data = invoke($module, 'LaundryState');
check(count($data['devices']) === 2, 'Both appliances must share one state');
check($data['devices'][0]['running'] === true, 'Heating pause must still show running');
check($data['devices'][0]['watts'] === 1.2, 'Power remains independent of running status');
check($data['devices'][0]['since'] === $started, 'Use VariableChanged, not VariableUpdated');
check($data['devices'][1]['running'] === false && $data['devices'][1]['since'] === null, 'Inactive device must not show previous start');
check(invoke($module, 'LaundrySourceIDs') === [15161, 1001, 12920, 1002], 'Subscribe to power AND detected state');

// An active duplicate is ambiguous; disabling that event restores unique discovery.
$GLOBALS['variables'][1003] = $GLOBALS['variables'][1001];
$GLOBALS['scripts'][502] = ['WaschmaschineLaeuft' => 1003, 'event' => 2003];
$GLOBALS['events'][2003] = $GLOBALS['events'][2001];
check(invoke($module, 'LaundryState')['devices'][0]['running'] === null, 'Ambiguous discovery must stay unknown');
$GLOBALS['events'][2003]['EventActive'] = false;
check(invoke($module, 'LaundryState')['devices'][0]['running'] === true, 'Disabled copy must not shadow live source');
$GLOBALS['events'][2003]['EventActive'] = true;
$GLOBALS['events'][2003]['TriggerVariableID'] = 999;
check(invoke($module, 'LaundryState')['devices'][0]['running'] === true, 'Unrelated power event must not match');

$GLOBALS['properties'][100]['WasherRunning'] = 1003;
$GLOBALS['variables'][1003]['raw'] = false;
check(invoke($module, 'LaundryState')['devices'][0]['running'] === false, 'Explicit selection takes precedence');
$GLOBALS['variables'][1003]['VariableType'] = 1;
check(invoke($module, 'LaundryState')['devices'][0]['running'] === null, 'Wrong type must not become false');
$GLOBALS['properties'][100]['WasherRunning'] = 1001;
$GLOBALS['variables'][1001]['VariableChanged'] = 0;
check(invoke($module, 'LaundryState')['devices'][0]['since'] === null, 'Unknown timestamp must not become 1970');
$GLOBALS['variables'][1001]['VariableChanged'] = time() + 999;
check(invoke($module, 'LaundryState')['devices'][0]['since'] === null, 'Future timestamp must not become a negative duration');
unset($GLOBALS['variables'][12920]);
check(invoke($module, 'LaundryState')['devices'][1]['watts'] === null, 'Missing power must not become zero');

$child = new HausstatusBedienung(); $child->InstanceID = 101; $child->Create();
$GLOBALS['properties'][101]['ConfigSource'] = 100; $GLOBALS['properties'][101]['View'] = 27;
$GLOBALS['variables'][1001]['VariableChanged'] = $started;
check(invoke($child, 'LaundryState')['devices'][0]['since'] === $started, 'Split tile must inherit master sources');
check(in_array('WasherPower', invoke($child, 'SourceNames'), true), 'Split view source registration');
$GLOBALS['properties'][101]['View'] = 26;
check(invoke($child, 'LaundryState') === null && invoke($child, 'LaundrySourceIDs') === [], 'Do not add appliances to unrelated views');
echo "Laundry regression checks passed (simulated Symcon only).\n";
