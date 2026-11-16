<?php
declare(strict_types=1);

class BatteryChargeController extends IPSModule
{
    private const STATUS_BATTERY_VARIABLE_MISSING = 201;
    private const STATUS_BATTERY_VARIABLE_WRONG_TYPE = 202;
    private const STATUS_ACTOR_VARIABLE_MISSING = 203;
    private const STATUS_ACTOR_VARIABLE_WRONG_TYPE = 204;
    private const STATUS_ACTOR_WITHOUT_ACTION = 205;
    private const STATUS_THRESHOLDS_OUT_OF_RANGE = 206;
    private const STATUS_THRESHOLDS_INVALID_ORDER = 207;
    private const STATUS_MESSAGE_REGISTRATION_FAILED = 208;
    private const STATUS_MESSAGE_UNREGISTRATION_FAILED = 209;
    private const STATUS_WATCHDOG_TIMEOUT_OUT_OF_RANGE = 210;
    private const STATUS_BATTERY_VALUE_STALE = 211;

    private const DEFAULT_SWITCH_ON_THRESHOLD = 20;
    private const DEFAULT_SWITCH_OFF_THRESHOLD = 80;
    private const MAX_WATCHDOG_TIMEOUT = 1440;
    private const WATCHDOG_TIMER = 'BatteryWatchdog';

    public function Create(): void
    {
        parent::Create();

        $this->RegisterPropertyInteger('BatteryVariableID', 0);
        $this->RegisterPropertyInteger('ChargingActorVariableID', 0);
        $this->RegisterPropertyInteger('SwitchOnThreshold', self::DEFAULT_SWITCH_ON_THRESHOLD);
        $this->RegisterPropertyInteger('SwitchOffThreshold', self::DEFAULT_SWITCH_OFF_THRESHOLD);
        $this->RegisterPropertyBoolean('ShowBatteryLevel', false);
        $this->RegisterPropertyBoolean('ShowChargingActorState', false);
        $this->RegisterPropertyInteger('BatteryWatchdogTimeout', 0);

        // Configured thresholds last copied to the threshold variables; -1 forces the initial copy.
        $this->RegisterAttributeInteger('AppliedSwitchOnThreshold', -1);
        $this->RegisterAttributeInteger('AppliedSwitchOffThreshold', -1);

        $this->RegisterVariableInteger('SwitchOnThreshold', $this->Translate('Switch-on threshold'), '~Intensity.100', 10);
        $this->EnableAction('SwitchOnThreshold');
        $this->RegisterVariableInteger('SwitchOffThreshold', $this->Translate('Switch-off threshold'), '~Intensity.100', 20);
        $this->EnableAction('SwitchOffThreshold');

        $this->RegisterTimer(self::WATCHDOG_TIMER, 0, 'IPS_RequestAction(' . $this->InstanceID . ', "' . self::WATCHDOG_TIMER . '", true);');

        $this->RegisterMessage(0, IPS_KERNELSTARTED);

        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetTimerInterval(self::WATCHDOG_TIMER, 0);
        if (!$this->unregisterMonitoredVariableMessages()) {
            return;
        }

        $this->MaintainVariable('BatteryLevel', $this->Translate('Battery level'), VARIABLETYPE_INTEGER, '~Battery.100', 30, $this->ReadPropertyBoolean('ShowBatteryLevel'));
        $this->MaintainVariable('ChargingActorState', $this->Translate('Charger actuator'), VARIABLETYPE_BOOLEAN, '~Switch', 40, $this->ReadPropertyBoolean('ShowChargingActorState'));
        $this->applyConfiguredThresholds();

        // Other objects may not be available yet during startup; IPS_KERNELSTARTED calls ApplyChanges again.
        if (IPS_GetKernelRunlevel() !== KR_READY) {
            return;
        }

        if (!$this->validateConfiguration()) {
            $this->updateVisualization();
            return;
        }

        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        $registrations = [
            [$batteryVariableID, VM_UPDATE],
            [$batteryVariableID, VM_DELETE],
            [$chargingActorVariableID, VM_UPDATE],
            [$chargingActorVariableID, VM_DELETE]
        ];
        foreach ($registrations as [$senderID, $message]) {
            if (!$this->RegisterMessage($senderID, $message)) {
                $this->unregisterMonitoredVariableMessages();
                $this->configurationError(self::STATUS_MESSAGE_REGISTRATION_FAILED, 'The configured variables could not be monitored.');
                $this->updateVisualization();
                return;
            }
        }
        $this->SetStatus(IS_ACTIVE);

        $this->updateChargingActorState();
        if ($this->isBatteryValueStale()) {
            $this->handleStaleBatteryValue();
            return;
        }
        $this->evaluateBatteryLevel();
        $this->scheduleBatteryWatchdog();
        $this->updateVisualization();
    }

    public function GetVisualizationTile(): string
    {
        $html = file_get_contents(__DIR__ . '/module.html');
        if ($html === false) {
            return '';
        }

        // GetVisualizationTile is only called initially, so the current state is passed along with the HTML.
        return $html . '<script>handleMessage(' . json_encode($this->getVisualizationState()) . ');</script>';
    }

    public function ResetThresholds(): void
    {
        $this->SetValue('SwitchOnThreshold', self::DEFAULT_SWITCH_ON_THRESHOLD);
        $this->SetValue('SwitchOffThreshold', self::DEFAULT_SWITCH_OFF_THRESHOLD);
        $this->SendDebug('Thresholds', sprintf('Active thresholds reset to %d %% and %d %%.', self::DEFAULT_SWITCH_ON_THRESHOLD, self::DEFAULT_SWITCH_OFF_THRESHOLD), 0);

        if ($this->GetStatus() === IS_ACTIVE) {
            $this->evaluateBatteryLevel();
        }
        $this->updateVisualization();
    }

    public function RequestAction($Ident, $Value): void
    {
        switch ($Ident) {
            case self::WATCHDOG_TIMER:
                $this->checkBatteryWatchdog();
                return;
            case 'SwitchOnThreshold':
            case 'SwitchOffThreshold':
                $this->setActiveThreshold($Ident, $Value);
                return;
        }

        $message = 'Unknown ident: ' . (string) $Ident;
        $this->SendDebug('RequestAction', $message, 0);
        $this->LogMessage($message, KL_ERROR);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        if ($Message === IPS_KERNELSTARTED) {
            $this->ApplyChanges();
            return;
        }

        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');

        if ($Message === VM_UPDATE && $SenderID === $batteryVariableID) {
            if ($this->GetStatus() === self::STATUS_BATTERY_VALUE_STALE) {
                $this->SetStatus(IS_ACTIVE);
                $this->LogMessage($this->Translate('The battery level is being updated again. Charge control has resumed.'), KL_NOTIFY);
            }
            $this->evaluateBatteryLevel();
            $this->scheduleBatteryWatchdog();
            $this->updateVisualization();
            return;
        }
        if ($Message === VM_UPDATE && $SenderID === $chargingActorVariableID) {
            $this->updateChargingActorState();
            $this->updateVisualization();
            return;
        }

        if ($Message !== VM_DELETE) {
            return;
        }
        if ($SenderID === $batteryVariableID) {
            $this->SetTimerInterval(self::WATCHDOG_TIMER, 0);
            $this->SetStatus(self::STATUS_BATTERY_VARIABLE_MISSING);
            $this->SendDebug('Configuration', 'The configured battery variable was deleted.', 0);
            $this->updateVisualization();
            return;
        }
        if ($SenderID === $chargingActorVariableID) {
            $this->SetTimerInterval(self::WATCHDOG_TIMER, 0);
            $this->SetStatus(self::STATUS_ACTOR_VARIABLE_MISSING);
            $this->SendDebug('Configuration', 'The configured charger actuator was deleted.', 0);
            $this->updateVisualization();
        }
    }

    private function setActiveThreshold(string $ident, $value): void
    {
        if (!is_int($value)) {
            $message = 'The threshold must be an integer.';
            $this->SendDebug('ThresholdAction', $message, 0);
            $this->LogMessage($message, KL_ERROR);
            return;
        }
        if ($value < 0 || $value > 100) {
            $message = sprintf($this->Translate('The threshold cannot be set to %d %%. Allowed values are between 0 %% and 100 %%.'), $value);
            $this->rejectThresholdAction($message);
            return;
        }

        $switchOnThreshold = $ident === 'SwitchOnThreshold' ? $value : $this->GetValue('SwitchOnThreshold');
        $switchOffThreshold = $ident === 'SwitchOffThreshold' ? $value : $this->GetValue('SwitchOffThreshold');
        if ($switchOnThreshold >= $switchOffThreshold) {
            if ($ident === 'SwitchOnThreshold') {
                $message = sprintf(
                    $this->Translate('The switch-on threshold cannot be set to %d %%. It must be lower than the switch-off threshold of %d %%.'),
                    $value,
                    $switchOffThreshold
                );
            } else {
                $message = sprintf(
                    $this->Translate('The switch-off threshold cannot be set to %d %%. It must be higher than the switch-on threshold of %d %%.'),
                    $value,
                    $switchOnThreshold
                );
            }
            $this->rejectThresholdAction($message);
            return;
        }

        $this->SetValue($ident, $value);
        $this->SendDebug('ThresholdAction', sprintf('%s set to %d %%.', $ident, $value), 0);

        if ($this->GetStatus() === IS_ACTIVE) {
            $this->evaluateBatteryLevel();
        }
        $this->updateVisualization();
    }

    private function rejectThresholdAction(string $message): void
    {
        $this->SendDebug('ThresholdAction', $message, 0);
        $this->LogMessage($message, KL_WARNING);
        $this->updateVisualization();
        echo $message;
    }

    private function applyConfiguredThresholds(): void
    {
        $switchOnThreshold = $this->ReadPropertyInteger('SwitchOnThreshold');
        $switchOffThreshold = $this->ReadPropertyInteger('SwitchOffThreshold');
        if (
            $switchOnThreshold === $this->ReadAttributeInteger('AppliedSwitchOnThreshold')
            && $switchOffThreshold === $this->ReadAttributeInteger('AppliedSwitchOffThreshold')
        ) {
            return;
        }
        // Invalid configured thresholds are reported by validateConfiguration().
        if ($switchOnThreshold < 0 || $switchOffThreshold > 100 || $switchOnThreshold >= $switchOffThreshold) {
            return;
        }

        $this->SetValue('SwitchOnThreshold', $switchOnThreshold);
        $this->SetValue('SwitchOffThreshold', $switchOffThreshold);
        $this->WriteAttributeInteger('AppliedSwitchOnThreshold', $switchOnThreshold);
        $this->WriteAttributeInteger('AppliedSwitchOffThreshold', $switchOffThreshold);
        $this->SendDebug('Thresholds', sprintf('Configured thresholds applied: %d %% and %d %%.', $switchOnThreshold, $switchOffThreshold), 0);
    }

    private function unregisterMonitoredVariableMessages(): bool
    {
        foreach ($this->GetMessageList() as $senderID => $messages) {
            foreach ($messages as $message) {
                if (!in_array($message, [VM_UPDATE, VM_DELETE], true)) {
                    continue;
                }
                if (!$this->UnregisterMessage((int) $senderID, $message)) {
                    $this->configurationError(self::STATUS_MESSAGE_UNREGISTRATION_FAILED, 'A previously monitored variable could not be unregistered.');
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
            return $this->configurationError(IS_INACTIVE, 'No battery variable selected.');
        }
        if (!IPS_VariableExists($batteryVariableID)) {
            return $this->configurationError(self::STATUS_BATTERY_VARIABLE_MISSING, 'The selected battery variable does not exist.');
        }

        $batteryVariable = IPS_GetVariable($batteryVariableID);
        if (!in_array($batteryVariable['VariableType'], [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT], true)) {
            return $this->configurationError(self::STATUS_BATTERY_VARIABLE_WRONG_TYPE, 'The battery variable must be of type Integer or Float.');
        }

        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        if ($chargingActorVariableID <= 0) {
            return $this->configurationError(IS_INACTIVE, 'No charger actuator variable selected.');
        }
        if (!IPS_VariableExists($chargingActorVariableID)) {
            return $this->configurationError(self::STATUS_ACTOR_VARIABLE_MISSING, 'The selected charger actuator variable does not exist.');
        }

        $chargingActorVariable = IPS_GetVariable($chargingActorVariableID);
        if ($chargingActorVariable['VariableType'] !== VARIABLETYPE_BOOLEAN) {
            return $this->configurationError(self::STATUS_ACTOR_VARIABLE_WRONG_TYPE, 'The charger actuator variable must be of type Boolean.');
        }
        if (!HasAction($chargingActorVariableID)) {
            return $this->configurationError(self::STATUS_ACTOR_WITHOUT_ACTION, 'The charger actuator variable has no action.');
        }

        $switchOnThreshold = $this->ReadPropertyInteger('SwitchOnThreshold');
        $switchOffThreshold = $this->ReadPropertyInteger('SwitchOffThreshold');
        if ($switchOnThreshold < 0 || $switchOnThreshold > 100 || $switchOffThreshold < 0 || $switchOffThreshold > 100) {
            return $this->configurationError(self::STATUS_THRESHOLDS_OUT_OF_RANGE, 'The thresholds must be between 0 and 100.');
        }
        if ($switchOnThreshold >= $switchOffThreshold) {
            return $this->configurationError(self::STATUS_THRESHOLDS_INVALID_ORDER, 'The switch-on threshold must be lower than the switch-off threshold.');
        }

        $watchdogTimeout = $this->ReadPropertyInteger('BatteryWatchdogTimeout');
        if ($watchdogTimeout < 0 || $watchdogTimeout > self::MAX_WATCHDOG_TIMEOUT) {
            return $this->configurationError(self::STATUS_WATCHDOG_TIMEOUT_OUT_OF_RANGE, 'The watchdog timeout must be between 0 and 1440 minutes.');
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
            $this->SendDebug('BatteryLevel', 'Evaluation aborted: a configured variable does not exist.', 0);
            return;
        }

        $batteryLevel = GetValue($batteryVariableID);
        if (!is_int($batteryLevel) && !is_float($batteryLevel)) {
            $this->SendDebug('BatteryLevel', 'Invalid battery value: not numeric.', 0);
            return;
        }

        $batteryLevel = (float) $batteryLevel;
        if (!is_finite($batteryLevel) || $batteryLevel < 0.0 || $batteryLevel > 100.0) {
            $this->SendDebug('BatteryLevel', sprintf('Invalid battery value: %s (allowed: 0 to 100).', (string) $batteryLevel), 0);
            return;
        }

        if ($this->ReadPropertyBoolean('ShowBatteryLevel')) {
            $this->SetValue('BatteryLevel', (int) round($batteryLevel, 0, PHP_ROUND_HALF_UP));
        }

        $switchOnThreshold = $this->GetValue('SwitchOnThreshold');
        $switchOffThreshold = $this->GetValue('SwitchOffThreshold');
        $this->SendDebug(
            'BatteryLevel',
            sprintf('Battery level: %s %%, switch-on threshold: %d %%, switch-off threshold: %d %%', (string) $batteryLevel, $switchOnThreshold, $switchOffThreshold),
            0
        );

        if ($batteryLevel <= $switchOnThreshold) {
            $this->SendDebug('Decision', 'ON: battery level is at or below the switch-on threshold.', 0);
            $this->setChargingActorState($chargingActorVariableID, true);
            return;
        }

        if ($batteryLevel >= $switchOffThreshold) {
            $this->SendDebug('Decision', 'OFF: battery level is at or above the switch-off threshold.', 0);
            $this->setChargingActorState($chargingActorVariableID, false);
            return;
        }

        $this->SendDebug('Decision', 'Within hysteresis, no change.', 0);
    }

    private function isBatteryValueStale(): bool
    {
        $timeout = $this->ReadPropertyInteger('BatteryWatchdogTimeout') * 60;
        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        if ($timeout <= 0 || !IPS_VariableExists($batteryVariableID)) {
            return false;
        }

        return time() - IPS_GetVariable($batteryVariableID)['VariableUpdated'] >= $timeout;
    }

    private function scheduleBatteryWatchdog(): void
    {
        $timeout = $this->ReadPropertyInteger('BatteryWatchdogTimeout') * 60;
        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        if ($timeout <= 0 || !IPS_VariableExists($batteryVariableID)) {
            $this->SetTimerInterval(self::WATCHDOG_TIMER, 0);
            return;
        }

        // The timer is set to the remaining time, so a restarted or continued timer interval both lead to a correct check.
        $remaining = IPS_GetVariable($batteryVariableID)['VariableUpdated'] + $timeout - time();
        $this->SetTimerInterval(self::WATCHDOG_TIMER, max(1, $remaining) * 1000);
    }

    private function checkBatteryWatchdog(): void
    {
        if ($this->GetStatus() !== IS_ACTIVE) {
            $this->SetTimerInterval(self::WATCHDOG_TIMER, 0);
            return;
        }
        if ($this->isBatteryValueStale()) {
            $this->handleStaleBatteryValue();
            return;
        }
        $this->scheduleBatteryWatchdog();
    }

    private function handleStaleBatteryValue(): void
    {
        $this->SetTimerInterval(self::WATCHDOG_TIMER, 0);
        $this->SetStatus(self::STATUS_BATTERY_VALUE_STALE);

        $message = sprintf(
            $this->Translate('The battery level has not been updated for at least %d minutes. The charger actuator is switched off for safety.'),
            $this->ReadPropertyInteger('BatteryWatchdogTimeout')
        );
        $this->SendDebug('Watchdog', $message, 0);
        $this->LogMessage($message, KL_WARNING);

        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        if (IPS_VariableExists($chargingActorVariableID)) {
            $this->setChargingActorState($chargingActorVariableID, false);
        }
        $this->updateVisualization();
    }

    private function getVisualizationState(): array
    {
        $batteryLevel = null;
        $batteryVariableID = $this->ReadPropertyInteger('BatteryVariableID');
        if ($batteryVariableID > 0 && IPS_VariableExists($batteryVariableID)) {
            $value = GetValue($batteryVariableID);
            if ((is_int($value) || is_float($value)) && is_finite((float) $value)) {
                $batteryLevel = max(0.0, min(100.0, (float) $value));
            }
        }

        $charging = null;
        $chargingActorVariableID = $this->ReadPropertyInteger('ChargingActorVariableID');
        if ($chargingActorVariableID > 0 && IPS_VariableExists($chargingActorVariableID)) {
            $value = GetValue($chargingActorVariableID);
            if (is_bool($value)) {
                $charging = $value;
            }
        }

        return [
            'level'    => $batteryLevel,
            'on'       => $this->GetValue('SwitchOnThreshold'),
            'off'      => $this->GetValue('SwitchOffThreshold'),
            'charging' => $charging,
            'status'   => $this->GetStatus()
        ];
    }

    private function updateVisualization(): void
    {
        $this->UpdateVisualizationValue(json_encode($this->getVisualizationState()));
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
            $this->SendDebug('ChargingActor', sprintf('Actuator already %s, no switching command.', $desiredState ? 'ON' : 'OFF'), 0);
            return;
        }

        $failureMessage = $desiredState
            ? $this->Translate('The charger actuator could not be switched on.')
            : $this->Translate('The charger actuator could not be switched off.');
        try {
            if (!RequestAction($chargingActorVariableID, $desiredState)) {
                $this->SendDebug('ChargingActor', 'The action did not execute the switching command successfully.', 0);
                $this->LogMessage($failureMessage, KL_WARNING);
                return;
            }
        } catch (Throwable $e) {
            $this->SendDebug('ChargingActor', 'Switching command failed: ' . $e->getMessage(), 0);
            $this->LogMessage($failureMessage . ' ' . $e->getMessage(), KL_WARNING);
            return;
        }

        $this->SendDebug('ChargingActor', sprintf('Switching command executed: %s.', $desiredState ? 'ON' : 'OFF'), 0);
    }
}
