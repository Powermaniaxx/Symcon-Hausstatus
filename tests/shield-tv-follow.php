<?php
declare(strict_types=1);
$values = [11 => true, 12 => 'awake', 13 => time()];
$objects = ['ShieldOnline' => 11, 'ShieldPower' => 12, 'ShieldUpdated' => 13];
$actions = [];
$created = 100;
function IPS_ScriptExists($id) { return $id === 42; }
function IPS_InstanceExists($id) { return $id === 52627; }
function IPS_GetInstance($id) { return ['ModuleInfo' => ['ModuleID' => '{9E33E109-4881-4E78-9906-38CAC2F1E210}']]; }
function IPS_GetConfiguration($id) { return '{"ConfigSource":0,"PhilipsTvEnabled":true}'; }
function IPS_GetObjectIDByIdent($ident, $parent) { return $GLOBALS['objects'][$ident] ?? false; }
function IPS_VariableExists($id) { return array_key_exists($id, $GLOBALS['values']); }
function IPS_GetVariable($id) { return ['VariableType' => $id === 11 ? 0 : ($id === 13 ? 1 : 3)]; }
function IPS_SemaphoreEnter(...$args) { return true; }
function IPS_SemaphoreLeave(...$args) {}
function IPS_CreateVariable($type) { $id = ++$GLOBALS['created']; $GLOBALS['values'][$id] = ''; return $id; }
function IPS_CreateEvent($type) { return ++$GLOBALS['created']; }
function IPS_SetParent(...$args) {}
function IPS_SetIdent($id, $ident) { $GLOBALS['objects'][$ident] = $id; }
function IPS_SetName(...$args) {}
function IPS_SetHidden(...$args) {}
function IPS_EventExists($id) { return $id === ($GLOBALS['objects']['ShieldTvFollowEvent'] ?? false); }
function IPS_GetEvent($id) { return ['EventType' => 0]; }
function IPS_SetEventTrigger($id, $type, $variable) { check($type === 1 && $variable === 13, 'trigger must observe committed status'); }
function IPS_SetEventAction(...$args) {}
function IPS_SetEventActive(...$args) {}
function GetValue($id) { return $GLOBALS['values'][$id]; }
function SetValue($id, $value) { $GLOBALS['values'][$id] = $value; }
function IPS_RequestAction($id, $ident, $value) { check($id === 52627 && $ident === 'ShieldPower_philips', 'wrong target'); $GLOBALS['actions'][] = $value; }
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
function runFollow($sender = 'Variable') {
    $_IPS = ['SELF' => 42, 'SENDER' => $sender];
    ob_start();
    try { include __DIR__ . '/../docs/integrations/shield/ShieldWohnzimmer-TV-Kopplung.php'; }
    finally { ob_end_clean(); }
}
runFollow('Execute');
check($actions === [true] && $created === 102, 'initial setup/wake failed');
runFollow();
check($actions === [true], 'unchanged poll repeated command');
$values[12] = 'standby';
runFollow();
check($actions === [true, false], 'standby did not turn TV off');
$values[11] = false;
$values[12] = 'unknown';
runFollow();
check($actions === [true, false], 'offline sent a command');
$values[11] = true;
$values[12] = 'awake';
runFollow();
check($actions === [true, false, true], 'recovery did not synchronize');
$values[13] = time() - 120;
$values[12] = 'standby';
runFollow();
check($actions === [true, false, true], 'stale status switched TV');
$values[13] = time();
runFollow('Execute');
check($created === 102 && $actions === [true, false, true, false], 'setup was not idempotent');
echo "Shield/TV follow setup, transitions, recovery and stale/offline guards: passed\n";
