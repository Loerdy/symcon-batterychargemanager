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
        $this->RegisterPropertyBoolean('ShowBatteryLevel', false);
        $this->RegisterPropertyBoolean('ShowChargingActorState', false);

        $this->RegisterVariableInteger('SwitchOnThreshold', 'Einschaltschwelle', '~Intensity.100', 10);
        $this->EnableAction('SwitchOnThreshold');
        $this->RegisterVariableInteger('SwitchOffThreshold', 'Ausschaltschwelle', '~Intensity.100', 20);
        $this->EnableAction('SwitchOffThreshold');
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        if (!$this->unregisterMonitoredVariableMessages()) {
            return;
        }

        $this->MaintainVariable('BatteryLevel', 'Batteriestand', VARIABLETYPE_INTEGER, '~Battery.100', 30, $this->ReadPropertyBoolean('ShowBatteryLevel'));
        $this->MaintainVariable('ChargingActorState', 'Ladeaktor', VARIABLETYPE_BOOLEAN, '', 40, $this->ReadPropertyBoolean('ShowChargingActorState'));
        $this->SetValue('SwitchOnThreshold', $this->ReadPropertyInteger('SwitchOnThreshold'));
        $this->SetValue('SwitchOffThreshold', $this->ReadPropertyInteger('SwitchOffThreshold'));

        if (!$this->validateConfiguration()) {
            return;
        }

        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        $registrations = [
            [$batteryVariableID, VM_UPDATE],
            [$batteryVariableID, VM_DELETE],
            [$chargingActorVariableID, VM_DELETE]
        ];
        if ($this->ReadPropertyBoolean('ShowChargingActorState')) {
            $registrations[] = [$chargingActorVariableID, VM_UPDATE];
        }
        foreach ($registrations as [$senderID, $message]) {
            if (!$this->RegisterMessage($senderID, $message)) {
                $this->unregisterMonitoredVariableMessages();
                $this->configurationError(IS_EBASE, 'Die konfigurierten Variablen konnten nicht überwacht werden.');
                return;
            }
        }
        $this->SetStatus(IS_ACTIVE);

        $this->updateChargingActorState();
        $this->evaluateBatteryLevel();
    }

    public function RequestAction($Ident, $Value): void
    {
        if (!in_array($Ident, ['SwitchOnThreshold', 'SwitchOffThreshold'], true)) {
            $message = 'Unbekannter Ident: ' . (string) $Ident;
            $this->SendDebug('RequestAction', $message, 0);
            $this->LogMessage($message, KL_ERROR);
            return;
        }
        if (!is_int($Value)) {
            $message = 'Der Schwellwert muss eine Ganzzahl sein.';
            $this->SendDebug('ThresholdAction', $message, 0);
            $this->LogMessage($message, KL_ERROR);
            return;
        }
        if ($Value < 0 || $Value > 100) {
            $message = 'Der Schwellwert muss zwischen 0 und 100 liegen.';
            $this->SendDebug('ThresholdAction', $message, 0);
            $this->LogMessage($message, KL_WARNING);
            return;
        }

        $switchOnThreshold = $Ident === 'SwitchOnThreshold' ? $Value : $this->ReadPropertyInteger('SwitchOnThreshold');
        $switchOffThreshold = $Ident === 'SwitchOffThreshold' ? $Value : $this->ReadPropertyInteger('SwitchOffThreshold');
        if ($switchOnThreshold >= $switchOffThreshold) {
            $message = 'Die Einschaltschwelle muss kleiner als die Ausschaltschwelle sein.';
            $this->SendDebug('ThresholdAction', $message, 0);
            $this->LogMessage($message, KL_WARNING);
            return;
        }

        if (!IPS_SetProperty($this->InstanceID, $Ident, $Value)) {
            throw new RuntimeException('Der Schwellwert konnte nicht gespeichert werden.');
        }
        if (!IPS_ApplyChanges($this->InstanceID)) {
            throw new RuntimeException('Der geänderte Schwellwert konnte nicht übernommen werden.');
        }
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');

        if ($Message === VM_UPDATE && $SenderID === $batteryVariableID) {
            $this->evaluateBatteryLevel();
            return;
        }
        if ($Message === VM_UPDATE && $SenderID === $chargingActorVariableID) {
            $this->updateChargingActorState();
            return;
        }

        if ($Message !== VM_DELETE) {
            return;
        }
        if ($SenderID === $batteryVariableID) {
            $this->SetStatus(IS_EBASE);
            $this->SendDebug('Configuration', 'Die konfigurierte Batterievariable wurde gelöscht.', 0);
            return;
        }
        if ($SenderID === $chargingActorVariableID) {
            $this->SetStatus(IS_EBASE);
            $this->SendDebug('Configuration', 'Der konfigurierte Ladeaktor wurde gelöscht.', 0);
        }
    }

    private function unregisterMonitoredVariableMessages(): bool
    {
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if (!in_array($message, [VM_UPDATE, VM_DELETE], true)) {
                    continue;
                }
                if (!$this->UnregisterMessage((int) $senderID, $message)) {
                    $this->configurationError(IS_EBASE, 'Eine bisher überwachte Variable konnte nicht abgemeldet werden.');
                    return false;
                }
            }
        }

        return true;
    }

    private function validateConfiguration(): bool
    {
        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        if ($batteryVariableID <= 0) {
            return $this->configurationError(IS_INACTIVE, 'Keine Batterievariable ausgewählt.');
        }
        if (!IPS_VariableExists($batteryVariableID)) {
            return $this->configurationError(IS_EBASE, 'Die ausgewählte Batterievariable existiert nicht.');
        }

        $batteryVariable = IPS_GetVariable($batteryVariableID);
        if (!in_array($batteryVariable['VariableType'], [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT], true)) {
            return $this->configurationError(IS_EBASE, 'Die Batterievariable muss vom Typ Integer oder Float sein.');
        }

        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        if ($chargingActorVariableID <= 0) {
            return $this->configurationError(IS_INACTIVE, 'Keine Ladeaktorvariable ausgewählt.');
        }
        if (!IPS_VariableExists($chargingActorVariableID)) {
            return $this->configurationError(IS_EBASE, 'Die ausgewählte Ladeaktorvariable existiert nicht.');
        }

        $chargingActorVariable = IPS_GetVariable($chargingActorVariableID);
        if ($chargingActorVariable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
            return $this->configurationError(IS_EBASE, 'Die Ladeaktorvariable muss vom Typ Boolean sein.');
        }
        if (!HasAction($chargingActorVariableID)) {
            return $this->configurationError(IS_EBASE, 'Die Ladeaktorvariable hat keine Action.');
        }

        $switchOnThreshold = $this->ReadPropertyInteger('SwitchOnThreshold');
        $switchOffThreshold = $this->ReadPropertyInteger('SwitchOffThreshold');
        if ($switchOnThreshold < 0 || $switchOnThreshold > 100 || $switchOffThreshold < 0 || $switchOffThreshold > 100) {
            return $this->configurationError(IS_EBASE, 'Die Schwellwerte müssen zwischen 0 und 100 liegen.');
        }
        if ($switchOnThreshold >= $switchOffThreshold) {
            return $this->configurationError(IS_EBASE, 'Die Einschaltschwelle muss kleiner als die Ausschaltschwelle sein.');
        }

        return true;
    }

    private function configurationError(int $status, string $message): bool
    {
        $this->SetStatus($status);
        $this->SendDebug('Configuration', $message, 0);
        return false;
    }

    private function evaluateBatteryLevel(): void
    {
        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        if (!IPS_VariableExists($batteryVariableID) || !IPS_VariableExists($chargingActorVariableID)) {
            $this->SendDebug('BatteryLevel', 'Auswertung abgebrochen: Konfigurierte Variable existiert nicht.', 0);
            return;
        }

        $batteryLevel = GetValue($batteryVariableID);
        if (!is_int($batteryLevel) && !is_float($batteryLevel)) {
            $this->SendDebug('BatteryLevel', 'Ungültiger Batteriewert: kein numerischer Wert.', 0);
            return;
        }

        $batteryLevel = (float) $batteryLevel;
        if (!is_finite($batteryLevel) || $batteryLevel < 0.0 || $batteryLevel > 100.0) {
            $this->SendDebug('BatteryLevel', sprintf('Ungültiger Batteriewert: %s (zulässig: 0 bis 100).', (string) $batteryLevel), 0);
            return;
        }

        if ($this->ReadPropertyBoolean('ShowBatteryLevel')) {
            $this->SetValue('BatteryLevel', (int) round($batteryLevel, 0, PHP_ROUND_HALF_UP));
        }

        $switchOnThreshold = $this->ReadPropertyInteger('SwitchOnThreshold');
        $switchOffThreshold = $this->ReadPropertyInteger('SwitchOffThreshold');
        $this->SendDebug(
            'BatteryLevel',
            sprintf('Batteriestand: %s %%, Einschaltschwelle: %d %%, Ausschaltschwelle: %d %%', (string) $batteryLevel, $switchOnThreshold, $switchOffThreshold),
            0
        );

        if ($batteryLevel <= $switchOnThreshold) {
            $this->SendDebug('Decision', 'EIN: Batteriestand liegt auf oder unter der Einschaltschwelle.', 0);
            $this->setChargingActorState($chargingActorVariableID, true);
            return;
        }

        if ($batteryLevel >= $switchOffThreshold) {
            $this->SendDebug('Decision', 'AUS: Batteriestand liegt auf oder über der Ausschaltschwelle.', 0);
            $this->setChargingActorState($chargingActorVariableID, false);
            return;
        }

        $this->SendDebug('Decision', 'Innerhalb der Hysterese, keine Änderung.', 0);
    }

    private function updateChargingActorState(): void
    {
        if (!$this->ReadPropertyBoolean('ShowChargingActorState')) {
            return;
        }

        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        if (!IPS_VariableExists($chargingActorVariableID)) {
            return;
        }

        $chargingActorState = GetValue($chargingActorVariableID);
        if (is_bool($chargingActorState)) {
            $this->SetValue('ChargingActorState', $chargingActorState);
        }
    }

    private function setChargingActorState(int $chargingActorVariableID, bool $desiredState): void
    {
        $currentState = GetValue($chargingActorVariableID);
        if ($currentState === $desiredState) {
            $this->SendDebug('ChargingActor', sprintf('Aktor bereits %s, kein Schaltbefehl.', $desiredState ? 'EIN' : 'AUS'), 0);
            return;
        }

        try {
            if (!RequestAction($chargingActorVariableID, $desiredState)) {
                $this->SendDebug('ChargingActor', 'Schaltbefehl wurde von der Action nicht erfolgreich ausgeführt.', 0);
                return;
            }
        } catch (Throwable $e) {
            $this->SendDebug('ChargingActor', 'Schaltbefehl fehlgeschlagen: ' . $e->getMessage(), 0);
            return;
        }

        $this->SendDebug('ChargingActor', sprintf('Schaltbefehl ausgeführt: %s.', $desiredState ? 'EIN' : 'AUS'), 0);
    }
}
