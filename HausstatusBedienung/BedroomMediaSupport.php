<?php
declare(strict_types=1);

trait HausstatusBedroomMediaSupport
{
    private const LIVING_MEDIA_STATUS = [
        'LivingRearHeight' => [13241, 'Rear Height'],
        'LivingSubwoofer' => [50165, 'Subwoofer Cinema 40'],
        'LivingSpeakers' => [40994, 'Surround + Front Lautsprecher'],
        'LivingFan' => [17569, 'Ventilator Cinema 40']
    ];
    private const BEDROOM_DEFAULTS = [
        'BedroomAvrPower' => 13490, 'BedroomAvrZone' => 47173,
        'BedroomAvrSource' => 33178, 'BedroomAvrVolume' => 18046,
        'BedroomAvrMute' => 41849, 'BedroomSubwoofer' => 20175
    ];

    private function CreateBedroomMedia(): void
    {
        $this->RegisterPropertyBoolean('BedroomMediaEnabled', true);
        foreach (self::BEDROOM_DEFAULTS as $name => $id) { $this->RegisterPropertyInteger($name, $id); }
        foreach (self::LIVING_MEDIA_STATUS as $name => [$id, $label]) { $this->RegisterPropertyInteger($name, $id); }
    }

    private function BedroomMediaIDs(): array
    {
        if (!in_array($this->CurrentView(), [0, 6], true)) { return []; }
        return array_map(fn(string $name): int => $this->ConfigInteger($name),
            array_merge(array_keys(self::BEDROOM_DEFAULTS), array_keys(self::LIVING_MEDIA_STATUS)));
    }

    private function BedroomMediaControl(string $name): ?array
    {
        if ($name === 'BedroomSubwoofer') { return null; }
        if (!$this->ConfigBoolean('BedroomMediaEnabled')) { return null; }
        $id = $this->ConfigInteger($name);
        if (!IPS_VariableExists($id)) { return null; }
        if ($name === 'BedroomAvrVolume') { return ['kind' => 'slider'] + $this->SliderPlan($id); }
        $type = $name === 'BedroomAvrSource' ? 1 : 0;
        if (!$this->HasAction($id, $type)) { return null; }
        if ($type === 1) {
            $options = $this->RoomOptions($id, 1);
            return $options ? ['kind' => 'enum', 'options' => $options] : null;
        }
        return ['kind' => 'bool', 'options' => [['value' => true, 'name' => 'Ein'], ['value' => false, 'name' => 'Aus']]];
    }

    private function BedroomMediaState(): array
    {
        $items = [];
        foreach (['BedroomAvrPower' => 'AVR Power', 'BedroomAvrZone' => 'MainZone',
            'BedroomAvrSource' => 'Quelle', 'BedroomAvrVolume' => 'Lautstärke',
            'BedroomAvrMute' => 'Stumm', 'BedroomSubwoofer' => 'Subwoofer'] as $name => $label) {
            $id = $this->ConfigInteger($name); $control = null; $note = '';
            try { $control = $this->BedroomMediaControl($name); }
            catch (Throwable $e) { $note = $e->getMessage(); }
            $items[] = ['id' => $id, 'name' => $label, 'command' => $name,
                'value' => $this->Read($id), 'control' => $control, 'note' => $note];
        }
        return $items;
    }

    private function LivingMediaStatus(): array
    {
        $items = [];
        foreach (self::LIVING_MEDIA_STATUS as $name => [$defaultID, $label]) {
            $items[] = ['name' => $label, 'value' => $this->Read($this->ConfigInteger($name))];
        }
        return $items;
    }

    private function SetBedroomMedia(string $name, mixed $value): void
    {
        if (!isset(self::BEDROOM_DEFAULTS[$name])) { throw new InvalidArgumentException('Unbekannte Schlafzimmer-Bedienaktion.'); }
        $control = $this->BedroomMediaControl($name);
        if ($control === null) { throw new RuntimeException('Schlafzimmer-Bedienung ist deaktiviert oder die Variable hat keine passende Aktion.'); }
        $id = $this->ConfigInteger($name);
        if ($control['kind'] === 'slider') { $this->SetSlider($id, $value, $control); return; }
        if (!in_array($value, array_column($control['options'], 'value'), true)) {
            throw new InvalidArgumentException('Wert ist nicht als Bedienoption zugelassen.');
        }
        if (!RequestAction($id, $value)) { throw new RuntimeException('Schlafzimmer-Bedienaktion fehlgeschlagen.'); }
    }
}
