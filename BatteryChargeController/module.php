<?php
declare(strict_types=1);

class BatteryChargeController extends IPSModule
{
    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('BatteryVariableID', 0);
        $this->RegisterPropertyInteger('ChargingActorVariableID', 0);
        $this->RegisterPropertyInteger('SwitchOnThreshold', 20);
        $this->RegisterPropertyInteger('SwitchOffThreshold', 80);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();
    }
}
