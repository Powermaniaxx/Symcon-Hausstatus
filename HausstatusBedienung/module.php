<?php
declare(strict_types=1);
require_once __DIR__ . '/RoomSupport.php';
require_once __DIR__ . '/ComfortSupport.php';
require_once __DIR__ . '/RainSupport.php';
require_once __DIR__ . '/NavigationSupport.php';
require_once __DIR__ . '/DisplaySupport.php';
require_once __DIR__ . '/HeatingCenterSupport.php';
require_once __DIR__ . '/NetworkSupport.php';
require_once __DIR__ . '/PageSupport.php';
require_once __DIR__ . '/HomepageSupport.php';
require_once __DIR__ . '/PresenceHistorySupport.php';
require_once __DIR__ . '/DockerSupport.php';
require_once __DIR__ . '/LaundrySupport.php';
require_once __DIR__ . '/ShieldSupport.php';
require_once __DIR__ . '/BedroomMediaSupport.php';
require_once __DIR__ . '/WaterbedSupport.php';

class HausstatusBedienung extends IPSModuleStrict
{
    use HausstatusRoomSupport;
    use HausstatusComfortSupport;
    use HausstatusRainSupport;
    use HausstatusNavigationSupport;
    use HausstatusDisplaySupport;
    use HausstatusHeatingCenterSupport;
    use HausstatusNetworkSupport;
    use HausstatusPageSupport;
    use HausstatusHomepageSupport;
    use HausstatusPresenceHistorySupport;
    use HausstatusDockerSupport;
    use HausstatusLaundrySupport;
    use HausstatusShieldSupport;
    use HausstatusBedroomMediaSupport;
    use HausstatusWaterbedSupport;
    private const SOURCES = [
        
        'NetworkConnection' => 0, 'NetworkDownload' => 0, 'NetworkUpload' => 0, 'NetworkDownloadUsage' => 0, 'NetworkUploadUsage' => 0, 'NetworkActiveDevices' => 0, 'NetworkDevices' => 0, 'NetworkUptime' => 0, 'NetworkModel' => 0, 'NetworkFirmware' => 0,
        'NetworkManufacturer' => 0, 'NetworkWanType' => 0, 'NetworkUPnP' => 0,
        'NetworkDNS1' => 0, 'NetworkDNS2' => 0, 'NetworkDownstreamMax' => 0, 'NetworkUpstreamMax' => 0,
        'NetworkWirelessState' => 0, 'NetworkLastRestart' => 0,
        'NetworkTotalReceived' => 0, 'NetworkTotalSent' => 0,
        'NetworkVoipDNS1' => 0, 'NetworkVoipDNS2' => 0,
        'WasherPower' => 15161, 'DryerPower' => 12920, 'WasherRunning' => 0, 'DryerRunning' => 0,
        'HeatingProfile' => 31603, 'Presence' => 52726, 'SvenPresence' => 0, 'SusiPresence' => 0, 'Lock' => 0, 'DoorContact' => 0,
        'DoorControl' => 0, 'DoorPermission' => 0, 'Alarm' => 0, 'BatteryWarnings' => 0,
        'LightState' => 0, 'Brightness' => 0, 'CinemaState' => 0,
        'CinemaControl' => 0, 'CinemaSource' => 0, 'CinemaVolume' => 0, 'HeosSelection' => 0, 'HeosRadio' => 14121, 'HeosNAS' => 45081, 'HeosStatus' => 0, 'PVPower' => 0, 'PVEnergy' => 0,
        'Weather' => 0, 'Wind' => 0, 'Rain' => 0, 'Warning' => 0,
        'OutdoorTemperature' => 0, 'FeelsLikeTemperature' => 0, 'TodayLowTemperature' => 0, 'TodayHighTemperature' => 0,
        'RainProbability' => 0, 'OutdoorHumidity' => 0,
        'Sunrise' => 0, 'Sunset' => 0,
        'DoorOpened' => 0, 'DoorClosed' => 0,
        'AwningPosition' => 0, 'AwningAuto' => 0, 'AwningStatus' => 0,
        'RoofPosition' => 0, 'RoofAuto' => 0, 'RoofNight' => 0, 'RoofStatus' => 0
    ];

    public function Create(): void
    {
        parent::Create();
        $this->CreateShield();
        $this->CreateBedroomMedia();
        $this->CreateWaterbed();
        foreach (self::SOURCES as $name => $id) {
            $this->RegisterPropertyInteger($name, $id);
        }
        $this->RegisterPropertyInteger('View', 0);
        // Sensitive maintenance action: opt-in for a separate, private System tile.
        $this->RegisterPropertyBoolean('ModuleUpdateEnabled', false);
        $this->RegisterPropertyInteger('DockerCategory', 0);
        $this->RegisterPropertyInteger('DockerTileCategory', 0);
        $this->RegisterPropertyBoolean('DockerAllowSymconRestart', false);
        $this->RegisterPropertyBoolean('SeparateHomepageTiles', false);
        $this->RegisterPropertyBoolean('PreserveHomepageStyle', true);
        $this->RegisterPropertyInteger('HomepagePart', 0);
        $this->RegisterPropertyInteger('HomepageCategory', 0);
        // Independent add-tile dialog: destination selected before creating a new view.
        $this->RegisterPropertyInteger('NewTileView', -1);
        $this->RegisterPropertyInteger('NewTileCategory', 0);
        $this->RegisterPropertyString('NewTileTitle', '');
        $this->RegisterPropertyString('NewTileRoomFilter', '');
        $this->RegisterAttributeString('HomepageLayout', '');
        $this->RegisterAttributeString('WatchVariableIDs', '[]');
        $this->RegisterPropertyBoolean('ActiveView', true);
        $this->RegisterPropertyBoolean('SeparateDetails', false);
        $this->RegisterPropertyBoolean('OutdoorEnabled', true);
        $this->RegisterPropertyInteger('ConfigSource', 0);
        $this->RegisterPropertyString('RoomFilter', '');
        // Optional: forward visible WebFront heartbeat to the local Node-RED flow.
        $this->RegisterPropertyString('NodeRedViewerPingURL', '');
        $this->RegisterPropertyString('DiningRoom', 'Wohnzimmer');
        $this->RegisterPropertyString('PVRoom', 'PV-Anlage');
        $this->RegisterPropertyString('DisplayTexts', $this->DefaultDisplayTexts());
        $this->RegisterPropertyString('WirelessNetworks', '[]');
        $this->RegisterPropertyString('RoomEntries', $this->GetRoomDefaults());
        $this->RegisterPropertyBoolean('RoomControlsEnabled', true);
        $this->RegisterPropertyInteger('DiningInstance', 0);
        $this->RegisterPropertyInteger('DiningState', 0);
        $this->RegisterPropertyInteger('DiningBrightness', 0);
        foreach (['LivingColor', 'DiningColor'] as $name) { $this->RegisterPropertyInteger($name, 0); }
        $this->RegisterPropertyInteger('BedroomColor', 15645);
        // Installation-specific Hue targets: editable per lamp; group color is not used.
        foreach (['LivingColor1' => 40852, 'LivingColor2' => 21768, 'LivingColor3' => 41186,
            'DiningColor1' => 50603, 'DiningColor2' => 30828, 'DiningColor3' => 27644] as $name => $id) {
            $this->RegisterPropertyInteger($name, $id);
        }
        $this->RegisterPropertyBoolean('DiningEnabled', true);
        foreach (['HeaterInstance', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'] as $name) { $this->RegisterPropertyInteger($name, 0); }
        $this->RegisterPropertyString('HeaterTitle', 'Zusatzheizung Wohnzimmer');
        $this->RegisterPropertyBoolean('HeaterEnabled', true);
        $this->RegisterPropertyInteger('Raining', 0);
        $this->RegisterPropertyInteger('RainArchive', 0);
        $this->RegisterPropertyInteger('PresenceArchive', 0);
        $this->RegisterPropertyString('AdditionalAlarmVariables', '[]');
        $this->RegisterPropertyString('ExtraTileVariables', '[]');
        $this->RegisterPropertyInteger('SvenPresenceImage', 0);
        $this->RegisterPropertyInteger('SusiPresenceImage', 0);
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
        $this->RegisterTimer('ViewerWatchdog', 0, 'SVHS_ExpireViewers($_IPS["TARGET"]);');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
        // Keine zyklischen Hintergrundchecks: Shield-Ablauf nur bei sichtbarer Medienkachel.
        $this->SetTimerInterval('ShieldWatchdog', 0);
        $this->SetTimerInterval('ViewerWatchdog', 0);
        $this->SetBuffer('ViewerSubscriptionsActive', '0');
        if (!$this->ReadPropertyBoolean('ActiveView')) {
            $this->SetTimerInterval('Refresh', 0);
            $this->WriteAttributeString('WatchVariableIDs', '[]');
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
        $this->SetBuffer('PresenceHistoryCache', '');
        if ($this->ReadPropertyInteger('View') === 18) {
            $presenceArchive = $this->PresenceHistoryArchive();
            if ($presenceArchive > 0) { $this->RegisterReference($presenceArchive); }
            foreach (['SvenPresenceImage', 'SusiPresenceImage'] as $property) {
                $mediaID = $this->ConfigInteger($property);
                if ($mediaID > 0 && IPS_MediaExists($mediaID)) { $this->RegisterReference($mediaID); }
            }
        }
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
        if ($this->ReadPropertyInteger('View') === 12 && mb_strtolower($this->ReadPropertyString('RoomFilter'), 'UTF-8') === 'schlafzimmer') {
            foreach (array_keys(self::WATERBED_SOURCES) as $property) { $ids[] = $this->ConfigInteger($property); }
            $waterbedArchive = $this->WaterbedArchiveID();
            if ($waterbedArchive > 0) { $this->RegisterReference($waterbedArchive); }
        }
        if (in_array($this->ReadPropertyInteger('View'), [0, 5, 12, 17], true)) {
            try { foreach ($this->DiningSources() as $id) { if ($id > 0) { $ids[] = $id; } } } catch (Throwable $e) { /* Missing sources are explained in the tile. */ }
            foreach (['LivingColor', 'DiningColor', 'BedroomColor'] as $name) { $ids[] = $this->ConfigInteger($name); }
            foreach (['LivingColor1', 'LivingColor2', 'LivingColor3', 'DiningColor1', 'DiningColor2', 'DiningColor3'] as $name) { $ids[] = $this->ConfigInteger($name); }
        }
        if ($this->ReadPropertyInteger('View') === 12) {
            foreach ($this->TemperatureSources() as $row) { $ids[] = $row['actualID']; }
        }
        if (in_array($this->ReadPropertyInteger('View'), [0, 14], true)) {
            foreach ([40020, 26961, 16673, 40887, 55619, 35915, 25551, 38129] as $id) { $ids[] = $id; }
        }
        if ($this->ReadPropertyInteger('View') === 31) {
            foreach ([33763, 53624, 21012, 23009, 38996, 46284, 48316, 54068] as $upsID) { $ids[] = $upsID; }
        }
        foreach ($this->SourceNames() as $name) { $ids[] = $this->ConfigInteger($name); }
        foreach ($this->ExtraTileVariableIDs() as $id) { $ids[] = $id; }
        foreach ($this->BedroomMediaIDs() as $id) { $ids[] = $id; }
        foreach ($this->ShieldIDs() as $id) { $ids[] = $id; }
        foreach ($this->LaundrySourceIDs() as $id) { $ids[] = $id; }
        if (in_array($this->ReadPropertyInteger('View'), [25, 31], true)) {
            foreach ($this->DockerContainerIDs() as $dockerInstanceID) {
                $this->RegisterReference($dockerInstanceID);
                foreach (IPS_GetChildrenIDs($dockerInstanceID) as $dockerChild) {
                    if (IPS_VariableExists($dockerChild)) { $ids[] = $dockerChild; }
                }
            }
        }
        if ($this->ReadPropertyInteger('View') === 18) {
            foreach ($this->ExtraAlarmSources() as $row) { $ids[] = $row['id']; }
            $ids[] = 52726; // Die tatsaechliche Google-Home-Quelle aktualisiert die Verlaufskachel.
        }
        if (in_array($this->ReadPropertyInteger('View'), [0, 7, 15, 29], true)) {
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
        if (in_array($this->ReadPropertyInteger('View'), [13, 24, 31], true)) {
            foreach ($this->WirelessNetworks() as $wifi) {
                foreach (['State', 'SSID', 'Devices'] as $name) { $ids[] = $wifi[$name]; }
                if ($wifi['QRCode'] > 0 && IPS_MediaExists($wifi['QRCode'])) { $this->RegisterReference($wifi['QRCode']); }
            }
        }
        $detailsID = $this->BatteryWarningDetailsID();
        if ($detailsID > 0) { $ids[] = $detailsID; }
        $watchIDs = [];
        foreach (array_unique($ids) as $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterReference($id);
                $watchIDs[] = (int)$id;
            }
        }
        $this->WriteAttributeString('WatchVariableIDs', json_encode($watchIDs, JSON_THROW_ON_ERROR));
        $script = $this->ConfigInteger('LightCommandScript');
        if ($script > 0 && IPS_ScriptExists($script)) { $this->RegisterReference($script); }
        $this->HideTileMaximize();
        $this->HideUnusedShieldVariables();
        $this->VersionShieldStatusVariables();
        $this->OrganizeHomepageDetails();
        $this->OrganizeSeparateHomepage();
        // Safe presentation only: status variables stay at the data receiver;
        // stable links group them below the visualization's System category.
        try { $this->RemoveShieldStatusLinks(); }
        catch (Throwable $e) {
            $this->SendDebug('Medienstatus-Navigation', $e->getMessage(), 0);
            IPS_LogMessage('Hausstatus', 'Medienstatus-Verknuepfungen: ' . $e->getMessage());
        }
        // Version all existing Hausstatus presentation links without touching
        // folder names, instances, IDs, target mappings, or grid layouts.
        try { $this->VersionHausstatusPresentationLinks(); }
        catch (Throwable $e) { $this->SendDebug('Hausstatus-Verknuepfungen', $e->getMessage(), 0); }
        foreach ([$this->PVDetailsTarget(), $this->RoomNavigationTarget()] as $target) {
            if ($target > 0) { $this->RegisterReference($target); }
        }
        // 0.33: Kein permanenter Refresh ohne aktive WebFront-Kachel.
        // Bei geoeffnetem WebFront bleiben Variablenaenderungen ereignisgesteuert.
        $this->SetTimerInterval('Refresh', 0);
        $this->SetStatus(102);
        $this->SetSummary($this->GetBuffer('HomepageLayoutError') ?: 'Hausstatus mit HTML-Bedienung');
        $this->SetBuffer('LastState', '');
        if ($this->HasLiveViewers()) { $this->EnableViewerSubscriptions(); }
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

    // Nachrichten sind nur waehrend einer tatsaechlich sichtbaren WebFront-Kachel noetig.
    private function EnableViewerSubscriptions(): bool
    {
        if ($this->GetBuffer('ViewerSubscriptionsActive') === '1') { return false; }
        $ids = json_decode($this->ReadAttributeString('WatchVariableIDs'), true);
        if (!is_array($ids)) { return false; }
        foreach ($ids as $id) {
            $id = (int)$id;
            if ($id > 0 && IPS_VariableExists($id)) {
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterMessage($id, VM_DELETE);
            }
        }
        $this->SetBuffer('ViewerSubscriptionsActive', '1');
        $this->SetTimerInterval('ViewerWatchdog', 30000);
        return true;
    }

    private function DisableViewerSubscriptions(): void
    {
        $this->SetTimerInterval('ViewerWatchdog', 0);
        if ($this->GetBuffer('ViewerSubscriptionsActive') !== '1') { return; }
        foreach ($this->GetMessageList() as $id => $messages) {
            foreach ($messages as $message) {
                if ($message === VM_UPDATE || $message === VM_DELETE) {
                    $this->UnregisterMessage((int)$id, $message);
                }
            }
        }
        $this->SetBuffer('ViewerSubscriptionsActive', '0');
        $this->SetBuffer('LastState', '');
    }

    public function ExpireViewers(): void
    {
        if (!$this->HasLiveViewers()) { $this->DisableViewerSubscriptions(); }
    }

    /**
     * Forward a lightweight alive signal only while an HTML tile is visible.
     * Disabled by default: entering a local URL in the master settings opts in.
     * The receiving Node-RED flow must itself stop polling after 75s silence.
     */
    private function ForwardViewerPingToNodeRed(): void
    {
        $url = trim($this->ConfigString('NodeRedViewerPingURL'));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)
            || !preg_match('~^https?://~i', $url)) { return; }
        // Many tile instances may be open. Throttle per instance.
        $now = time();
        $last = (int)$this->GetBuffer('NodeRedViewerPingLastSent');
        if ($last > 0 && $now - $last < 25) { return; }
        $this->SetBuffer('NodeRedViewerPingLastSent', (string)$now);
        try {
            $payload = json_encode(['instance' => $this->InstanceID], JSON_THROW_ON_ERROR);
            $context = stream_context_create(['http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nConnection: close\r\n",
                'content' => $payload,
                'timeout' => 0.5,
                'ignore_errors' => true
            ]]);
            // Fail open: if Node-RED is offline the normal WebFront works unchanged.
            @file_get_contents($url, false, $context);
        } catch (Throwable $e) {
            $this->SendDebug('Node-RED-Sichtbarkeit', $e->getMessage(), 0);
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if ($Message === VM_UPDATE || $Message === VM_DELETE) {
            if (!$this->HasLiveViewers()) { $this->DisableViewerSubscriptions(); return; }
            if ($Message === VM_UPDATE && array_key_exists(1, $Data) && !$Data[1]) { return; }
            if ($SenderID === $this->ConfigInteger('Raining')) { $this->SetBuffer('RainCache', ''); }
            if ($this->ReadPropertyInteger('View') === 18) {
                $historySourceIDs = array_column($this->PresenceHistorySources(), 'id');
                if (in_array($SenderID, $historySourceIDs, true)) {
                    $this->SetBuffer('PresenceHistoryCache', '');
                }
            }
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
        $form['elements'][0]['caption'] = 'Hausstatus v' . $this->HausstatusReleaseVersion() . ' – Einstellungen';
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
        foreach (['PresenceSettings' => 1, 'DoorSettings' => 2, 'LightSettings' => 5, 'CinemaSettings' => 6, 'BedroomMediaSettings' => 6, 'HeaterSettings' => 14,
            'DeviceSettings' => 4, 'PVSettings' => 7, 'OutdoorSettings' => 11, 'MotionSettings' => 8,
            'TemperatureSettings' => 9, 'WeatherSettings' => 10, 'RoomDeviceSettings' => 12, 'WaterbedSettings' => 12, 'NetworkSettings' => 13,
            'PresenceHistorySettings' => 18, 'LaundrySettings' => 27] as $name => $sectionView) {
            $fields[$name] = ['visible' => $own, 'expanded' => $own && ($view === $sectionView || ($name === 'PresenceSettings' && $view === 18))];
        }
        $fields['OverviewSettings'] = ['visible' => $own && $view === 0];
        $fields['TileCreationSettings'] = ['visible' => $own, 'expanded' => false];
        $fields['DisplaySettings'] = ['visible' => $own];
        $fields['NodeRedViewerSettings'] = ['visible' => $own, 'expanded' => false];
        $fields['SettingsCategoryBasics'] = ['visible' => true];
        foreach (['SettingsCategorySecurity', 'SettingsCategoryRooms', 'SettingsCategoryMedia',
            'SettingsCategoryHousehold', 'SettingsCategorySensors', 'SettingsCategoryNetwork'] as $group) {
            $fields[$group] = ['visible' => $own];
        }
        $fields['SettingsCategorySystem'] = ['visible' => $own || $view === 30];
        $fields['RoomFilter'] = ['visible' => $view === 12];
        $fields['NetworkSettings'] = ['visible' => $own,
            'expanded' => $own && in_array($view, [13, 20, 21, 22, 23, 24, 31], true)];
        $fields['ExtraTileSettings'] = ['visible' => true, 'expanded' => $view === 19];
        $fields['DockerSettings'] = ['visible' => $own, 'expanded' => $own && in_array($view, [25, 31], true)];
        $fields['ModuleUpdateSettings'] = ['visible' => $view === 30, 'expanded' => $view === 30];
        $caption = 'Eigene Einstellungen: Die Quellen und Bedienoptionen werden in dieser Instanz festgelegt.';
        if (!$own) {
            if ($source === $this->InstanceID || !IPS_InstanceExists($source)
                || IPS_GetInstance($source)['ModuleInfo']['ModuleID'] !== '{9E33E109-4881-4E78-9906-38CAC2F1E210}'
                || IPS_GetProperty($source, 'ConfigSource') !== 0) {
                $caption = 'Die gewählte Konfigurationsquelle ist ungültig. Eine Hausstatus-Instanz mit eigenen Einstellungen wählen oder die Auswahl leeren.';
            } else {
                $caption = 'Gemeinsame Einstellungen aus ' . IPS_GetName($source) . ' (ID ' . $source
                    . '). Quellen und Bedienoptionen dort ändern. Die Standardquellen hier kommen aus der Master-Instanz. Zusätzliche Variablen unter Punkt 2 werden nur in dieser Kachel gespeichert.';
            }
        }
        $fields['SettingsOrigin'] = ['caption' => $caption];
        return $fields;
    }

    private function FreshState(): array
    {
        $this->SetBuffer('MotionCache', '');
        $this->SetBuffer('RainCache', '');
        $this->SetBuffer('PresenceHistoryCache', '');
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

    /**
     * Read-only diagnostics for the living room and dining controls.
     * Use after pressing each problematic button. No RequestAction calls.
     */
    public function GetLightControlDiagnostics(): string
    {
        $module = '{9E33E109-4881-4E78-9906-38CAC2F1E210}';
        $source = $this->ReadPropertyInteger('ConfigSource');
        $owner = $source > 0 ? $source : $this->InstanceID;
        if (!IPS_InstanceExists($owner) || IPS_GetInstance($owner)['ModuleInfo']['ModuleID'] !== $module) {
            throw new RuntimeException('Hausstatus-Datenquelle nicht gefunden.');
        }

        $variableInfo = function (int $id): array {
            if ($id <= 0 || !IPS_VariableExists($id)) {
                return ['id' => $id, 'valid' => false, 'note' => 'Keine gueltige Variable'];
            }
            $variable = IPS_GetVariable($id);
            $type = (int)$variable['VariableType'];
            $action = (int)$variable['VariableAction'];
            $custom = (int)$variable['VariableCustomAction'];
            return [
                'id' => $id,
                'valid' => true,
                'name' => IPS_GetName($id),
                'parent' => IPS_GetParent($id),
                'type' => $type,
                'value' => GetValue($id),
                'standardAction' => $action,
                'customAction' => $custom,
                'effectiveAction' => $custom === 1 ? 0 : ($custom > 0 ? $custom : $action),
                'canToggle' => $type === 0 && $custom !== 1 && ($custom > 0 || $action > 0)
            ];
        };

        $diningIDs = ['state' => 0, 'brightness' => 0];
        $diningProblem = '';
        try {
            $diningIDs = $this->DiningSources();
        } catch (Throwable $e) {
            $diningProblem = $e->getMessage();
        }

        $scriptID = $this->ConfigInteger('LightCommandScript');
        $instances = [];
        foreach (IPS_GetInstanceListByModuleID($module) as $id) {
            if ($id !== $owner && (int)IPS_GetProperty($id, 'ConfigSource') !== $owner) {
                continue;
            }
            $view = (int)IPS_GetProperty($id, 'View');
            $part = (int)IPS_GetProperty($id, 'HomepagePart');
            if (!in_array($view, [0, 5, 17], true) || ($view === 0 && !in_array($part, [0, 4], true))) {
                continue;
            }
            $instances[] = [
                'id' => $id,
                'name' => IPS_GetName($id),
                'view' => $view,
                'homepagePart' => $part,
                'active' => (bool)IPS_GetProperty($id, 'ActiveView'),
                'hidden' => (bool)IPS_GetObject($id)['ObjectIsHidden'],
                'lastCommand' => $id === $this->InstanceID ? $this->GetBuffer('LastCommandReceipt') : '(Diagnose auf dieser Kachel aufrufen)',
                'lastError' => $id === $this->InstanceID ? $this->GetBuffer('LastCommandError') : '(Diagnose auf dieser Kachel aufrufen)'
            ];
        }

        return json_encode([
            'moduleVersion' => $this->HausstatusReleaseVersion(),
            'sourceInstance' => $owner,
            'lightEnabled' => $this->ConfigBoolean('LightEnabled'),
            'rgbSources' => ['living' => $variableInfo($this->ConfigInteger('LivingColor')),
                'dining' => $variableInfo($this->ConfigInteger('DiningColor')),
                'bedroom' => $variableInfo($this->ConfigInteger('BedroomColor'))],
            'livingRoom' => [
                'switch' => $variableInfo($this->ConfigInteger('LightState')),
                'brightness' => $variableInfo($this->ConfigInteger('Brightness')),
                'commandScript' => $scriptID,
                'scriptExists' => $scriptID > 0 && IPS_ScriptExists($scriptID),
                'mode' => $scriptID > 0 ? 'Bedienskript' : 'Variablenaktion'
            ],
            'diningEnabled' => $this->ConfigBoolean('DiningEnabled'),
            'dining' => [
                'switch' => $variableInfo((int)$diningIDs['state']),
                'brightness' => $variableInfo((int)$diningIDs['brightness']),
                'sourceProblem' => $diningProblem
            ],
            'instances' => $instances,
            'info' => 'Nur Bestandsaufnahme. Keine Schaltaktion ausgefuehrt.'
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
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
        if (!in_array($Ident, ['Refresh', 'PageRead', 'ViewerPing', 'WetterComRefresh'], true)) {
            $this->SetBuffer('LastCommandReceipt', date('c') . ' | ' . $Ident . ' | ' . get_debug_type($Value)
                . (in_array($Ident, ['Light', 'Dining', 'Brightness', 'DiningBrightness'], true)
                    ? ' | value=' . json_encode($Value, JSON_INVALID_UTF8_SUBSTITUTE) : ''));
        }
        if (preg_match('/^RoomValue:([1-9][0-9]{0,9})$/D', $Ident, $match) === 1) {
            $Value = ['id' => (int)$match[1], 'value' => $Value]; $Ident = 'RoomValue';
        }
        if (!$this->ReadPropertyBoolean('ActiveView')) { throw new RuntimeException('Diese Ansicht ist pausiert. Bitte die gemeinsame Hausstatus-Kachel verwenden.'); }
        if (in_array($Ident, ['ModuleUpdateCheck', 'ModuleUpdateInstall'], true)) {
            $this->HandleHausstatusModuleUpdate($Ident, $Value);
            return;
        }
        if ($Ident === 'ViewerPing') {
            $this->SetBuffer('LastViewerSeen', (string)time());
            $subscriptionsStarted = $this->EnableViewerSubscriptions();
            $this->ForwardViewerPingToNodeRed();
            $this->ExpireShieldOnViewerPing();
            if ($subscriptionsStarted || ($this->HasTemperatureView() && count($this->HeatingCenterIDs()) === 1)) { $this->Refresh(); }
            return;
        }
        // A remote weather fetch must never delay loading the HTML tile.
        if ($Ident === 'WetterComRefresh') {
            $view = $this->ReadPropertyInteger('View');
            $part = $this->ReadPropertyInteger('HomepagePart');
            if ($view === 0 && preg_match('/^SVHSHomePart_([1-9][0-9]*)$/D',
                (string)(IPS_GetObject($this->InstanceID)['ObjectIdent'] ?? ''), $matches) === 1) {
                $part = (int)$matches[1];
            }
            if (!in_array($view, [10, 16], true)
                && !($view === 0 && ($part === 0 || $part === 8))) { return; }
            try {
                $previous = $this->WetterComForecastState();
                $updated = $this->WetterComForecastState(true);
                if ($previous !== $updated) {
                    $this->UpdateVisualizationValue(json_encode(['wetterComUpdate' => $updated],
                        JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
                }
            } catch (Throwable $e) {
                $this->SendDebug('WetterCom', $e->getMessage(), 0);
            }
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
        if (!in_array($Ident, ['Light', 'Brightness', 'LivingColor', 'DiningColor', 'ShieldPower_wohnzimmer', 'ShieldPower_ankleidezimmer', 'ShieldPower_schlafzimmer', 'ShieldPower_philips', 'PhilipsHdmi3', 'Cinema', 'CinemaSource', 'CinemaVolume', 'DoorConfirm', 'DoorOpen', 'DoorPermission',
            'AwningPosition', 'AwningAuto', 'RoofPosition', 'RoofAuto', 'RoofNight', 'RoomValue', 'HeatingCenterMode', 'Dining', 'DiningBrightness', 'HeosSelection', 'HeosRadio', 'HeosNAS', 'HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan', 'DreoPower', 'DreoMode', 'DreoPreset', 'DreoOscillation', 'DreoDisplay', 'BedroomAvrPower', 'BedroomAvrZone', 'BedroomAvrSource', 'BedroomAvrVolume', 'BedroomAvrMute', 'BedroomSubwoofer', 'DockerControl'], true)) {
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
            if (in_array($Ident, ['RoomValue', 'DoorConfirm', 'DoorOpen', 'DockerControl'], true) && is_string($Value)) {
                if (strlen($Value) > 32768) { throw new InvalidArgumentException('Bedienbefehl ist zu groß.'); }
                $Value = json_decode($Value, true, 32, JSON_THROW_ON_ERROR);
            }
            if ($Ident === 'HeatingCenterMode') {
                $this->SetHeatingCenterMode($Value);
            } elseif ($Ident === 'PhilipsHdmi3') {
                $this->SetPhilipsHdmi3($Value);
            } elseif (str_starts_with($Ident, 'ShieldPower_')) {
                $this->SetShieldPower(substr($Ident, 12), $Value);
            } elseif (isset(self::BEDROOM_DEFAULTS[$Ident])) {
                $this->SetBedroomMedia($Ident, $Value);
            } elseif ($Ident === 'DockerControl') {
                $this->SetDockerControl($Value);
            } elseif (in_array($Ident, ['DreoPower', 'DreoMode', 'DreoPreset', 'DreoOscillation', 'DreoDisplay'], true)) {
                $this->SetDreo($Ident, $Value);
            } elseif (in_array($Ident, ['HeaterSwitch', 'HeaterMinimum', 'HeaterMaximum', 'HeaterManual', 'HeaterWeekplan'], true)) {
                $this->SetHeater($Ident, $Value);
            } elseif (in_array($Ident, ['Dining', 'DiningBrightness'], true)) {
                $this->SetDining($Ident, $Value);
            } elseif (in_array($Ident, ['LivingColor', 'DiningColor'], true)) {
                $this->SetLightColor($Ident, $Value);
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


    // Update only the installed Hausstatus library, never every installed module.
    private function HausstatusModuleControl(): array
    {
        $libraryID = '{2AC4EF61-E417-423C-A0E7-A0A797A0357A}';
        if (!IPS_LibraryExists($libraryID)) {
            throw new RuntimeException('Die Hausstatus-Bibliothek ist nicht installiert.');
        }
        foreach (IPS_GetModuleListByType(0) as $moduleID) {
            $module = IPS_GetModule($moduleID);
            if (!in_array((string)($module['ModuleName'] ?? ''), ['Module Control', 'Modules'], true)) {
                continue;
            }
            foreach (IPS_GetInstanceListByModuleID($moduleID) as $controlID) {
                foreach (MC_GetModuleList($controlID) as $installedName) {
                    if (!in_array($installedName, ['Hausstatus', 'Symcon-Hausstatus'], true)) {
                        continue;
                    }
                    // Refuse a demonstrably different repository, if its URL is supplied.
                    $info = MC_GetModuleRepositoryInfo($controlID, $installedName);
                    $encoded = strtolower(json_encode($info, JSON_INVALID_UTF8_SUBSTITUTE) ?: '');
                    if (str_contains($encoded, 'github.com/') &&
                        !str_contains($encoded, 'github.com/powermaniaxx/symcon-hausstatus')) {
                        throw new RuntimeException('Das installierte Repository stimmt nicht mit Hausstatus überein.');
                    }
                    return [$controlID, $installedName];
                }
            }
        }
        throw new RuntimeException('Hausstatus wurde in Module Control nicht gefunden.');
    }

    private function HandleHausstatusModuleUpdate(string $action, mixed $value): void
    {
        if ($this->ReadPropertyInteger('View') !== 30 ||
            !$this->ReadPropertyBoolean('ModuleUpdateEnabled')) {
            throw new RuntimeException('Modulupdates sind für diese Kachel nicht freigegeben.');
        }
        $lock = 'SVHSModuleUpdate';
        if (!IPS_SemaphoreEnter($lock, 1000)) {
            throw new RuntimeException('Ein Update-Vorgang läuft bereits.');
        }
        try {
            [$controlID, $module] = $this->HausstatusModuleControl();
            if ($action === 'ModuleUpdateCheck') {
                if (!function_exists('MC_IsModuleUpdateAvailable')) {
                    throw new RuntimeException('Die Updateprüfung wird von dieser Symcon-Version nicht unterstützt.');
                }
                $available = MC_IsModuleUpdateAvailable($controlID, $module);
                $this->SetBuffer('ModuleUpdateStatus', json_encode([
                    'checked' => time(), 'available' => $available,
                    'token' => $available ? bin2hex(random_bytes(16)) : '',
                    'message' => $available ? 'Update verfügbar. Installation bestätigen.' : 'Kein Update verfügbar.'
                ], JSON_THROW_ON_ERROR));
                $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'state' => $this->BaseState()],
                    JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
                return;
            }
            $status = json_decode($this->GetBuffer('ModuleUpdateStatus'), true);
            if (!is_string($value) || !is_array($status) ||
                !($status['available'] ?? false) ||
                (int)($status['checked'] ?? 0) < time() - 180 ||
                !hash_equals((string)($status['token'] ?? ''), $value)) {
                throw new RuntimeException('Update-Freigabe abgelaufen. Bitte erneut prüfen.');
            }
            $this->SetBuffer('ModuleUpdateStatus', json_encode([
                'checked' => time(), 'available' => false, 'token' => '',
                'message' => 'Update gestartet. Nach Abschluss die System-Kachel neu laden.'
            ], JSON_THROW_ON_ERROR));
            $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'state' => $this->BaseState()],
                JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
            if (!MC_UpdateModule($controlID, $module)) {
                throw new RuntimeException('Module Control konnte das Update nicht installieren.');
            }
            IPS_LogMessage('Hausstatus', 'Hausstatus-Modulupdate über System-Kachel ausgeführt.');
        } catch (Throwable $e) {
            $this->SetBuffer('ModuleUpdateStatus', json_encode([
                'checked' => time(), 'available' => false, 'token' => '',
                'message' => 'Fehler: ' . $e->getMessage()
            ], JSON_INVALID_UTF8_SUBSTITUTE));
            $this->UpdateVisualizationValue(json_encode(['commandDone' => true, 'error' => $e->getMessage(),
                'state' => $this->BaseState()], JSON_INVALID_UTF8_SUBSTITUTE));
        } finally {
            IPS_SemaphoreLeave($lock);
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

    private function ConfigInteger(string $name): int
    {
        $id = (int)$this->ConfigValue($name);
        // Google Home liefert den richtigen Hausstatus in 52726.
        // Bestehende Zuordnungen auf 12936 sowie leere Zuordnungen nutzen 52726.
        // Andere bewusst gewaehlte Anwesenheitsquellen bleiben erhalten.
        if ($name === 'Presence' && ($id === 0 || $id === 12936)) { return 52726; }
        return $id === 0 ? (['HeatingProfile' => 31603, 'HeosRadio' => 14121, 'HeosNAS' => 45081][$name] ?? 0) : $id;
    }
    private function ConfigBoolean(string $name): bool { return (bool)$this->ConfigValue($name); }
    private function ConfigString(string $name): string { return (string)$this->ConfigValue($name); }
    private function HasMotionView(): bool
    {
        $view = $this->CurrentView();
        // Movement remains available on the homepage, including its dedicated split tile.
        return $view === 8 || $view === 0;
    }
    private function HasTemperatureView(): bool
    {
        $view = $this->CurrentView();
        return $view === 9 || ($view === 0 && !$this->ConfigBoolean('SeparateDetails'));
    }

    // These rows belong to the individual tile, never to the shared master settings.
    // This allows two instances with the same preset to show different custom variables.
    private function ExtraTileVariables(): array
    {
        $raw = json_decode($this->ReadPropertyString('ExtraTileVariables'), true);
        if (!is_array($raw)) { return []; }
        $rows = [];
        $seen = [];
        foreach ($raw as $row) {
            if (!is_array($row)) { continue; }
            $id = (int)($row['Variable'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) { continue; }
            $seen[$id] = true;
            $name = trim((string)($row['Name'] ?? ''));
            if ($name === '' && IPS_VariableExists($id)) { $name = IPS_GetName($id); }
            if ($name === '') { $name = 'Variable ' . $id; }
            $rows[] = [
                'id' => $id,
                'name' => mb_substr($name, 0, 80),
                'value' => $this->Read($id),
                'type' => IPS_VariableExists($id) ? (int)IPS_GetVariable($id)['VariableType'] : -1
            ];
            if (count($rows) >= 32) { break; }
        }
        return $rows;
    }

    private function ExtraTileVariableIDs(): array
    {
        return array_column($this->ExtraTileVariables(), 'id');
    }

    private function SourceNames(): array
    {
        $views = [1 => ['Presence', 'Alarm'], 2 => ['Lock', 'DoorContact', 'DoorPermission', 'DoorControl', 'DoorOpened', 'DoorClosed'],
            4 => ['BatteryWarnings'], 5 => ['LightState', 'Brightness'], 18 => ['Presence', 'SvenPresence', 'SusiPresence', 'Alarm'], 19 => [],
            26 => ['Lock', 'DoorContact', 'DoorPermission', 'DoorControl', 'Alarm'],
            29 => ['PVPower', 'PVEnergy', 'BatteryWarnings'],
            27 => ['WasherPower', 'DryerPower', 'WasherRunning', 'DryerRunning'],
            28 => ['Presence', 'Alarm', 'Lock', 'DoorContact', 'DoorPermission', 'DoorControl', 'DoorOpened', 'DoorClosed'],
            6 => ['CinemaState', 'CinemaControl', 'CinemaSource', 'CinemaVolume', 'HeosSelection', 'HeosRadio', 'HeosNAS', 'HeosStatus'], 7 => ['PVPower', 'PVEnergy'], 8 => [], 9 => ['HeatingProfile'],
            10 => ['Weather', 'OutdoorTemperature', 'FeelsLikeTemperature', 'TodayLowTemperature', 'TodayHighTemperature', 'RainProbability', 'OutdoorHumidity', 'Wind', 'Rain', 'Warning', 'Sunrise', 'Sunset'],
            11 => ['AwningPosition', 'AwningAuto', 'AwningStatus', 'RoofPosition', 'RoofAuto', 'RoofNight', 'RoofStatus'], 14 => [], 15 => ['PVPower', 'PVEnergy'], 16 => ['Weather', 'OutdoorTemperature', 'FeelsLikeTemperature', 'TodayLowTemperature', 'TodayHighTemperature', 'RainProbability', 'OutdoorHumidity', 'Wind', 'Rain', 'Warning', 'Sunrise', 'Sunset'], 17 => [], 12 => ['HeosStatus', 'HeatingProfile'], 13 => ['NetworkConnection', 'NetworkDownload', 'NetworkUpload', 'NetworkDownloadUsage', 'NetworkUploadUsage', 'NetworkActiveDevices', 'NetworkDevices', 'NetworkUptime', 'NetworkModel', 'NetworkFirmware']];
        $view = $this->CurrentView();
        if (in_array($view, [13, 20, 21, 22, 23, 24], true)) {
            return $this->NetworkViewFields($view);
        }
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


    // Testversion: öffentliche wetter.com-Tagesprognose, ohne API.
    // Nicht garantiert: wetter.com kann Seitenstruktur oder Zugriffsregeln ändern.
    private function WetterComForecastState(bool $refresh = false): array
    {
        $now = time();
        $stored = json_decode($this->GetBuffer('WetterComForecastCache'), true);
        if (!is_array($stored)) { $stored = []; }
        $lastTry = (int) ($stored['lastTry'] ?? 0);
        // Initial rendering and variable updates only read the cached forecast.
        if (!$refresh || ($lastTry > 0 && $now - $lastTry < (empty($stored['days']) ? 600 : 3600))) {
            return $this->WetterComVisibleCache($stored, $now);
        }
        // Reserve this retry period before contacting wetter.com to limit
        // duplicate fetches across concurrent requests to this instance.
        $stored['lastTry'] = $now;
        $this->SetBuffer('WetterComForecastCache', json_encode($stored, JSON_THROW_ON_ERROR));
        $url = 'https://www.wetter.com/wetter_aktuell/wettervorhersage/16_tagesvorhersage/deutschland/leipzig/moelkau/DE0006194064.html';
        $html = false;
        try {
            if (function_exists('curl_init')) {
                $curl = curl_init($url);
                curl_setopt_array($curl, [
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; SymconHausstatusPreview/1.0)',
                    CURLOPT_HTTPHEADER => ['Accept: text/html', 'Accept-Language: de-DE,de;q=0.9']
                ]);
                $response = curl_exec($curl);
                $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
                curl_close($curl);
                if (is_string($response) && $status === 200 && strlen($response) > 1000) { $html = $response; }
            } else {
                $context = stream_context_create(['http' => ['timeout' => 12, 'header' => "User-Agent: Mozilla/5.0\r\nAccept-Language: de-DE\r\n"]]);
                $response = @file_get_contents($url, false, $context);
                if (is_string($response) && strlen($response) > 1000) { $html = $response; }
            }
        } catch (Throwable $e) { $this->SendDebug('WetterCom', $e->getMessage(), 0); }
        if ($html === false) {
            $stored['error'] = 'Abruf nicht möglich';
        } else {
            $plain = html_entity_decode(strip_tags(preg_replace('~<(script|style)\\b[^>]*>.*?</\\1>~is', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $plain = preg_replace('/\\s+/u', ' ', $plain);
            $days = [];
            $pattern = '/\\b(?:MO|DI|MI|DO|FR|SA|SO)\\s+(\\d{1,2})\\.(\\d{1,2})\\.\\s+(-?\\d{1,2})\\s*°\\s*\\/\\s*(-?\\d{1,2})\\s*°/iu';
            preg_match_all($pattern, $plain, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $key = sprintf('%02d.%02d.', (int) $match[1], (int) $match[2]);
                $high = (int) $match[3];
                $low = (int) $match[4];
                if ($low > $high || $high > 60 || $low < -60) { continue; }
                if (!isset($days[$key])) { $days[$key] = ['high' => $high, 'low' => $low]; }
            }
            // Die kompakte Tagesübersicht enthält Beschreibung und Regenwahrscheinlichkeit.
            // Nur Werte übernehmen, wenn sie direkt hinter dem passenden Tagesdatum stehen.
            $detailPattern = '/\\b(?:MO|DI|MI|DO|FR|SA|SO)\\s+(\\d{1,2})\\.(\\d{1,2})\\.\\s+(-?\\d{1,2})\\s*°\\s*\\/\\s*(-?\\d{1,2})\\s*°\\s+([^%]{3,110}?)\\s+(\\d{1,3})\\s*%/iu';
            preg_match_all($detailPattern, $plain, $details, PREG_SET_ORDER);
            foreach ($details as $detail) {
                $key = sprintf('%02d.%02d.', (int) $detail[1], (int) $detail[2]);
                $high = (int) $detail[3]; $low = (int) $detail[4]; $rain = (int) $detail[6];
                $description = trim((string) $detail[5]);
                if (!isset($days[$key]) || $days[$key]['high'] !== $high || $days[$key]['low'] !== $low
                    || $rain < 0 || $rain > 100 || mb_strlen($description) > 100) { continue; }
                if (preg_match('/(?:gefühlt|km\\/h|Uhr|l\\/m)/iu', $description)) { continue; }
                $days[$key]['rainProbability'] = $rain;
                $days[$key]['description'] = $description;
            }
            $tz = new DateTimeZone('Europe/Berlin');
            $today = (new DateTimeImmutable('now', $tz))->format('d.m.');
            $tomorrow = (new DateTimeImmutable('tomorrow', $tz))->format('d.m.');
            if (isset($days[$today]) && isset($days[$tomorrow])) {
                $stored['days'] = ['today' => $days[$today], 'tomorrow' => $days[$tomorrow]];
                $stored['date'] = $today;
                $stored['updated'] = $now;
                unset($stored['error']);
            } else {
                $stored['error'] = 'Tageswerte nicht erkannt';
            }
        }
        $this->SetBuffer('WetterComForecastCache', json_encode($stored, JSON_THROW_ON_ERROR));
        return $this->WetterComVisibleCache($stored, $now);
    }

    private function WetterComVisibleCache(array $stored, int $now): array
    {
        // Keine Tageswerte von gestern oder älter als drei Stunden anzeigen.
        $today = (new DateTimeImmutable('now', new DateTimeZone('Europe/Berlin')))->format('d.m.');
        if (($stored['date'] ?? '') !== $today || $now - (int) ($stored['updated'] ?? 0) > 10800) {
            return ['available' => false, 'error' => (string) ($stored['error'] ?? 'Keine aktuellen Vorhersagedaten')];
        }
        return ['available' => true, 'today' => $stored['days']['today'], 'tomorrow' => $stored['days']['tomorrow'],
            'updated' => (int) $stored['updated'], 'source' => 'wetter.com · Leipzig-Mölkau (Testversion)'];
    }

    private function BatteryWarningDetailsID(): int
    {
        $countID = $this->ConfigInteger('BatteryWarnings');
        if ($countID <= 0 || !IPS_VariableExists($countID)) { return 0; }
        $parentID = IPS_GetParent($countID);
        $detailsID = @IPS_GetObjectIDByName('Gerätewarnungen', $parentID);
        if ($detailsID === false || !IPS_VariableExists((int)$detailsID)) { return 0; }
        return IPS_GetVariable((int)$detailsID)['VariableType'] === 3 ? (int)$detailsID : 0;
    }

    private function State(): array
    {
        if ($this->CurrentView() === 30) {
            $status = json_decode($this->GetBuffer('ModuleUpdateStatus'), true);
            return [
                'View' => 30, 'Controls' => [],
                'ModuleUpdate' => [
                    'enabled' => $this->ReadPropertyBoolean('ModuleUpdateEnabled'),
                    'message' => is_array($status) ? (string)($status['message'] ?? '') : '',
                    'available' => is_array($status) && !empty($status['available']) &&
                        (int)($status['checked'] ?? 0) >= time() - 180,
                    'token' => is_array($status) ? (string)($status['token'] ?? '') : '',
                    'checked' => is_array($status) ? (int)($status['checked'] ?? 0) : 0
                ]
            ];
        }
        $state = ['View' => $this->CurrentView(), 'SeparateDetails' => $this->ConfigBoolean('SeparateDetails')];
        // The preserved homepage tile's fixed ident defines its visual section.
        // This keeps existing tiles correct even after stale property values.
        $homepagePart = $this->ReadPropertyInteger('HomepagePart');
        if ($state['View'] === 0) {
            $ident = IPS_GetObject($this->InstanceID)['ObjectIdent'] ?? '';
            if (preg_match('/^SVHSHomePart_([1-9][0-9]*)$/D', $ident, $match) === 1) {
                $homepagePart = (int)$match[1];
            }
        }
        $state['HomepagePart'] = $homepagePart;
        $part = $state['View'] === 0 ? $state['HomepagePart'] : 0;
        $shows = static fn(array $parts): bool => $part === 0 || in_array($part, $parts, true);
        foreach (array_keys(self::SOURCES) as $name) { $state[$name] = $this->Read($this->ConfigInteger($name)); }
        $detailsID = $this->BatteryWarningDetailsID();
        $state['BatteryWarningDetails'] = $detailsID > 0 ? (string)GetValueString($detailsID) : '';
        $state['InlinePages'] = $this->UsesInlinePages();
        $state['NavigationRooms'] = $state['InlinePages'] ? $this->NavigationRooms() : [];
        $state['PageRoom'] = $this->CurrentRoom();
        $state['DisplaySettings'] = $this->DisplaySettings();
        $state['Network'] = $this->NetworkState();
        $state['Docker'] = $this->DockerState();
        if ($state['View'] === 31) {
            $upsSources = ['power' => 33763, 'load' => 53624, 'battery' => 21012, 'runtime' => 23009, 'status' => 38996, 'inputVoltage' => 46284, 'batteryVoltage' => 48316, 'dataStatus' => 54068];
            $ups = [];
            foreach ($upsSources as $key => $upsID) {
                $ups[$key] = IPS_VariableExists($upsID) ? GetValue($upsID) : null;
            }
            $state['UPS'] = $ups;
        }
        $state['Laundry'] = $shows([3]) ? $this->LaundryState() : null;
        $state['HeatingProfileItem'] = $shows([9]) ? $this->HeatingProfileItem() : null;
        $state['DoorReason'] = $this->DoorReason();
        $state['Motion'] = $shows([10]) && $this->HasMotionView() ? $this->MotionState() : null;
        $state['RainHistory'] = $shows([8]) && $this->HasRainView() ? $this->RainHistoryState() : null;
        $state['WetterComForecast'] = $shows([8]) && in_array($this->CurrentView(), [0, 10, 16], true) ? $this->WetterComForecastState() : null;
        $state['WeatherAvailable'] = [];
        foreach (['OutdoorTemperature', 'FeelsLikeTemperature', 'TodayLowTemperature', 'TodayHighTemperature', 'RainProbability', 'OutdoorHumidity'] as $weatherName) {
            $weatherID = $this->ConfigInteger($weatherName);
            $state['WeatherAvailable'][$weatherName] = $weatherID > 0 && IPS_VariableExists($weatherID);
        }
        $state['PresenceHistory'] = $this->CurrentView() === 18 ? $this->PresenceHistoryState() : null;
        $state['ExtraTileVariables'] = $this->ExtraTileVariables();
        $state['Outdoor'] = $shows([8]) && in_array($this->CurrentView(), [0, 11], true) ? $this->OutdoorState() : null;
        $state['Shield'] = $shows([5,6,7]) ? $this->ShieldState() : null;
        $state['PhilipsTv'] = $shows([5]) && in_array($this->CurrentView(), [0, 6], true) ? $this->ShieldState('PhilipsTv') : null;
        $state['BedroomMedia'] = $shows([6]) && in_array($this->CurrentView(), [0, 6], true) ? $this->BedroomMediaState() : [];
        $state['LivingMediaStatus'] = $shows([5]) && in_array($this->CurrentView(), [0, 6], true) ? $this->LivingMediaStatus() : [];
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
        $state['RoomSections'] = $shows([11]) ? $this->RoomSections() : [];
        $state['WaterbedEnergy'] = $this->WaterbedEnergyState();
        $state['TemperatureRows'] = $shows([11]) ? $this->TemperatureRows() : [];
        $state['RoomSummary'] = [];
        if ($state['View'] === 12 && $state['PageRoom'] === '') {
            $state['RoomSummary'] = $this->RoomSummary($state['RoomSections'], $state['TemperatureRows']);
            $state['RoomSections'] = []; $state['TemperatureRows'] = [];
        }
        $state['SeparateHomepageTiles'] = $this->ConfigBoolean('SeparateHomepageTiles');
        $state['Heater'] = $shows([9]) && in_array($this->CurrentView(), [0, 14], true) ? $this->HeaterState() : null;
        $state['LightColors'] = [
            'living' => $shows([4]) && in_array($this->CurrentView(), [0, 5], true) ? $this->LightColorState('LivingColor') : null,
            'dining' => $shows([4]) && in_array($this->CurrentView(), [0, 5, 17], true) ? $this->LightColorState('DiningColor') : null
        ];
        $state['Dining'] = $shows([4]) && in_array($this->CurrentView(), [0, 5, 17], true)
            && ($this->ConfigInteger('DiningInstance') > 0 || $this->ConfigInteger('DiningState') > 0) ? $this->DiningState() : null;
        $state['PVInverters'] = $shows([2]) ? $this->PVInverters() : [];
        $state['PVDetailsTarget'] = in_array($this->CurrentView(), [0, 15, 29], true) ? $this->PVDetailsTarget() : 0;
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
