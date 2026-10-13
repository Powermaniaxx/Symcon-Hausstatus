<?php
declare(strict_types=1);

// Isolated regression tests. No Symcon connection and no real device actions.
class IPSModuleStrict
{
    public int $InstanceID = 100;
    public function Create(): void {}
    public function __call(string $name, array $args): mixed
    {
        if (str_starts_with($name, 'RegisterProperty')) {
            $GLOBALS['properties'][$this->InstanceID][$args[0]] = $args[1];
        }
        return null;
    }
    public function ReadPropertyInteger(string $name): int { return (int)IPS_GetProperty($this->InstanceID, $name); }
    public function ReadPropertyString(string $name): string { return (string)IPS_GetProperty($this->InstanceID, $name); }
    public function ReadPropertyBoolean(string $name): bool { return (bool)IPS_GetProperty($this->InstanceID, $name); }
}
function IPS_GetProperty(int $id, string $name): mixed { return $GLOBALS['properties'][$id][$name]; }
function IPS_VariableExists(int $id): bool { return isset($GLOBALS['variables'][$id]); }
function IPS_ObjectExists(int $id): bool { return IPS_VariableExists($id); }
function IPS_InstanceExists(int $id): bool { return isset($GLOBALS['properties'][$id]); }
function IPS_GetInstance(int $id): array { return ['ModuleInfo' => ['ModuleID' => '{9E33E109-4881-4E78-9906-38CAC2F1E210}']]; }
function IPS_GetVariable(int $id): array { return $GLOBALS['variables'][$id]; }
function IPS_GetObject(int $id): array { return ['ObjectIdent' => '', 'ObjectType' => 2]; }
function IPS_GetParent(int $id): int { return 0; }
function IPS_GetName(int $id): string { return 'Wohnungsprofil'; }
function IPS_GetVariablePresentation(int $id): array { return $GLOBALS['presentation']; }
function IPS_VariableProfileExists(string $name): bool { return $name === 'Heating'; }
function IPS_GetVariableProfile(string $name): array { return ['Associations' => $GLOBALS['options']]; }
function GetValue(int $id): mixed { return $GLOBALS['variables'][$id]['raw']; }
function GetValueFormatted(int $id): string { return 'Komfort'; }
function RequestAction(int $id, mixed $value): bool { $GLOBALS['actions'][] = [$id, $value]; return true; }
function IPS_SemaphoreEnter(string $key, int $timeout): bool { return true; }
function IPS_SemaphoreLeave(string $key): void {}

require __DIR__ . '/../HausstatusBedienung/module.php';
function callPrivate(object $instance, string $method, mixed ...$args): mixed
{
    return (new ReflectionMethod($instance, $method))->invoke($instance, ...$args);
}
function check(bool $ok, string $message): void
{
    if (!$ok) { throw new RuntimeException($message); }
}
function rejected(callable $action): void
{
    $before = count($GLOBALS['actions']);
    try { $action(); } catch (RuntimeException $e) {
        check(count($GLOBALS['actions']) === $before, 'Rejected command reached RequestAction');
        return;
    }
    throw new RuntimeException('Expected command rejection');
}

$GLOBALS['actions'] = [];
$GLOBALS['options'] = [['Value' => 0, 'Name' => 'Absenkung'], ['Value' => 1, 'Name' => 'Komfort']];
$GLOBALS['presentation'] = [];
$GLOBALS['variables'][31603] = [
    'VariableType' => 1, 'VariableProfile' => 'Heating', 'VariableCustomProfile' => '',
    'VariableAction' => 500, 'VariableCustomAction' => 0, 'raw' => 1
];
$module = new HausstatusBedienung();
$module->Create();
$p = &$GLOBALS['properties'][100];
$p['RoomEntries'] = '[]';
$p['Rooms'] = '[]';
check($p['HeatingProfile'] === 31603, 'New instance default');
$p['HeatingProfile'] = 0;
check(callPrivate($module, 'ConfigInteger', 'HeatingProfile') === 31603, 'Existing empty configuration fallback');

foreach ([9, 12] as $view) {
    $p['View'] = $view;
    $item = callPrivate($module, 'HeatingProfileItem');
    check($item['id'] === 31603 && $item['value']['text'] === 'Komfort', 'Real formatted profile value');
    check($item['control']['kind'] === 'enum' && count($item['control']['options']) === 2, 'Dynamic profile options');
    callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => 0]);
    check(end($GLOBALS['actions']) === [31603, 0], 'Existing action and integer value');
    rejected(fn() => callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => 99]));
    rejected(fn() => callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => '0']));
    rejected(fn() => callPrivate($module, 'SetRoomValue', ['id' => 77777, 'value' => 0]));
}
check(callPrivate($module, 'RoomSections') === [], 'No artificial room for apartment profile');
check(in_array('HeatingProfile', callPrivate($module, 'SourceNames'), true), 'Room view subscribes to profile');

// Exercise the real inline-page guard; state generation is unrelated to this action test.
class ProfilePageHarness
{
    use HausstatusPageSupport;
    public int $InstanceID = 100;
    public array $reply = [];
    public function __construct(private object $module) {}
    public function ReadPropertyInteger(string $name): int { return 12; }
    private function ConfigInteger(string $name): int { return callPrivate($this->module, 'ConfigInteger', $name); }
    private function SetRoomValue(mixed $payload): void { callPrivate($this->module, 'SetRoomValue', $payload); }
    private function State(): array { return ['View' => 12]; }
    public function __call(string $name, array $args): mixed
    {
        if ($name === 'UpdateVisualizationValue') { $this->reply = json_decode($args[0], true, 512, JSON_THROW_ON_ERROR); }
        return null;
    }
}
$page = new ProfilePageHarness($module);
$request = ['request' => 'page-profile-test', 'view' => 12, 'room' => '', 'value' => ['id' => 31603, 'value' => 0]];
callPrivate($page, 'HandlePageRequest', 'PageValue', json_encode($request));
check(!isset($page->reply['error']) && end($GLOBALS['actions']) === [31603, 0], 'Overview profile action allowed');
$before = count($GLOBALS['actions']);
$request['value'] = ['id' => 77777, 'value' => 0];
callPrivate($page, 'HandlePageRequest', 'PageValue', $request);
check(isset($page->reply['error']) && count($GLOBALS['actions']) === $before, 'Overview blocks unrelated room action');
$request['value'] = ['id' => 31603, 'value' => 99];
callPrivate($page, 'HandlePageRequest', 'PageValue', $request);
check(isset($page->reply['error']) && count($GLOBALS['actions']) === $before, 'Overview still validates profile option');

foreach ([0, 1] as $action) {
    $GLOBALS['variables'][31603]['VariableCustomAction'] = $action;
    $GLOBALS['variables'][31603]['VariableAction'] = $action === 0 ? 0 : 500;
    $item = callPrivate($module, 'HeatingProfileItem');
    check($item['control'] === null && str_contains($item['note'], 'Statusanzeige'), 'No/disabled action stays read-only');
    rejected(fn() => callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => 0]));
}
$GLOBALS['variables'][31603]['VariableCustomAction'] = 600;
$p['RoomControlsEnabled'] = false;
check(callPrivate($module, 'HeatingProfileItem')['control'] === null, 'Global room controls flag');
rejected(fn() => callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => 0]));
$p['RoomControlsEnabled'] = true;
$GLOBALS['options'] = [];
check(callPrivate($module, 'HeatingProfileItem')['control'] === null, 'No options must not become a slider or native control');

// Modern string enumeration uses typed values from the presentation.
$GLOBALS['variables'][31603]['VariableType'] = 3;
$GLOBALS['variables'][31603]['raw'] = 'comfort';
$GLOBALS['presentation'] = ['PRESENTATION' => '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}',
    'OPTIONS' => json_encode([['Value' => 'eco', 'Caption' => 'Sparen'], ['Value' => 'comfort', 'Caption' => 'Komfort']])];
check(callPrivate($module, 'HeatingProfileItem')['control']['options'][0]['value'] === 'eco', 'String presentation options');
callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => 'eco']);
check(end($GLOBALS['actions']) === [31603, 'eco'], 'Custom action receives typed string');
rejected(fn() => callPrivate($module, 'SetRoomValue', ['id' => 31603, 'value' => 0]));

$p['HeatingProfile'] = 40000;
check(callPrivate($module, 'HeatingProfileItem')['id'] === 40000, 'Explicit configured source preserved');
check(str_contains(callPrivate($module, 'HeatingProfileItem')['note'], '40000'), 'Missing source clearly identified');
$p['HeatingProfile'] = 0;
unset($GLOBALS['variables'][31603]);
$item = callPrivate($module, 'HeatingProfileItem');
check($item['value']['text'] === 'Quelle fehlt' && str_contains($item['note'], '31603'), 'Missing default source');

$p['View'] = 26;
check(callPrivate($module, 'HeatingProfileItem') === null, 'Sophie view remains limited to door and alarm');
$p['View'] = 7;
check(callPrivate($module, 'HeatingProfileItem') === null, 'No global profile in PV view');

// Shared configuration is read from the master, including the fallback.
$GLOBALS['properties'][200] = $p;
$GLOBALS['properties'][200]['ConfigSource'] = 0;
$GLOBALS['properties'][200]['HeatingProfile'] = 40000;
$p['ConfigSource'] = 200;
check(callPrivate($module, 'ConfigInteger', 'HeatingProfile') === 40000, 'Inherited configured source');
$GLOBALS['properties'][200]['HeatingProfile'] = 0;
check(callPrivate($module, 'ConfigInteger', 'HeatingProfile') === 31603, 'Inherited default source');

echo "Heating profile regression checks passed (simulated Symcon only).\n";
