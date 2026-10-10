<?php
declare(strict_types=1);
require_once __DIR__ . '/RoomSupport.php';
require_once __DIR__ . '/ComfortSupport.php';
require_once __DIR__ . '/RainSupport.php';
require_once __DIR__ . '/NavigationSupport.php';
require_once __DIR__ . '/DisplaySupport.php';
require_once __DIR__ . '/NetworkSupport.php';
require_once __DIR__ . '/PageSupport.php';
require_once __DIR__ . '/HomepageSupport.php';

class HausstatusBedienung extends IPSModuleStrict
{
    use HausstatusRoomSupport;
    use HausstatusComfortSupport;
    use HausstatusRainSupport;
    use HausstatusNavigationSupport;
    use HausstatusDisplaySupport;
    use HausstatusNetworkSupport;
    use HausstatusPageSupport;
    use HausstatusHomepageSupport;
    private const SOURCES = [
        
        'NetworkConnection' => 0, 'NetworkDownload' => 0, 'NetworkUpload' => 0, 'NetworkDownloadUsage' => 0, 'NetworkUploadUsage' => 0, 'NetworkActiveDevices' => 0, 'NetworkDevices' => 0, 'NetworkUptime' => 0, 'NetworkModel' => 0, 'NetworkFirmware' => 0,
        'HeatingProfile' => 0, 'Presence' => 0, 'Lock' => 0, 'DoorContact' => 0,
        'DoorControl' => 0, 'DoorPermission' => 0, 'Alarm' => 0, 'BatteryWarnings' => 0,
        'LightState' => 0, 'Brightness' => 0, 'CinemaState' => 0,
        'CinemaControl' => 0, 'CinemaSource' => 0, 'CinemaVolume' => 0, 'HeosSelection' => 0, 'HeosRadio' => 0, 'HeosNAS' => 0, 'HeosStatus' => 0, 'PVPower' => 0, 'PVEnergy' => 0,
        'Weather' => 0, 'Wind' => 0, 'Rain' => 0, 'Warning' => 0,
        'Sunrise' => 0, 'Sunset' => 0,
        'DoorOpened' => 0, 'DoorClosed' => 0,
        'AwningPosition' => 0, 'AwningAuto' => 0, 'AwningStatus' => 0,
        'RoofPosition' => 0, 'RoofAuto' => 0, 'RoofNight' => 0, 'RoofStatus' => 0
    ];

    public function Create(): void
    {
        parent::Create();
        foreach (self::SOURCES as $name => $id) {
            $this->RegisterPropertyInteger($name, $id);
        }
        $this->RegisterPropertyInteger('View', 0);
        $this->RegisterPropertyBoolean('SeparateHomepageTiles', false);
        $this->RegisterPropertyInteger('HomepageCategory', 0);
        $this->RegisterAttributeString('HomepageLayout', '');
        $this->RegisterPropertyBoolean('ActiveView', true);
        $this->RegisterPropertyBoolean('SeparateDetails', false);
        $this->RegisterPropertyBoolean('OutdoorEnabled', true);
        $this->RegisterPropertyInteger('ConfigSource', 0);
        $this->RegisterPropertyString('RoomFilter', '');
        $this->RegisterPropertyString('DiningRoom', 'Wohnzimmer');
        $this->RegisterPropertyString('PVRoom', 'PV-Anlage');
        $this->RegisterPropertyString('DisplayTexts', $this->DefaultDisplayTexts());
        $this->RegisterPropertyString('WirelessNetworks', '[]');
        $this->RegisterPropertyString('RoomEntries', $this->GetRoomDefaults());
        $this->RegisterPropertyBoolean('RoomControlsEnabled', true);
        $this->RegisterPropertyInteger('DiningInstance', 0);
        $this->RegisterPropertyInteger('DiningState', 0);
        $this->RegisterPropertyInteger('DiningBrightness', 0);
        $this->RegisterPropertyBoolean('DiningEnabled', true);
        foreach (['HeaterInstance', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'] as $name) { $this->RegisterPropertyInteger($name, 0); }
        $this->RegisterPropertyString('HeaterTitle', 'Zusatzheizung Wohnzimmer');
        $this->RegisterPropertyBoolean('HeaterEnabled', true);
        $this->RegisterPropertyInteger('Raining', 0);
        $this->RegisterPropertyInteger('RainArchive', 0);
        $this->RegisterPropertyBoolean('RainLogging', true);
        $this->RegisterPropertyInteger('MotionArchive', 0);
        $this->RegisterPropertyBoolean('MotionLogging', true);
        $this->RegisterPropertyString('MotionSensors', '[]');
        $this->RegisterPropertyBoolean('DoorEnabled', false);
        $this->RegisterPropertyBoolean('LightEnabled', false);
        $this->RegisterPropertyBoolean('CinemaEnabled', false);
        $this->RegisterPropertyInteger('LightCommandScript', 0);
        $this->RegisterPropertyString('Rooms', '[]');
        $this->RegisterTimer('Refresh', 0, 'SVHS_Refresh($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        if (!$this->ReadPropertyBoolean('ActiveView')) {
            $this->SetTimerInterval('Refresh', 0);
            $this->SetBuffer('DoorChallenges', '{}');
            foreach ($this->GetMessageList() as $id => $messages) {
                foreach ($messages as $message) { $this->UnregisterMessage($id, $message); }
            }
            $this->SetStatus(102);
            $this->SetSummary('Ansicht pausiert');
            return;
        }
        // Use fullscreen only when the installed SDK declares support for it.
        // Older installations and the homepage keep the existing HTML tile mode.
        $this->SetVisualizationType($this->HtmlVisualizationType());
        $this->SetBuffer('DoorChallenges', '{}');
        foreach ($this->GetMessageList() as $id => $messages) {
            foreach ($messages as $message) { $this->UnregisterMessage($id, $message); }
        }
        foreach ($this->GetReferenceList() as $id) { $this->UnregisterReference($id); }
        
        $this->SetBuffer('MotionCache', '');
        $this->SetBuffer('RainCache', '');
        $archive = $this->MotionArchive();
        if ($archive > 0) { $this->RegisterReference($archive); }
        if ($this->HasMotionView() && $archive > 0 && $this->ConfigBoolean('MotionLogging')) {
            foreach ($this->MotionSensors() as $sensor) {
                $id = $sensor['Variable'];
                if (IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0) {
                    try { AC_SetLoggingStatus($archive, $id, true); }
                    catch (Throwable $e) { $this->SendDebug('Bewegungsarchiv', $e->getMessage(), 0); }
                }
            }
        }
        $ids = [];
        if ($this->HasRainView()) {
            $rainID = $this->ConfigInteger('Raining');
            $ids[] = $rainID;
            $rainArchive = $this->RainArchive();
            if ($rainArchive > 0) { $this->RegisterReference($rainArchive); }
            if ($rainArchive > 0 && $this->ConfigBoolean('RainLogging') && IPS_VariableExists($rainID)
                && IPS_GetVariable($rainID)['VariableType'] === 0) {
                try { AC_SetLoggingStatus($rainArchive, $rainID, true); }
                catch (Throwable $e) { $this->SendDebug('Regenarchiv', $e->getMessage(), 0); }
            }
        }
        foreach ($this->SelectedRoomEntries() as $entry) { if ($entry['type'] === 2) { $ids[] = $entry['id']; } }
        if (in_array($this->ReadPropertyInteger('View'), [0, 5, 12, 17], true)) {
            try { foreach ($this->DiningSources() as $id) { if ($id > 0) { $ids[] = $id; } } } catch (Throwable $e) { /* Missing sources are explained in the tile. */ }
        }
        if ($this->ReadPropertyInteger('View') === 12) {
            foreach ($this->TemperatureSources() as $row) { $ids[] = $row['actualID']; }
        }
        if (in_array($this->ReadPropertyInteger('View'), [0, 14], true)) { foreach ($this->HeaterIDs() as $id) { if ($id > 0) { $ids[] = $id; } } }
        foreach ($this->SourceNames() as $name) { $ids[] = $this->ConfigInteger($name); }
        if (in_array($this->ReadPropertyInteger('View'), [0, 7, 15], true)) {
            foreach ($this->PVInverterSources() as $inverter) { $ids[] = $inverter['power']; $ids[] = $inverter['producing']; }
        }
        if ($this->HasTemperatureView()) {
            foreach ($this->Rooms() as $room) { $ids[] = $room['Variable']; }
        }
        if ($this->HasMotionView()) {
            foreach ($this->MotionSensors() as $sensor) { $ids[] = $sensor['Variable']; }
        }
        $source = $this->ReadPropertyInteger('ConfigSource');
        if ($source > 0 && IPS_InstanceExists($source)) { $this->RegisterReference($source); }
        if ($this->ReadPropertyInteger('View') === 13) {
            foreach ($this->WirelessNetworks() as $wifi) {
                foreach (['State', 'SSID', 'Devices'] as $name) { $ids[] = $wifi[$name]; }
                if ($wifi['QRCode'] > 0 && IPS_MediaExists($wifi['QRCode'])) { $this->RegisterReference($wifi['QRCode']); }
            }
        }
        foreach (array_unique($ids) as $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterMessage($id, VM_DELETE);
            }
        }
        $script = $this->ConfigInteger('LightCommandScript');
        if ($script > 0 && IPS_ScriptExists($script)) { $this->RegisterReference($script); }
        $this->HideTileMaximize();
        $this->OrganizeHomepageDetails();
        $this->OrganizeSeparateHomepage();
        foreach ([$this->PVDetailsTarget(), $this->RoomNavigationTarget()] as $target) {
            if ($target > 0) { $this->RegisterReference($target); }
        }
        // 0.33: Kein permanenter Refresh ohne aktive WebFront-Kachel.
        // Bei geoeffnetem WebFront bleiben Variablenaenderungen ereignisgesteuert.
        $this->SetTimerInterval('Refresh', 0);
        $this->SetStatus(102);
        $this->SetSummary($this->GetBuffer('HomepageLayoutError') ?: 'Hausstatus mit HTML-Bedienung');
        $this->SetBuffer('LastState', '');
        $this->Refresh();
        if ($this->ReadPropertyInteger('ConfigSource') === 0) {
            foreach (IPS_GetInstanceListByModuleID('{9E33E109-4881-4E78-9906-38CAC2F1E210}') as $child) {
                if ($child !== $this->InstanceID && IPS_GetProperty($child, 'ConfigSource') === $this->InstanceID) { IPS_ApplyChanges($child); }
            }

            // Child ApplyChanges calls may touch presentation objects after the split
            // homepage was built. Enforce the intended final state once more: when
            // separate homepage tiles are enabled, the combined master tile and every
            // direct presentation link to it must stay hidden.
            if ($this->ReadPropertyBoolean('SeparateHomepageTiles')) {
                IPS_SetHidden($this->InstanceID, true);
                foreach (IPS_GetObjectList() as $id) {
                    if (IPS_LinkExists($id) && IPS_GetLink($id)['TargetID'] === $this->InstanceID) {
                        IPS_SetHidden($id, true);
                    }
                }
            }
        }
    }

    // Ein Browser sendet alle 30 Sekunden ein Lebenszeichen.
    // Ohne Browser kommen nach spaetestens 75 Sekunden keine Neuberechnungen mehr.
    // Ein gemeinsamer Zeitstempel funktioniert auch mit mehreren geoeffneten Tabs.
    private function HasLiveViewers(): bool
    {
        $last = (int)$this->GetBuffer('LastViewerSeen');
        return $last > 0 && (time() - $last) <= 75;
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE || $Message === VM_DELETE) {
            if (!$this->HasLiveViewers()) { return; }
            if ($SenderID === $this->ConfigInteger('Raining')) { $this->SetBuffer('RainCache', ''); }
            foreach ($this->MotionSensors() as $sensor) {
                if ($sensor['Variable'] === $SenderID) { $this->SetBuffer('MotionCache', ''); break; }
            }
            $this->Refresh();
        }
    }

    public function Refresh(): void
    {
        if (!$this->HasLiveViewers()) { return; }
        $state = $this->BaseState();
        $encoded = json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($this->GetBuffer('LastState') !== $encoded) {
            $this->SetBuffer('LastState', $encoded);
            $this->UpdateVisualizationValue($encoded);
        }
    }

    public function GetConfigurationForm(): string
    {
        $form = json_decode((string)file_get_contents(__DIR__ . '/form.json'), true, 512, JSON_THROW_ON_ERROR);
        $fields = $this->SettingsFormFields($this->ReadPropertyInteger('ConfigSource'), $this->ReadPropertyInteger('View'));
        if ($this->ReadPropertyInteger('ConfigSource') === 0) {
            // Reconstruct read-only captions; only editable texts and their stable keys are saved.
            $fields['DisplayTexts'] = ['values' => $this->DisplayRows(), 'loadValuesFromConfiguration' => false];
        }
        $this->ApplyFormFields($form['elements'], $fields);
        return json_encode($form, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private function ApplyFormFields(array &$elements, array $fields): void
    {
        foreach ($elements as &$element) {
            $name = $element['name'] ?? '';
            if (isset($fields[$name])) { $element = array_replace($element, $fields[$name]); }
            if (isset($element['items'])) { $this->ApplyFormFields($element['items'], $fields); }
        }
        unset($element);
    }

    public function UpdateSettingsForm(int $Source, int $View): void
    {
        // Only change form visibility. Pending selections are not saved or applied here.
        foreach ($this->SettingsFormFields($Source, $View) as $name => $parameters) {
            foreach ($parameters as $parameter => $value) { $this->UpdateFormField($name, $parameter, $value); }
        }
    }

    public function GetRoomDefaults(): string
    {
        return $this->DefaultRoomJSON();
    }

    private function SettingsFormFields(int $source, int $view): array
    {
        $own = $source === 0;
        $fields = [];
        foreach (['PresenceSettings' => 1, 'DoorSettings' => 2, 'LightSettings' => 5, 'CinemaSettings' => 6, 'HeaterSettings' => 14,
            'DeviceSettings' => 4, 'PVSettings' => 7, 'OutdoorSettings' => 11, 'MotionSettings' => 8,
            'TemperatureSettings' => 9, 'WeatherSettings' => 10, 'RoomDeviceSettings' => 12, 'NetworkSettings' => 13] as $name => $sectionView) {
            $fields[$name] = ['visible' => $own, 'expanded' => $own && $view === $sectionView];
        }
        $fields['OverviewSettings'] = ['visible' => $own && $view === 0];
        $fields['DisplaySettings'] = ['visible' => $own];
        $fields['RoomFilter'] = ['visible' => $view === 12];
        $caption = 'Eigene Einstellungen: Die Quellen und Bedienoptionen werden in dieser Instanz festgelegt.';
        if (!$own) {
            if ($source === $this->InstanceID || !IPS_InstanceExists($source)
                || IPS_GetInstance($source)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
                || IPS_GetProperty($source, 'ConfigSource') !== 0) {
                $caption = 'Die gewählte Konfigurationsquelle ist ungültig. Eine Hausstatus-Instanz mit eigenen Einstellungen wählen oder die Auswahl leeren.';
            } else {
                $caption = 'Gemeinsame Einstellungen aus ' . IPS_GetName($source) . ' (ID ' . $source
                    . '). Quellen und Bedienoptionen dort ändern. Hier werden nur Inhalt und Aktivierung dieser Kachel festgelegt.';
            }
        }
        $fields['SettingsOrigin'] = ['caption' => $caption];
        return $fields;
    }

    private function FreshState(): array
    {
        $this->SetBuffer('MotionCache', '');
        $this->SetBuffer('RainCache', '');
        $state = $this->BaseState();
        $this->SetBuffer('LastState', json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        return $state;
    }

    public function RefreshFromConfiguration(): string
    {
        if (!$this->ReadPropertyBoolean('ActiveView')) { return 'Diese Kachel ist pausiert. Zum Aktualisieren zuerst aktivieren und Änderungen übernehmen.'; }
        try {
            $this->UpdateVisualizationValue(json_encode($this->FreshState(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            return 'Werte wurden neu gelesen und an die geöffnete Kachel übergeben.';
        } catch (Throwable $e) {
            $this->SendDebug('Aktualisierungsfehler', $e->getMessage(), 0);
            return 'Aktualisierung fehlgeschlagen: ' . $e->getMessage();
        }
    }

    public function GetVisualizationTile(): string
    {
        try {
            $html = file_get_contents(__DIR__ . '/module.html');
            if ($html === false) { throw new RuntimeException('module.html fehlt.'); }
            if (!str_contains($html, '/*INITIAL_STATE*/null')) { throw new RuntimeException('Die HTML-Vorlage enthält keinen Platzhalter für Zustandsdaten.'); }
            $initial = json_encode($this->BaseState(), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            return str_replace('/*INITIAL_STATE*/null', $initial, $html);
        } catch (Throwable $e) {
            $this->SendDebug('HTML-Darstellung', $e->getMessage(), 0);
            $message = htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            return '<!doctype html><html lang="de"><head><meta charset="utf-8"></head><body '
                . 'style="margin:0;padding:56px 12px 12px;font:14px system-ui;color:inherit" data-svhs-error="true">'
                . '<div role="alert"><strong>Darstellung konnte nicht geladen werden.</strong><p>' . $message
                . '</p><p>Instanz ' . $this->InstanceID . ': Konfiguration und Quellen prüfen.</p></div></body></html>';
        }
    }

    public function RequestAction(string $Ident, mixed $Value): void
    {
        if (!in_array($Ident, ['Refresh', 'PageRead', 'ViewerPing'], true)) {
            $this->SetBuffer('LastCommandReceipt', date('c') . ' | ' . $Ident . ' | ' . get_debug_type($Value));
        }
        if (preg_match('/^RoomValue:([1-9][0-9]{0,9})$/D', $Ident, $match) === 1) {
            $Value = ['id' => (int)$match[1], 'value' => $Value]; $Ident = 'RoomValue';
        }
        if (!$this->ReadPropertyBoolean('ActiveView')) { throw new RuntimeException('Diese Ansicht ist pausiert. Bitte die gemeinsame Hausstatus-Kachel verwenden.'); }
        if ($Ident === 'ViewerPing') {
            $this->SetBuffer('LastViewerSeen', (string)time());
            return;
        }
        if (in_array($Ident, ['PageRead', 'PageValue'], true)) {
            $this->HandlePageRequest($Ident, $Value); return;
        }
        if ($Ident === 'Refresh') {
            // Reply to the requesting tile even when no values have changed.
            // A manual refresh must also bypass the cached archive history.
            $request = is_string($Value) && preg_match('/^refresh-[a-z0-9-]{1,80}$/D', $Value) === 1 ? $Value : null;
            try {
                $state = $this->FreshState();
                $encoded = json_encode($state, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                $reply = $request === null ? $encoded : json_encode(['refreshDone' => $request, 'state' => $state],
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
                $this->UpdateVisualizationValue($reply);
            } catch (Throwable $e) {
                $this->SendDebug('Aktualisierungsfehler', $e->getMessage(), 0);
                $this->UpdateVisualizationValue(json_encode(['refreshDone' => $request, 'refreshError' => $e->getMessage()],
                    JSON_INVALID_UTF8_SUBSTITUTE));
            }
            return;
        }
        if (!in_array($Ident, ['Light', 'Brightness', 'Cinema', 'CinemaSource', 'CinemaVolume', 'DoorConfirm', 'DoorOpen', 'DoorPermission',
            'AwningPosition', 'AwningAuto', 'RoofPosition', 'RoofAuto', 'RoofNight', 'RoomValue', 'Dining', 'DiningBrightness', 'HeosSelection', 'HeosRadio', 'HeosNAS', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'], true)) {
            throw new InvalidArgumentException('Unbekannte Bedienaktion.');
        }
        $key = 'SVHSCommand' . $this->InstanceID;
        if (!IPS_SemaphoreEnter($key, 1000)) {
            $this->UpdateVisualizationValue(json_encode(['error' => 'Bitte kurz warten und erneut bedienen.']));
            return;
        }
        try {
            $this->SetBuffer('LastCommandError', '');
            // Structured HTML commands cross the SDK bridge as scalar JSON text.
            // Keep arrays accepted for existing PHP callers and validate types afterwards.
            if (in_array($Ident, ['RoomValue', 'DoorConfirm', 'DoorOpen'], true) && is_string($Value)) {
                if (strlen($Value) > 32768) { throw new InvalidArgumentException('Bedienbefehl ist zu groß.'); }
                $Value = json_decode($Value, true, 32, JSON_THROW_ON_ERROR);
            }
            if (in_array($Ident, ['HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'], true)) {
                $this->SetHeater($Ident, $Value);
            } elseif (in_array($Ident, ['Dining', 'DiningBrightness'], true)) {
                $this->SetDining($Ident, $Value);
            } elseif ($Ident === 'HeosSelection') {
                $this->SetHeosSelection($Value);
            } elseif ($Ident === 'HeosRadio' || $Ident === 'HeosNAS') {
                $this->SetHeosSplitSelection($Ident, $Value);
            } elseif ($Ident === 'RoomValue') {
                $this->SetRoomValue($Value);
            } elseif (in_array($Ident, ['AwningPosition', 'AwningAuto', 'RoofPosition', 'RoofAuto', 'RoofNight'], true)) {
                $this->SetOutdoor($Ident, $Value);
            } elseif ($Ident === 'DoorPermission') {
                $this->SetDoorPermission($Value);
            } elseif ($Ident === 'DoorConfirm') {
                $this->PrepareDoor($Value);
                return;
            } elseif ($Ident === 'DoorOpen') {
                $this->OpenDoor($Value);
            } elseif ($Ident === 'CinemaSource') {
                $this->SetCinemaSource($Value);
            } elseif ($Ident === 'CinemaVolume') {
                $this->SetCinemaVolume($Value);
            } elseif ($Ident === 'Cinema') {
                if (!$this->ConfigBoolean('CinemaEnabled')) { throw new RuntimeException('Cinema-Bedienung ist deaktiviert.'); }
                if (!is_bool($Value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
                $id = $this->ConfigInteger('CinemaControl');
                $this->ValidateAction($id, 0);
                RequestAction($id, $Value);
            } else {
                $this->SetLight($Ident, $Value);
            }
            $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'state' => $this->BaseState()], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
        } catch (Throwable $e) {
            $this->SetBuffer('LastCommandError', get_class($e) . ': ' . $e->getMessage());
            $this->SendDebug('Bedienfehler', $e->getMessage(), 0);
            $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'error' => $e->getMessage()], JSON_INVALID_UTF8_SUBSTITUTE));
        } finally {
            IPS_SemaphoreLeave($key);
        }
    }


    private function ConfigValue(string $name): mixed
    {
        $source = $this->ReadPropertyInteger('ConfigSource');
        if ($source === 0) { return IPS_GetProperty($this->InstanceID, $name); }
        if ($source === $this->InstanceID || !IPS_InstanceExists($source)
            || IPS_GetInstance($source)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
            || IPS_GetProperty($source, 'ConfigSource') !== 0) {
            throw new RuntimeException('Zentrale Hausstatus-Konfiguration fehlt oder ist ungültig.');
        }
        return IPS_GetProperty($source, $name);
    }

    private function ConfigInteger(string $name): int { return (int)$this->ConfigValue($name); }
    private function ConfigBoolean(string $name): bool { return (bool)$this->ConfigValue($name); }
    private function ConfigString(string $name): string { return (string)$this->ConfigValue($name); }
    private function HasMotionView(): bool
    {
        $view = $this->CurrentView();
        return $view === 8 || ($view === 0 && !$this->ConfigBoolean('SeparateDetails'));
    }
    private function HasTemperatureView(): bool
    {
        $view = $this->CurrentView();
        return $view === 9 || ($view === 0 && !$this->ConfigBoolean('SeparateDetails'));
    }

    private function SourceNames(): array
    {
        $views = [1 => ['Presence', 'Alarm'], 2 => ['Lock', 'DoorContact', 'DoorPermission', 'DoorControl', 'DoorOpened', 'DoorClosed'],
            4 => ['BatteryWarnings'], 5 => ['LightState', 'Brightness'],
            6 => ['CinemaState', 'CinemaControl', 'CinemaSource', 'CinemaVolume', 'HeosSelection', 'HeosRadio', 'HeosNAS', 'HeosStatus'], 7 => ['PVPower', 'PVEnergy'], 8 => [], 9 => ['HeatingProfile'],
            10 => ['Weather', 'Wind', 'Rain', 'Warning', 'Sunrise', 'Sunset'],
            11 => ['AwningPosition', 'AwningAuto', 'AwningStatus', 'RoofPosition', 'RoofAuto', 'RoofNight', 'RoofStatus'], 14 => [], 15 => ['PVPower', 'PVEnergy'], 16 => ['Weather', 'Wind', 'Rain', 'Warning', 'Sunrise', 'Sunset'], 17 => [], 12 => ['HeosStatus'], 13 => ['NetworkConnection', 'NetworkDownload', 'NetworkUpload', 'NetworkDownloadUsage', 'NetworkUploadUsage', 'NetworkActiveDevices', 'NetworkDevices', 'NetworkUptime', 'NetworkModel', 'NetworkFirmware']];
        $view = $this->CurrentView();
        if ($view === 0 && $this->ConfigBoolean('SeparateDetails')) {
            return array_keys(self::SOURCES);
        }
        return $views[$view] ?? array_keys(self::SOURCES);
    }

    private function CinemaSourceOptions(): array
    {
        $id = $this->ConfigInteger('CinemaSource');
        if (!$this->ConfigBoolean('CinemaEnabled') || !$this->HasAction($id, 1)) { return []; }
        $presentation = $this->VariablePresentation($id);
        if (($presentation['PRESENTATION'] ?? '') === '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}') {
            $options = $presentation['OPTIONS'] ?? [];
            if (is_string($options)) { $options = json_decode($options, true); }
            $result = [];
            foreach (is_array($options) ? $options : [] as $option) {
                if (!is_array($option) || !isset($option['Value'], $option['Caption'])) { continue; }
                $value = $option['Value'];
                if ((is_int($value) || is_float($value)) && is_finite((float)$value)
                    && (float)$value === (float)(int)$value) {
                    $result[(int)$value] = ['value' => (int)$value, 'name' => strip_tags((string)$option['Caption'])];
                }
            }
            return array_values($result);
        }
        if (!in_array($presentation['PRESENTATION'] ?? '', ['', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}'], true)) { return []; }
        $v = IPS_GetVariable($id);
        $name = $presentation['PROFILE'] ?? ($v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile']);
        if ($name === '' || !IPS_VariableProfileExists($name)) { return []; }
        $result = [];
        foreach (IPS_GetVariableProfile($name)['Associations'] as $a) {
            if (is_numeric($a['Value']) && (float)$a['Value'] === (float)(int)$a['Value']) {
                $result[] = ['value' => (int)$a['Value'], 'name' => strip_tags((string)$a['Name'])];
            }
        }
        return $result;
    }

    private function SetCinemaSource(mixed $Value): void
    {
        if (!is_int($Value) || !in_array($Value, array_column($this->CinemaSourceOptions(), 'value'), true)) {
            throw new RuntimeException('Quelle ist nicht als schaltbare Auswahl eingerichtet.');
        }
        if (!RequestAction($this->ConfigInteger('CinemaSource'), $Value)) { throw new RuntimeException('Quellenwahl fehlgeschlagen.'); }
    }

    private function VariablePresentation(int $id): array
    {
        try { return IPS_GetVariablePresentation($id); }
        catch (Throwable $e) { $this->SendDebug('Variablendarstellung', $e->getMessage(), 0); return []; }
    }

    private function CinemaVolumePlan(): array
    {
        if (!$this->ConfigBoolean('CinemaEnabled')) { throw new RuntimeException('Cinema-Bedienung ist im Modul deaktiviert.'); }
        return $this->SliderPlan($this->ConfigInteger('CinemaVolume'));
    }

    private function SliderPlan(int $id): array
    {
        if ($id <= 0 || !IPS_VariableExists($id)) { throw new RuntimeException('Bedienvariable fehlt.'); }
        $v = IPS_GetVariable($id); $type = (int)$v['VariableType'];
        if (!in_array($type, [1, 2], true)) { throw new RuntimeException('Regler braucht eine Integer- oder Float-Variable.'); }
        if (!$this->HasAction($id, $type)) { throw new RuntimeException('Die Variable hat keine aktive Bedienaktion.'); }
        $presentation = $this->VariablePresentation($id);
        $kind = $presentation['PRESENTATION'] ?? '';
        if ($kind === '{6B9CAEEC-5958-C223-30F7-BD36569FC57A}') {
            $min = $presentation['MIN'] ?? null; $max = $presentation['MAX'] ?? null;
            $step = $presentation['STEP_SIZE'] ?? 0;
            $percentage = (bool)($presentation['PERCENTAGE'] ?? false);
            $prefix = (string)($presentation['PREFIX'] ?? ''); $suffix = (string)($presentation['SUFFIX'] ?? '');
            $digits = (int)($presentation['DIGITS'] ?? 0);
        } elseif (in_array($kind, ['', '{4153A8D4-5C33-C65F-C1F3-7B61AAF99B1C}'], true)) {
            $name = $presentation['PROFILE'] ?? ($v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile']);
            if ($name === '' || !IPS_VariableProfileExists($name)) {
                throw new RuntimeException('In der Variable sind keine Reglergrenzen hinterlegt.');
            }
            $p = IPS_GetVariableProfile($name);
            $min = $p['MinValue']; $max = $p['MaxValue']; $step = $p['StepSize'];
            $prefix = (string)$p['Prefix']; $suffix = (string)$p['Suffix'];
            $percentage = trim($suffix) === '%'; $digits = (int)$p['Digits'];
        } else {
            throw new RuntimeException('Die Variable verwendet keine Schieberegler-Darstellung.');
        }
        if (!is_numeric($min) || !is_numeric($max) || !is_numeric($step)
            || !is_finite((float)$min) || !is_finite((float)$max) || !is_finite((float)$step)
            || (float)$max <= (float)$min || (float)$step < 0) {
            throw new RuntimeException('Die Darstellung hat ungültige Reglergrenzen.');
        }
        $min = (float)$min; $max = (float)$max; $step = (float)$step;
        if ($type === 1 && (floor($min) !== $min || floor($max) !== $max || floor($step) !== $step)) {
            throw new RuntimeException('Reglergrenzen und Schrittweite passen nicht zum Integer-Typ.');
        }
        // Step 0 means no fixed increment in Symcon, not an invented 0.5 dB step.
        $step = $step > 0 ? $step : ($type === 1 ? 1 : 'any');
        return ['min' => $min, 'max' => $max, 'step' => $step, 'type' => $type,
            'percentage' => $percentage, 'prefix' => $prefix, 'suffix' => $suffix, 'digits' => max(0, min(6, $digits))];
    }

    private function SetCinemaVolume(mixed $Value): void
    {
        $control = $this->CinemaVolumePlan();
        $this->SetSlider($this->ConfigInteger('CinemaVolume'), $Value, $control);
    }

    private function SetSlider(int $id, mixed $Value, array $control): void
    {
        if ((!is_int($Value) && !is_float($Value)) || !is_finite((float)$Value)
            || $Value < $control['min'] || $Value > $control['max']
            || ($control['type'] === 1 && floor($Value) !== (float)$Value)) {
            throw new RuntimeException('Wert liegt außerhalb der konfigurierten Reglergrenzen.');
        }
        if (is_numeric($control['step'])) {
            $steps = ((float)$Value - $control['min']) / $control['step'];
            if (abs($steps - round($steps)) > 0.000001) { throw new RuntimeException('Wert passt nicht zur konfigurierten Schrittweite.'); }
        }
        $value = $control['type'] === 1 ? (int)$Value : (float)$Value;
        if (!RequestAction($id, $value)) { throw new RuntimeException('Regleraktion fehlgeschlagen.'); }
    }

    private function SetOutdoor(string $command, mixed $value): void
    {
        if (!$this->ConfigBoolean('OutdoorEnabled')) { throw new RuntimeException('Markise/Dachfenster-Bedienung ist deaktiviert.'); }
        $id = $this->ConfigInteger($command);
        if (in_array($command, ['AwningPosition', 'RoofPosition'], true)) {
            $this->SetSlider($id, $value, $this->SliderPlan($id));
        } else {
            if (!is_bool($value)) { throw new InvalidArgumentException('Ein/Aus erwartet einen Boolean-Wert.'); }
            $this->ValidateAction($id, 0);
            if (!RequestAction($id, $value)) { throw new RuntimeException('Automatikaktion fehlgeschlagen.'); }
        }
    }

    private function OutdoorState(): array
    {
        $enabled = $this->ConfigBoolean('OutdoorEnabled');
        $result = [];
        foreach (['Awning', 'Roof'] as $name) {
            $control = null; $reason = '';
            try {
                if (!$enabled) { throw new RuntimeException('Bedienung ist deaktiviert.'); }
                $control = $this->SliderPlan($this->ConfigInteger($name . 'Position'));
            } catch (Throwable $e) { $reason = $e->getMessage(); }
            $result[$name] = [
                'position' => $this->Read($this->ConfigInteger($name . 'Position')),
                'auto' => $this->Read($this->ConfigInteger($name . 'Auto')),
                'status' => $this->Read($this->ConfigInteger($name . 'Status')),
                'control' => $control, 'reason' => $reason,
                'canAuto' => $enabled && $this->HasAction($this->ConfigInteger($name . 'Auto'), 0)
            ];
        }
        $result['Roof']['night'] = $this->Read($this->ConfigInteger('RoofNight'));
        $result['Roof']['canNight'] = $enabled && $this->HasAction($this->ConfigInteger('RoofNight'), 0);
        return $result;
    }

    private function CanSetDoorPermission(): bool
    {
        $id = $this->ConfigInteger('DoorPermission');
        return $this->ConfigBoolean('DoorEnabled') && $id > 0
            && IPS_VariableExists($id) && IPS_GetVariable($id)['VariableType'] === 0
            && (int)IPS_GetVariable($id)['VariableCustomAction'] !== 1;
    }

    private function SetDoorPermission(mixed $Value): void
    {
        if (!is_bool($Value) || !$this->CanSetDoorPermission()) {
            throw new RuntimeException('Türfreigabe ist deaktiviert oder keine Boolean-Variable.');
        }
        $id = $this->ConfigInteger('DoorPermission');
        if ($this->HasAction($id, 0)) {
            if (!RequestAction($id, $Value)) { throw new RuntimeException('Freigabeaktion fehlgeschlagen.'); }
        } else {
            // This is the explicitly selected permission flag, not the lock actuator.
            SetValueBoolean($id, $Value);
        }
        // Any pending opening confirmation is invalid after a permission command.
        $this->SetBuffer('DoorChallenges', '{}');
    }

    private function DoorPlan(): array
    {
        if (!$this->ConfigBoolean('DoorEnabled')) {
            throw new RuntimeException('Türöffnung ist im Modul deaktiviert.');
        }
        $permissionID = $this->ConfigInteger('DoorPermission');
        if (!IPS_VariableExists($permissionID)
            || IPS_GetVariable($permissionID)['VariableType'] !== 0
            || GetValueBoolean($permissionID) !== true) {
            throw new RuntimeException('Tür öffnen ist gesperrt. Zuerst die vorhandene Türfreigabe aktivieren.');
        }
        $contact = $this->Read($this->ConfigInteger('DoorContact'))['raw'];
        if ($contact === true || $contact === 1) {
            throw new RuntimeException('Die Tür ist bereits offen.');
        }
        $id = $this->ConfigInteger('DoorControl');
        $this->ValidateAction($id, 1);
        $v = IPS_GetVariable($id);
        // Use the existing custom door script, never write directly to the lock.
        $script = (int)$v['VariableCustomAction'];
        if ($script <= 0 || !IPS_ScriptExists($script)) {
            throw new RuntimeException('Die Tür-Bedienvariable muss ein vorhandenes Aktionsskript besitzen.');
        }
        $profileName = $v['VariableCustomProfile'] !== '' ? $v['VariableCustomProfile'] : $v['VariableProfile'];
        if ($profileName === '' || !IPS_VariableProfileExists($profileName)) {
            throw new RuntimeException('Das Variablenprofil für die Türbedienung fehlt.');
        }
        $values = [];
        foreach (IPS_GetVariableProfile($profileName)['Associations'] as $association) {
            $name = trim(strip_tags((string)$association['Name']));
            $name = strtolower(str_replace(["\u{d6}", "\u{f6}", "\u{dc}", "\u{fc}"], ['Oe', 'oe', 'Ue', 'ue'], $name));
            if (in_array($name, ['oeffnen', 'tuer oeffnen', 'open'], true)) {
                $value = $association['Value'];
                if (is_numeric($value) && (float)$value === (float)(int)$value) { $values[] = (int)$value; }
            }
        }
        if (count($values) !== 1) {
            throw new RuntimeException('Die Aktion Öffnen ist im Türprofil nicht eindeutig. Kein Befehl gesendet.');
        }
        return ['id' => $id, 'value' => $values[0], 'script' => $script, 'permission' => $permissionID];
    }

    private function DoorAvailable(): bool
    {
        return $this->DoorReason() === '';
    }

    private function DoorReason(): string
    {
        try { $this->DoorPlan(); return ''; } catch (Throwable $e) { return $e->getMessage(); }
    }

    private function PrepareDoor(mixed $Value): void
    {
        if (!is_array($Value) || !isset($Value['request']) || !is_string($Value['request'])
            || !preg_match('/^[a-zA-Z0-9-]{16,64}$/D', $Value['request'])) {
            throw new InvalidArgumentException('Ungültige Bestätigungsanfrage.');
        }
        $plan = $this->DoorPlan();
        $pending = json_decode($this->GetBuffer('DoorChallenges'), true) ?: [];
        $pending = array_filter($pending, static fn(array $entry): bool => $entry['expires'] > time());
        if (count($pending) >= 10) { throw new RuntimeException('Bitte kurz warten und erneut versuchen.'); }
        $token = bin2hex(random_bytes(24));
        $expires = time() + 20;
        $pending[$Value['request']] = ['token' => $token, 'expires' => $expires, 'plan' => $plan];
        $this->SetBuffer('DoorChallenges', json_encode($pending, JSON_THROW_ON_ERROR));
        $this->UpdateVisualizationValue(json_encode(['doorConfirmation' => [
            'request' => $Value['request'], 'token' => $token, 'expires' => $expires, 'ttl' => 20
        ]], JSON_THROW_ON_ERROR));
    }

    private function OpenDoor(mixed $Value): void
    {
        if (!is_array($Value) || !isset($Value['request'], $Value['token'])
            || !is_string($Value['request']) || !is_string($Value['token'])
            || strlen($Value['request']) > 64 || strlen($Value['token']) !== 48) {
            throw new InvalidArgumentException('Bestätigung fehlt.');
        }
        $pending = json_decode($this->GetBuffer('DoorChallenges'), true) ?: [];
        $entry = $pending[$Value['request']] ?? null;
        if (!$entry || $entry['expires'] <= time() || !hash_equals($entry['token'], $Value['token'])) {
            throw new RuntimeException('Bestätigung fehlt oder ist abgelaufen. Bitte erneut Tür öffnen wählen.');
        }
        // Consume first; even a failing action must not be replayed.
        unset($pending[$Value['request']]);
        $this->SetBuffer('DoorChallenges', json_encode($pending, JSON_THROW_ON_ERROR));
        $plan = $this->DoorPlan();
        if ($plan !== $entry['plan']) { throw new RuntimeException('Türkonfiguration wurde geändert. Bitte erneut bestätigen.'); }
        if (!RequestAction($plan['id'], $plan['value'])) {
            throw new RuntimeException('Das Tür-Aktionsskript konnte nicht ausgeführt werden.');
        }
    }

    private function ValidateAction(int $id, int $type): void
    {
        if (!$this->HasAction($id, $type)) { throw new RuntimeException('Die gewählte Variable fehlt, hat den falschen Typ oder keine Bedienaktion.'); }
    }

    private function HasAction(int $id, int $type): bool
    {
        if ($id <= 0 || !IPS_VariableExists($id)) { return false; }
        $v = IPS_GetVariable($id);
        if ((int)$v['VariableCustomAction'] === 1) { return false; }
        $action = (int)$v['VariableCustomAction'] > 0 ? (int)$v['VariableCustomAction'] : (int)$v['VariableAction'];
        return (int)$v['VariableType'] === $type && $action > 0;
    }

    private function Rooms(): array
    {
        $list = json_decode($this->ConfigString('Rooms'), true);
        if (!is_array($list)) { return []; }
        $result = [];
        foreach ($list as $room) {
            if (is_array($room) && isset($room['Name'], $room['Variable'])
                && is_string($room['Name']) && is_numeric($room['Variable'])) {
                $result[] = ['Name' => $room['Name'], 'Variable' => (int)$room['Variable'], 'Setpoint' => (int)($room['Setpoint'] ?? 0)];
            }
        }
        return $result;
    }

    private function Read(int $id): array
    {
        if ($id <= 0 || !IPS_VariableExists($id)) { return ['raw' => null, 'text' => 'Unbekannt']; }
        try {
            return ['raw' => GetValue($id), 'text' => (string)GetValueFormatted($id)];
        } catch (Throwable $e) { return ['raw' => null, 'text' => 'Unbekannt']; }
    }

    private function MotionSensors(): array
    {
        $list = json_decode($this->ConfigString('MotionSensors'), true);
        if (!is_array($list)) { return []; }
        return array_values(array_filter($list, static fn($v): bool => is_array($v)
            && isset($v['Name'], $v['Variable']) && is_string($v['Name']) && is_int($v['Variable'])));
    }

    private function MotionArchive(): int
    {
        $id = $this->ConfigInteger('MotionArchive');
        if ($id > 0) { return IPS_InstanceExists($id) ? $id : 0; }
        $ids = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        return count($ids) === 1 ? $ids[0] : 0;
    }

    private function MotionState(): array
    {
        $now = time(); $start = $now - 86400;
        $cache = json_decode($this->GetBuffer('MotionCache'), true);
        if (!is_array($cache) || ($cache['time'] ?? 0) < $now - 30) {
            $cache = ['time' => $now, 'history' => []];
            $archive = $this->MotionArchive();
            foreach ($this->MotionSensors() as $sensor) {
                $id = $sensor['Variable'];
                $history = ['entries' => [], 'previous' => null, 'note' => ''];
                try {
                    if ($id <= 0 || !IPS_VariableExists($id) || IPS_GetVariable($id)['VariableType'] !== 0) {
                        throw new RuntimeException('Boolean-Bewegungsvariable auswählen.');
                    }
                    if ($archive <= 0) { throw new RuntimeException('Archiv auswählen: kein eindeutiges Archiv gefunden.'); }
                    if (!AC_GetLoggingStatus($archive, $id)) { throw new RuntimeException('Archivierung ist nicht aktiviert.'); }
                    $values = AC_GetLoggedValues($archive, $id, $start, $now, 10000);
                    foreach ($values as $v) { $history['entries'][] = ['time' => (int)$v['TimeStamp'], 'active' => (bool)$v['Value']]; }
                    $previous = AC_GetLoggedValues($archive, $id, 0, $start - 1, 1);
                    if ($previous) { $history['previous'] = (bool)$previous[0]['Value']; }
                    if (count($values) >= 10000) { $history['note'] = 'Abfragelimit erreicht: Verlauf möglicherweise unvollständig.'; }
                    elseif (!$values && !$previous) { $history['note'] = 'Noch keine Archivdaten. Aufzeichnung beginnt mit Aktivierung.'; }
                } catch (Throwable $e) { $history['note'] = $e->getMessage(); }
                $cache['history'][(string)$id] = $history;
            }
            $this->SetBuffer('MotionCache', json_encode($cache, JSON_THROW_ON_ERROR));
        }
        $result = ['start' => $start, 'end' => $now, 'sensors' => []];
        foreach ($this->MotionSensors() as $sensor) {
            $id = $sensor['Variable'];
            $result['sensors'][] = ['name' => $sensor['Name'], 'current' => $this->Read($id)['raw'],
                'history' => $cache['history'][(string)$id] ?? ['entries' => [], 'previous' => null, 'note' => 'Noch keine Daten.']];
        }
        return $result;
    }

    private function State(): array
    {
        $state = ['View' => $this->CurrentView(), 'SeparateDetails' => $this->ConfigBoolean('SeparateDetails')];
        foreach (array_keys(self::SOURCES) as $name) { $state[$name] = $this->Read($this->ConfigInteger($name)); }
        $state['InlinePages'] = $this->UsesInlinePages();
        $state['NavigationRooms'] = $state['InlinePages'] ? $this->NavigationRooms() : [];
        $state['PageRoom'] = $this->CurrentRoom();
        $state['DisplaySettings'] = $this->DisplaySettings();
        $state['Network'] = $this->NetworkState();
        $state['HeatingProfileItem'] = $this->HeatingProfileItem();
        $state['DoorReason'] = $this->DoorReason();
        $state['Motion'] = $this->HasMotionView() ? $this->MotionState() : null;
        $state['RainHistory'] = $this->HasRainView() ? $this->RainHistoryState() : null;
        $state['Outdoor'] = in_array($this->CurrentView(), [0, 11], true) ? $this->OutdoorState() : null;
        $state['HeosStatusConfigured'] = $this->ConfigInteger('HeosStatus') > 0;
        $state['HeosItem'] = $this->HeosItem();
        $state['HeosRadioItem'] = $this->HeosSplitItem('HeosRadio');
        $state['HeosNASItem'] = $this->HeosSplitItem('HeosNAS');
        $state['CinemaOptions'] = $this->CinemaSourceOptions();
        $state['VolumeControl'] = null;
        $state['VolumeReason'] = '';
        try { $state['VolumeControl'] = $this->CinemaVolumePlan(); }
        catch (Throwable $e) { $state['VolumeReason'] = $e->getMessage(); }
        $state['Rooms'] = [];
        $state['RoomSections'] = $this->RoomSections();
        $state['TemperatureRows'] = $this->TemperatureRows();
        $state['RoomSummary'] = [];
        if ($state['View'] === 12 && $state['PageRoom'] === '') {
            $state['RoomSummary'] = $this->RoomSummary($state['RoomSections'], $state['TemperatureRows']);
            $state['RoomSections'] = []; $state['TemperatureRows'] = [];
        }
        $state['SeparateHomepageTiles'] = $this->ConfigBoolean('SeparateHomepageTiles');
        $state['Heater'] = in_array($this->CurrentView(), [0, 14], true) ? $this->HeaterState() : null;
        $state['Dining'] = in_array($this->CurrentView(), [0, 5, 17], true)
            && ($this->ConfigInteger('DiningInstance') > 0 || $this->ConfigInteger('DiningState') > 0) ? $this->DiningState() : null;
        $state['PVInverters'] = $this->PVInverters();
        $state['PVDetailsTarget'] = in_array($this->CurrentView(), [0, 15], true) ? $this->PVDetailsTarget() : 0;
        $state['RoomNavigationTarget'] = $this->CurrentView() === 0 ? $this->RoomNavigationTarget() : 0;
        if ($this->HasTemperatureView()) {
            foreach ($this->Rooms() as $room) {
                $state['Rooms'][] = ['name' => $room['Name'], 'value' => $this->Read($room['Variable'])['text']];
            }
        }
        $script = $this->ConfigInteger('LightCommandScript');
        $scriptOK = $script > 0 && IPS_ScriptExists($script);
        $state['Controls'] = [
            'DoorPermission' => $this->CanSetDoorPermission(),
            'Door' => $this->DoorAvailable(),
            'Light' => $this->ConfigBoolean('LightEnabled')
                && ($script > 0 ? $scriptOK : $this->HasAction($this->ConfigInteger('LightState'), 0)),
            'Brightness' => $this->ConfigBoolean('LightEnabled')
                && ($script > 0 ? $scriptOK : $this->HasAction($this->ConfigInteger('Brightness'), 1)),
            'Cinema' => $this->ConfigBoolean('CinemaEnabled')
                && $this->HasAction($this->ConfigInteger('CinemaControl'), 0)
        ];
        return $state;
    }
}
