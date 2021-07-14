<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/local.php';   // lokale Funktionen

class MieleAtHomeDevice extends IPSModule
{
    use MieleAtHomeCommonLib;
    use MieleAtHomeLocalLib;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyInteger('deviceId', 0);
        $this->RegisterPropertyString('deviceType', '');
        $this->RegisterPropertyString('fabNumber', '');
        $this->RegisterPropertyString('techType', '');

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterPropertyBoolean('map_programName', false);
        $this->RegisterPropertyBoolean('map_programType', true);
        $this->RegisterPropertyBoolean('map_programPhase', false);
        $this->RegisterPropertyBoolean('map_dryingStep', true);
        $this->RegisterPropertyBoolean('map_ventilationStep', true);

        $this->CreateVarProfile('MieleAtHome.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass');
        $this->CreateVarProfile('MieleAtHome.Temperature', VARIABLETYPE_INTEGER, ' °C', 0, 0, 0, 0, 'Temperature');
        $this->CreateVarProfile('MieleAtHome.SpinningSpeed', VARIABLETYPE_INTEGER, ' U/min', 0, 0, 0, 0, '');
        $this->CreateVarProfile('MieleAtHome.BatteryLevel', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, '');

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => '-', 'Farbe' => -1];
        $associations[] = ['Wert' => 1, 'Name' => '%d %%', 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.WorkProgress', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$STATUS_UNKNOWN, 'Name' => $this->Translate('Unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_RESERVED, 'Name' => $this->Translate('Reserved'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_PROGRAMMED, 'Name' => $this->Translate('Programmed'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_WAITING_TO_START, 'Name' => $this->Translate('Waiting to start'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_RUNNING, 'Name' => $this->Translate('Running'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_PAUSE, 'Name' => $this->Translate('Pause'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_END_PROGRAMMED, 'Name' => $this->Translate('End programmed'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_FAILURE, 'Name' => $this->Translate('Failure'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_PROGRAM_INTERRUPTED, 'Name' => $this->Translate('Program interrupted'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_IDLE, 'Name' => $this->Translate('Idle'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_RINSE_HOLD, 'Name' => $this->Translate('Rinse hold'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_SERVICE, 'Name' => $this->Translate('Service'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_SUPERFREEZING, 'Name' => $this->Translate('Superfreezing'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_SUPERCOOLING, 'Name' => $this->Translate('Supercooling'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_SUPERHEATING, 'Name' => $this->Translate('Superheating'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_NOT_CONNECTED, 'Name' => $this->Translate('Not connected'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$STATUS_SUPERCOOLING_SUPERFREEZING, 'Name' => $this->Translate('Superfrost/cooling'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Status', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('Closed'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('Opened'), 'Farbe' => 0xEE0000];
        $this->CreateVarProfile('MieleAtHome.Door', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, 'Door', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$LIGHT_ENABLE, 'Name' => $this->Translate('On'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$LIGHT_DISABLE, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Light', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, 'Light', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$POWER_ON, 'Name' => $this->Translate('switch on'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$POWER_OFF, 'Name' => $this->Translate('switch off'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.PowerSupply', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, 'Power', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTION_PAUSE, 'Name' => $this->Translate('pause'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Action', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Superfreezing', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => self::$ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1];
        $associations[] = ['Wert' => self::$ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Supercooling', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => '-', 'Farbe' => -1];
        $associations[] = ['Wert' => 0.1, 'Name' => '%.1f kWh', 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Energy', VARIABLETYPE_FLOAT, '', 0, 0, 0, 1, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => 0, 'Name' => '-', 'Farbe' => -1];
        $associations[] = ['Wert' => 1, 'Name' => '%.0f l', 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Water', VARIABLETYPE_FLOAT, '', 0, 0, 0, 1, '', $associations);

        $this->RegisterTimer('UpdateData', 0, 'MieleAtHome_UpdateData(' . $this->InstanceID . ');');

        $this->ConnectParent('{996743FB-1712-47A3-9174-858A08A13523}');
    }

    private function device2with($deviceId)
    {
        $with['ProgramName'] = false;
        $with['ProgramType'] = false;
        $with['ProgramPhase'] = false;
        $with['times'] = false;
        $with['wash_temp'] = false;
        $with['SpinningSpeed'] = false;
        $with['DryingStep'] = false;
        $with['VentilationStep'] = false;
        $with['oven_temp'] = false;
        $with['fridge_temp'] = false;
        $with['freezer_temp'] = false;
        $with['Door'] = false;
        $with['ecoFeedback_Water'] = false;
        $with['ecoFeedback_Energy'] = false;
        $with['batteryLevel'] = false;

        $with['enabled_action'] = false;
        $with['enabled_starttime'] = false;
        $with['enabled_superfreezing'] = false;
        $with['enabled_supercooling'] = false;
        $with['enabled_light'] = false;
        $with['enabled_powersupply'] = false;

        switch ($deviceId) {
            case self::$DEVICE_WASHING_MACHINE:   					// Waschmaschine
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['wash_temp'] = true;
                $with['SpinningSpeed'] = true;
                $with['Door'] = true;
                $with['ecoFeedback_Water'] = true;
                $with['ecoFeedback_Energy'] = true;

                $with['enabled_powersupply'] = true;
                $with['enabled_action'] = true;
                $with['enabled_starttime'] = true;
                break;
            case self::$DEVICE_TUMBLE_DRYER:      					// Trockner
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['DryingStep'] = true;
                $with['Door'] = true;
                $with['ecoFeedback_Energy'] = true;

                $with['enabled_powersupply'] = true;
                $with['enabled_action'] = true;
                $with['enabled_starttime'] = true;
                break;
            case self::$DEVICE_DISHWASHER:         					// Geschirrspüler
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['Door'] = true;
                $with['ecoFeedback_Water'] = true;
                $with['ecoFeedback_Energy'] = true;

                $with['enabled_powersupply'] = true;
                $with['enabled_action'] = true;
                $with['enabled_starttime'] = true;
                break;
            case self::$DEVICE_OVEN:               					// Backofen
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['oven_temp'] = true;
                $with['Door'] = true;

                $with['enabled_powersupply'] = true;
                break;
            case self::$DEVICE_OVEN_MICROWAVE:     					// Backofen mit Mikrowelle
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['oven_temp'] = true;
                $with['Door'] = true;

                $with['enabled_powersupply'] = true;
                break;
            case self::$DEVICE_FRIDGE_FREEZER:						// Kühl-/Gefrierkombination
                $with['fridge_temp'] = true;
                $with['freezer_temp'] = true;
                $with['Door'] = true;

                $with['enabled_powersupply'] = true;
                $with['enabled_superfreezing'] = true;
                $with['enabled_supercooling'] = true;
                break;
            case self::$DEVICE_DISH_WARMER:							// Wärmeschublade
                $with['ProgramName'] = true;
                break;
            case self::$DEVICE_STEAM_OVEN_COMBINATION: 				// Dampfgarer mit Backofen-Funktion
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['oven_temp'] = true;
                $with['Door'] = true;

                $with['enabled_powersupply'] = true;
                break;
            case self::$DEVICE_STEAM_OVEN_MICROWAVE_COMBINATION:	// Dampfgarer mit Mikrowelle
                $with['ProgramName'] = true;
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['oven_temp'] = true;
                $with['Door'] = true;

                $with['enabled_powersupply'] = true;
                break;
        }
        return $with;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $deviceId = $this->ReadPropertyInteger('deviceId');
        $deviceType = $this->ReadPropertyString('deviceType');
        $techType = $this->ReadPropertyString('techType');
        $fabNumber = $this->ReadPropertyString('fabNumber');

        $with = $this->device2with($deviceId);

        $this->SendDebug(__FUNCTION__, 'with=' . print_r($with, true), 0);

        $vpos = 1;

        $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_INTEGER, 'MieleAtHome.Status', $vpos++, true);
        $this->MaintainVariable('Failure', $this->Translate('Failure'), VARIABLETYPE_BOOLEAN, 'Alert', $vpos++, true);

        $this->MaintainVariable('PowerSupply', $this->Translate('Power supply'), VARIABLETYPE_INTEGER, 'MieleAtHome.PowerSupply', $vpos++, $with['enabled_powersupply']);

        $this->MaintainVariable('Action', $this->Translate('Action'), VARIABLETYPE_INTEGER, 'MieleAtHome.Action', $vpos++, $with['enabled_action']);
        $this->MaintainVariable('Superfreezing', $this->Translate('Superfreezing'), VARIABLETYPE_INTEGER, 'MieleAtHome.Superfreezing', $vpos++, $with['enabled_superfreezing']);
        $this->MaintainVariable('Supercooling', $this->Translate('Supercooling'), VARIABLETYPE_INTEGER, 'MieleAtHome.Supercooling', $vpos++, $with['enabled_supercooling']);

        $this->MaintainVariable('ProgramName', $this->Translate('Program name'), VARIABLETYPE_STRING, '', $vpos++, $with['ProgramName']);
        $this->MaintainVariable('ProgramType', $this->Translate('Program'), VARIABLETYPE_STRING, '', $vpos++, $with['ProgramType']);

        $this->MaintainVariable('ProgramPhase', $this->Translate('Phase'), VARIABLETYPE_STRING, '', $vpos++, $with['ProgramPhase']);

        $this->MaintainVariable('StartTime', $this->Translate('Start at'), VARIABLETYPE_INTEGER, '~UnixTimestampTime', $vpos++, $with['times']);
        $this->MaintainVariable('ElapsedTime', $this->Translate('Elapsed time'), VARIABLETYPE_INTEGER, 'MieleAtHome.Duration', $vpos++, $with['times']);
        $this->MaintainVariable('RemainingTime', $this->Translate('Remaining time'), VARIABLETYPE_INTEGER, 'MieleAtHome.Duration', $vpos++, $with['times']);
        $this->MaintainVariable('EndTime', $this->Translate('End at'), VARIABLETYPE_INTEGER, '~UnixTimestampTime', $vpos++, $with['times']);
        $this->MaintainVariable('WorkProgress', $this->Translate('Work progress'), VARIABLETYPE_INTEGER, 'MieleAtHome.WorkProgress', $vpos++, $with['times']);

        $this->MaintainVariable('Wash_TargetTemperature', $this->Translate('Temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['wash_temp']);

        $this->MaintainVariable('SpinningSpeed', $this->Translate('Spinning speed'), VARIABLETYPE_INTEGER, 'MieleAtHome.SpinningSpeed', $vpos++, $with['SpinningSpeed']);

        $this->MaintainVariable('DryingStep', $this->Translate('Drying step'), VARIABLETYPE_STRING, '', $vpos++, $with['DryingStep']);

        $this->MaintainVariable('VentilationStep', $this->Translate('Ventilation step'), VARIABLETYPE_STRING, '', $vpos++, $with['VentilationStep']);

        $this->MaintainVariable('Fridge_TargetTemperature', $this->Translate('Fridge: target temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['fridge_temp']);
        $this->MaintainVariable('Fridge_Temperature', $this->Translate('Fridge: temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['fridge_temp']);

        $this->MaintainVariable('Freezer_TargetTemperature', $this->Translate('Freezer: target temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['freezer_temp']);
        $this->MaintainVariable('Freezer_Temperature', $this->Translate('Freezer: temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['freezer_temp']);

        $this->MaintainVariable('Door', $this->Translate('Door'), VARIABLETYPE_BOOLEAN, 'MieleAtHome.Door', $vpos++, $with['Door']);

        $this->MaintainVariable('Light', $this->Translate('Light'), VARIABLETYPE_INTEGER, 'MieleAtHome.Light', $vpos++, $with['enabled_light']);

        $this->MaintainVariable('Oven_TargetTemperature', $this->Translate('Target temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['oven_temp']);
        $this->MaintainVariable('Oven_Temperature', $this->Translate('Temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['oven_temp']);

        $vpos = 80;
        $this->MaintainVariable('CurrentWaterConsumption', $this->Translate('Current water consumption'), VARIABLETYPE_FLOAT, 'MieleAtHome.Water', $vpos++, $with['ecoFeedback_Water']);
        $this->MaintainVariable('EstimatedWaterConsumption', $this->Translate('Estimated water consumption'), VARIABLETYPE_FLOAT, 'MieleAtHome.Water', $vpos++, $with['ecoFeedback_Water']);
        $this->MaintainVariable('LastWaterConsumption', $this->Translate('Last water consumption'), VARIABLETYPE_FLOAT, 'MieleAtHome.Water', $vpos++, $with['ecoFeedback_Water']);
        $this->MaintainVariable('CurrentEnergyConsumption', $this->Translate('Current energy consumption'), VARIABLETYPE_FLOAT, 'MieleAtHome.Energy', $vpos++, $with['ecoFeedback_Energy']);
        $this->MaintainVariable('EstimatedEnergyConsumption', $this->Translate('Estimated energy consumption'), VARIABLETYPE_FLOAT, 'MieleAtHome.Energy', $vpos++, $with['ecoFeedback_Energy']);
        $this->MaintainVariable('LastEnergyConsumption', $this->Translate('Last energy consumption'), VARIABLETYPE_FLOAT, 'MieleAtHome.Energy', $vpos++, $with['ecoFeedback_Energy']);

        $vpos = 90;
        $this->MaintainVariable('BatteryLevel', $this->Translate('Battery level'), VARIABLETYPE_INTEGER, 'MieleAtHome.BatteryLevel', $vpos++, $with['batteryLevel']);

        $vpos = 100;
        $this->MaintainVariable('LastChange', $this->Translate('last change'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $this->SetSummary($techType . ' (#' . $fabNumber . ')');

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetTimerInterval('UpdateData', 0);
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        if ($deviceId > 0 && $fabNumber != '') {
            $this->SetStatus(IS_ACTIVE);
            $this->SetUpdateInterval();
        } else {
            $this->SetStatus(self::$IS_INVALIDCONFIG);
        }

        if ($with['enabled_action']) {
            $this->MaintainAction('Action', true);
        }
        if ($with['enabled_starttime']) {
            $this->MaintainAction('StartTime', true);
        }
        if ($with['enabled_superfreezing']) {
            $this->MaintainAction('Superfreezing', true);
        }
        if ($with['enabled_supercooling']) {
            $this->MaintainAction('Supercooling', true);
        }
        if ($with['enabled_light']) {
            $this->MaintainAction('Light', true);
        }
        if ($with['enabled_powersupply']) {
            $this->MaintainAction('PowerSupply', true);
        }
    }

    private function GetFormElements()
    {
        $formElements = [];

        if ($this->HasActiveParent() == false) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Instance has no active parent instance',
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Instance is disabled'
        ];

        $items = [];
        $items[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'deviceId',
            'caption' => 'Device id'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'deviceType',
            'caption' => 'Device type'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'fabNumber',
            'caption' => 'Fabrication number'
        ];
        $items[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'techType',
            'caption' => 'Model'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Basic configuration (don\'t change)'
        ];

        $items = [];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'mapping code to text of field ...'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'map_programName',
            'caption' => ' ... Program name'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'map_programType',
            'caption' => ' ... Program'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'map_programPhase',
            'caption' => ' ... Phase'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'map_dryingStep',
            'caption' => ' ... Drying step'
        ];
        $items[] = [
            'type'    => 'CheckBox',
            'name'    => 'map_ventilationStep',
            'caption' => ' ... Ventilation step'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Settings'
        ];

        $items = [];
        $items[] = [
            'type'    => 'Label',
            'caption' => 'Update data every X seconds'
        ];
        $items[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'caption' => 'Seconds'
        ];
        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => $items,
            'caption' => 'Communication'
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update data',
            'onClick' => 'MieleAtHome_UpdateData($id);'
        ];

        return $formActions;
    }

    protected function SetUpdateInterval()
    {
        $sec = $this->ReadPropertyInteger('update_interval');
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return;
        }

        $this->SetUpdateInterval();

        $fabNumber = $this->ReadPropertyString('fabNumber');
        $deviceId = $this->ReadPropertyInteger('deviceId');

        $map_programName = $this->ReadPropertyBoolean('map_programName');
        $map_programType = $this->ReadPropertyBoolean('map_programType');
        $map_programPhase = $this->ReadPropertyBoolean('map_programPhase');
        $map_dryingStep = $this->ReadPropertyBoolean('map_dryingStep');
        $map_ventilationStep = $this->ReadPropertyBoolean('map_ventilationStep');

        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDeviceStatus', 'Ident' => $fabNumber];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        if ($data == '') {
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == '') {
            return;
        }

        $with = $this->device2with($deviceId);
        $this->SendDebug(__FUNCTION__, 'with=' . print_r($with, true), 0);

        $is_changed = false;

        $value_raw = $this->GetArrayElem($jdata, 'status.value_raw', 0);
        $r = IPS_GetVariableProfile('MieleAtHome.Status');
        $status = self::$STATUS_UNKNOWN;
        foreach ($r['Associations'] as $a) {
            if ($a['Value'] == $value_raw) {
                $status = $value_raw;
                break;
            }
        }
        $this->SaveValue('State', $status, $is_changed);
        if ($status == self::$STATUS_UNKNOWN) {
            $e = 'unknown value ' . $value_raw;
            $value_localized = $this->GetArrayElem($jdata, 'status.value_localized', '');
            if ($value_localized != '') {
                $e .= ' (' . $value_localized . ')';
            }
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_NOTIFY);
        }

        $off = $status == self::$STATUS_OFF;
        $delayed = $status == self::$STATUS_WAITING_TO_START;
        $standby = $status == self::$STATUS_ON;

        $signalFailure = (bool) $this->GetArrayElem($jdata, 'signalFailure', false);
        $this->SaveValue('Failure', $signalFailure, $is_changed);

        $dt = new DateTime(date('d.m.Y H:i:00'));
        $now = (int) $dt->format('U');

        $startTime = 0;
        $endTime = 0;

        if ($with['ProgramType']) {
            if ($off) {
                $programType = '';
            } else {
                $programType = $map_programType ? '' : $this->GetArrayElem($jdata, 'programType.value_localized', '');
                if ($programType == '') {
                    $value_raw = $this->GetArrayElem($jdata, 'programType.value_raw', 0);
                    $programType = $this->programType2text($deviceId, $value_raw);
                }
            }
            $this->SaveValue('ProgramType', $programType, $is_changed);
        }
        if ($with['ProgramName']) {
            if ($off) {
                $programName = '';
            } else {
                $programName = $map_programName ? '' : $this->GetArrayElem($jdata, 'ProgramID.value_localized', '');
                if ($programName == '') {
                    $value_raw = $this->GetArrayElem($jdata, 'programID.value_raw', 0);
                    $programName = $this->programId2text($deviceId, $value_raw);
                }
            }
            $this->SaveValue('ProgramName', $programName, $is_changed);
        }

        if ($with['ProgramPhase']) {
            if ($off) {
                $programPhase = '';
            } else {
                $programPhase = $map_programPhase ? '' : $this->GetArrayElem($jdata, 'programPhase.value_localized', '');
                if ($programPhase == '') {
                    $value_raw = $this->GetArrayElem($jdata, 'programPhase.value_raw', 0);
                    if ($value_raw != 65535) {
                        $programPhase = $this->programPhase2text($deviceId, $value_raw);
                    }
                }
            }
            $this->SaveValue('ProgramPhase', $programPhase, $is_changed);
        }

        if ($with['times']) {
            $remainingTime = 0;
            $elapsedTime = 0;
            $startTime = 0;
            $endTime = 0;
            $workProgress = 0;
            if (!$off) {
                $remainingTime_H = $this->GetArrayElem($jdata, 'remainingTime.0', 0);
                $remainingTime_M = $this->GetArrayElem($jdata, 'remainingTime.1', 0);
                $remainingTime = $remainingTime_H * 60 + $remainingTime_M;

                if ($delayed) {
                    $startTime_H = $this->GetArrayElem($jdata, 'startTime.0', 0);
                    $startTime_M = $this->GetArrayElem($jdata, 'startTime.1', 0);
                    $startDelay = ($startTime_H * 60 + $startTime_M) * 60;

                    if ($startDelay > 0) {
                        $startTime = $now + $startDelay;
                    }
                    if ($remainingTime > 0) {
                        $endTime = $startTime + $remainingTime * 60;
                    }
                    $elapsedTime = 0;
                } elseif (!$standby) {
                    $elapsedTime_H = $this->GetArrayElem($jdata, 'elapsedTime.0', 0);
                    $elapsedTime_M = $this->GetArrayElem($jdata, 'elapsedTime.1', 0);
                    $elapsedTime = $elapsedTime_H * 60 + $elapsedTime_M;

                    $endTime = $now + $remainingTime * 60;
                    $startTime = $now - $elapsedTime * 60;

                    if ($elapsedTime && $remainingTime) {
                        $workProgress = floor($elapsedTime / ($elapsedTime + $remainingTime) * 100);
                        $this->SendDebug(__FUNCTION__, 'elapsedTime=' . $elapsedTime . ', remainingTime=' . $remainingTime . ' => workProgress=' . $workProgress, 0);
                    } else {
                        $workProgress = 100;
                    }
                }
            }
            $this->SaveValue('RemainingTime', $remainingTime, $is_changed);
            $this->SaveValue('ElapsedTime', $elapsedTime, $is_changed);
            $this->SaveValue('StartTime', $startTime, $is_changed);
            $this->SaveValue('EndTime', $endTime, $is_changed);
            $this->SaveValue('WorkProgress', $workProgress, $is_changed);
        }

        if ($with['wash_temp']) {
            if ($off) {
                $targetTemperature = 0;
            } else {
                $targetTemperature = $this->GetArrayElem($jdata, 'targetTemperature.0.value_localized', 0);
                if ($targetTemperature <= -326) {
                    $targetTemperature = 0;
                }
            }
            $this->SaveValue('Wash_TargetTemperature', $targetTemperature, $is_changed);
        }

        if ($with['SpinningSpeed']) {
            if ($off) {
                $spinningSpeed = 0;
            } else {
                $spinningSpeed = $this->GetArrayElem($jdata, 'spinningSpeed.value_raw', 0);
            }
            $this->SaveValue('SpinningSpeed', $spinningSpeed, $is_changed);
        }

        if ($with['DryingStep']) {
            if ($off) {
                $dryingStep = '';
            } else {
                $dryingStep = $map_dryingStep ? '' : $this->GetArrayElem($jdata, 'dryingStep.value_localized', '');
                if ($dryingStep == '') {
                    $value_raw = $this->GetArrayElem($jdata, 'dryingStep.value_raw', 0);
                    $dryingStep = $this->dryingStep2text($deviceId, $value_raw);
                }
            }
            $this->SaveValue('DryingStep', $dryingStep, $is_changed);
        }

        if ($with['VentilationStep']) {
            if ($off) {
                $ventilationStep = '';
            } else {
                $ventilationStep = $map_ventilationStep ? '' : $this->GetArrayElem($jdata, 'ventilationStep.value_localized', '');
                if ($ventilationStep == '') {
                    $value_raw = $this->GetArrayElem($jdata, 'ventilationStep.value_raw', 0);
                    $ventilationStep = $this->ventilationStep2text($deviceId, $value_raw);
                }
            }
            $this->SaveValue('VentilationStep', $ventilationStep, $is_changed);
        }

        if ($with['fridge_temp']) {
            $targetTemperature = $this->GetArrayElem($jdata, 'targetTemperature.0.value_localized', 0);
            if ($targetTemperature <= -326) {
                $targetTemperature = 0;
            }
            $this->SaveValue('Fridge_TargetTemperature', $targetTemperature, $is_changed);

            $temperature = $this->GetArrayElem($jdata, 'temperature.0.value_localized', 0);
            if ($temperature <= -326) {
                $temperature = 0;
            }
            $this->SaveValue('Fridge_Temperature', $temperature, $is_changed);
        }

        if ($with['freezer_temp']) {
            $targetTemperature = $this->GetArrayElem($jdata, 'targetTemperature.1.value_localized', 0);
            if ($targetTemperature <= -326) {
                $targetTemperature = 0;
            }
            $this->SaveValue('Freezer_TargetTemperature', $targetTemperature, $is_changed);

            $temperature = $this->GetArrayElem($jdata, 'temperature.1.value_localized', 0);
            if ($temperature <= -326) {
                $temperature = 0;
            }
            $this->SaveValue('Freezer_Temperature', $temperature, $is_changed);
        }

        if ($with['Door']) {
            $signalDoor = (bool) $this->GetArrayElem($jdata, 'signalDoor', false);
            $this->SaveValue('Door', $signalDoor, $is_changed);
        }

        if ($with['oven_temp']) {
            $targetTemperature = $this->GetArrayElem($jdata, 'targetTemperature.0.value_localized', 0);
            if ($targetTemperature <= -326) {
                $targetTemperature = 0;
            }
            $this->SaveValue('Oven_TargetTemperature', $targetTemperature, $is_changed);

            $temperature = $this->GetArrayElem($jdata, 'temperature.0.value_localized', 0);
            if ($temperature <= -326) {
                $temperature = 0;
            }
            $this->SaveValue('Oven_Temperature', $temperature, $is_changed);
        }

        if ($with['ecoFeedback_Water']) {
            $ecoFeedback = $this->GetArrayElem($jdata, 'ecoFeedback', '');
            $state = $this->GetValue('State');
            if ($state == self::$STATUS_END_PROGRAMMED) {
                $ecoFeedback = false;
            }
            if ($ecoFeedback != false) {
                $currentWaterConsumption = $this->GetArrayElem($ecoFeedback, 'currentWaterConsumption.value', 0);
                $waterforecast = $this->GetArrayElem($ecoFeedback, 'waterforecast', 0);
                $estimatedWaterConsumption = $currentWaterConsumption * (float) $waterforecast * 100;
                $this->SendDebug(__FUNCTION__, 'WaterConsumption: current=' . $currentWaterConsumption . ', forecast=' . $waterforecast . ', $estimated=' . $estimatedWaterConsumption, 0);
            } else {
                $currentWaterConsumption = $this->GetValue('CurrentWaterConsumption');
                if ($currentWaterConsumption > 0) {
                    $this->SaveValue('LastWaterConsumption', $currentWaterConsumption, $is_changed);
                }
                $currentWaterConsumption = 0;
                $estimatedWaterConsumption = 0;
            }
            $this->SaveValue('CurrentWaterConsumption', $currentWaterConsumption, $is_changed);
            $this->SaveValue('EstimatedWaterConsumption', $estimatedWaterConsumption, $is_changed);
        }
        if ($with['ecoFeedback_Energy']) {
            $ecoFeedback = $this->GetArrayElem($jdata, 'ecoFeedback', '');
            $this->SendDebug(__FUNCTION__, 'ecoFeedback=' . print_r($ecoFeedback, true), 0);
            $state = $this->GetValue('State');
            if ($state == self::$STATUS_END_PROGRAMMED) {
                $ecoFeedback = false;
            }
            if ($ecoFeedback != false) {
                $currentEnergyConsumption = $this->GetArrayElem($ecoFeedback, 'currentEnergyConsumption.value', 0);
                $energyforecast = $this->GetArrayElem($ecoFeedback, 'energyforecast', 0);
                $estimatedEnergyConsumption = $currentEnergyConsumption * (float) $energyforecast * 100;
                $this->SendDebug(__FUNCTION__, 'EnergyConsumption: current=' . $currentEnergyConsumption . ', forecast=' . $energyforecast . ', $estimated=' . $estimatedEnergyConsumption, 0);
            } else {
                $currentEnergyConsumption = $this->GetValue('CurrentEnergyConsumption');
                if ($currentEnergyConsumption > 0) {
                    $this->SaveValue('LastEnergyConsumption', $currentEnergyConsumption, $is_changed);
                }
                $currentEnergyConsumption = 0;
                $estimatedEnergyConsumption = 0;
            }
            $this->SaveValue('CurrentEnergyConsumption', $currentEnergyConsumption, $is_changed);
            $this->SaveValue('EstimatedEnergyConsumption', $estimatedEnergyConsumption, $is_changed);
        }

        if ($with['batteryLevel']) {
            $batteryLevel = $this->GetArrayElem($jdata, 'batteryLevel', 0);
            $this->SendDebug(__FUNCTION__, 'batteryLevel=' . print_r($batteryLevel, true), 0);
            $this->SaveValue('BatteryLevel', (int) $batteryLevel, $is_changed);
        }

        if ($is_changed) {
            $this->SetValue('LastChange', $now);
        }

        $actions = $this->getEnabledActions(true);

        if ($with['enabled_action']) {
            if ($this->checkAction('Start', false)) {
                $b = true;
                $v = self::$ACTION_START;
            } elseif ($this->checkAction('Stop', false)) {
                $b = true;
                $v = self::$ACTION_STOP;
            } elseif ($this->checkAction('Pause', false)) {
                $b = true;
                $v = self::$ACTION_PAUSE;
            } else {
                $b = false;
                $v = self::$ACTION_UNDEF;
            }
            $this->SetValue('Action', $v);
            $this->MaintainAction('Action', $b);
            $this->SendDebug(__FUNCTION__, 'MaintainAction "Action": enabled=' . $this->bool2str($b) . ', value=' . $this->GetValueFormatted('Action'), 0);
        }

        if ($with['enabled_superfreezing']) {
            if ($this->checkAction('StartSuperfreezing', false)) {
                $b = true;
                $v = self::$ACTION_START;
            } elseif ($this->checkAction('StopSuperfreezing', false)) {
                $b = true;
                $v = self::$ACTION_STOP;
            } else {
                $b = false;
                $v = self::$ACTION_UNDEF;
            }
            $this->SetValue('Superfreezing', $v);
            $this->MaintainAction('Superfreezing', $b);
            $this->SendDebug(__FUNCTION__, 'MaintainAction "Superfreezing": enabled=' . $this->bool2str($b) . ', value=' . $this->GetValueFormatted('Superfreezing'), 0);
        }

        if ($with['enabled_supercooling']) {
            if ($this->checkAction('StartSupercooling', false)) {
                $b = true;
                $v = self::$ACTION_START;
            } elseif ($this->checkAction('StopSupercooling', false)) {
                $b = true;
                $v = self::$ACTION_STOP;
            } else {
                $b = false;
                $v = self::$ACTION_UNDEF;
            }
            $this->SetValue('Supercooling', $v);
            $this->MaintainAction('Supercooling', $b);
            $this->SendDebug(__FUNCTION__, 'MaintainAction "Supercooling": enabled=' . $this->bool2str($b) . ', value=' . $this->GetValueFormatted('Supercooling'), 0);
        }

        if ($with['enabled_light']) {
            /*
               $light = (bool) $this->GetArrayElem($jdata, 'light', false);
               $this->SaveValue('Light', $light, $is_changed);
             */

            if ($this->checkAction('LightEnable', false)) {
                $b = true;
                $v = self::$LIGHT_ENABLE;
            } elseif ($this->checkAction('LightDisable', false)) {
                $b = true;
                $v = self::$LIGHT_DISABLE;
            } else {
                $b = false;
                $v = self::$LIGHT_UNDEF;
            }
            $this->SetValue('Light', $v);
            $this->MaintainAction('Light', $b);
            $this->SendDebug(__FUNCTION__, 'MaintainAction "Light": enabled=' . $this->bool2str($b) . ', value=' . $this->GetValueFormatted('Light'), 0);
        }

        if ($with['enabled_starttime']) {
            if ($this->checkAction('SetStarttime', false)) {
                $b = true;
            } else {
                $b = false;
            }
            $this->MaintainAction('StartTime', $b);
            $this->SendDebug(__FUNCTION__, 'MaintainAction "StartTime": enabled=' . $this->bool2str($b), 0);
        }

        if ($with['enabled_powersupply']) {
            /*
               $power = $status == self::$STATUS_OFF ? self::$POWER_OFF : self::$POWER_ON;
               $this->SaveValue('PowerSupply', $power, $is_changed);
             */

            if ($this->checkAction('PowerOn', false)) {
                $b = true;
                $v = self::$POWER_ON;
            } elseif ($this->checkAction('PowerOff', false)) {
                $b = true;
                $v = self::$POWER_OFF;
            } else {
                $b = false;
                $v = self::$POWER_UNDEF;
            }
            $this->SetValue('PowerSupply', $v);
            $this->MaintainAction('PowerSupply', $b);
            $this->SendDebug(__FUNCTION__, 'MaintainAction "PowerSupply": enabled=' . $this->bool2str($b) . ', value=' . $this->GetValueFormatted('PowerSupply'), 0);
        }
    }

    private function programId2text($model, $id)
    {
        $id2txt = [
            0 => [
                0 => ''
            ],
        ];

        if (isset($id2txt[$model][$id])) {
            $txt = $this->Translate($id2txt[$model][$id]);
        } elseif (isset($id2txt[0][$id])) {
            $txt = $this->Translate($id2txt[0][$id]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $id;
            $e = 'unknown value ' . $id;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_NOTIFY);
        }
        return $txt;
    }

    private function programType2text($model, $type)
    {
        $type2txt = [
            0 => [
                0 => 'Normal operation mode',
                1 => 'Own program',
                2 => 'Automatic program',
                3 => 'Cleaning-/Care program',
            ],

            self::$DEVICE_TUMBLE_DRYER => [
                2 => 'Automatic plus',
                3 => 'Cotton',
            ],

            self::$DEVICE_DISHWASHER => [
                2 => 'Intensiv',
            ],
        ];

        if (isset($type2txt[$model][$type])) {
            $txt = $this->Translate($type2txt[$model][$type]);
        } elseif (isset($type2txt[0][$type])) {
            $txt = $this->Translate($type2txt[0][$type]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $type;
            $e = 'unknown value ' . $type;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_NOTIFY);
        }
        return $txt;
    }

    private function programPhase2text($model, $phase)
    {
        $phase2txt = [
            0 => [
                0 => 'Ready',
            ],
            self::$DEVICE_WASHING_MACHINE => [
                256 => 'Not running',
                257 => 'Pre-wash',
                258 => 'Soak',
                259 => 'Pre-wash',
                260 => 'Main wash',
                261 => 'Rinse',
                262 => 'Rinse hold',
                263 => 'Main wash',
                264 => 'Cooling down',
                265 => 'Drain',
                266 => 'Spin',
                267 => 'Anti-crease',
                268 => 'Finished',
                269 => 'Venting',
                270 => 'Starch stop',
                271 => 'Freshen-up + moisten',
                272 => 'Steam smoothing',
                279 => 'Hygiene',
                280 => 'Drying',
                285 => 'Disinfection',
                295 => 'Steam smoothing',
            ],

            self::$DEVICE_TUMBLE_DRYER => [
                512 => 'Not running',
                513 => 'Program running',
                514 => 'Drying',
                515 => 'Machine iron',
                516 => 'Hand iron',
                517 => 'Normal',
                518 => 'Normal plus',
                519 => 'Cooling down',
                520 => 'Hand iron',
                521 => 'Anti-crease',
                522 => 'Finished',
                523 => 'Extra dry',
                524 => 'Hand iron',
                526 => 'Moisten',
                528 => 'Timed drying',
                529 => 'Warm air',
                530 => 'Steam smoothing',
                531 => 'Comfort cooling',
                532 => 'Rinse out lint',
                533 => 'Rinses',
                534 => 'Smoothing',
                537 => 'Programmed',
                538 => 'Slightly dry',
                539 => 'Safety cooling',
            ],

            self::$DEVICE_DISHWASHER => [
                1792 => 'Not running',
                1793 => 'Reactivating',
                1794 => 'Pre-wash',
                1795 => 'Main wash',
                1796 => 'Rinse',
                1797 => 'Interim rinse',
                1798 => 'Final rinse',
                1799 => 'Drying',
                1800 => 'Finished',
                1801 => 'Pre-wash',
            ],

            self::$DEVICE_OVEN => [
                3072 => 'Not running',
                3073 => 'Heating up',
                3074 => 'In progress',
                3078 => 'Finished',
                3840 => 'Save energy',
            ],

            self::$DEVICE_STEAM_OVEN_COMBINATION => [
                3840 => 'Rinse',
                7938 => 'In progress',
                7940 => 'Heating up',
                7941 => 'Cooling down',
                7942 => 'Finished',
            ],
        ];

        if (isset($phase2txt[$model][$phase])) {
            $txt = $this->Translate($phase2txt[$model][$phase]);
        } elseif (isset($phase2txt[0][$phase])) {
            $txt = $this->Translate($phase2txt[0][$phase]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $phase;
            $e = 'unknown value ' . $phase;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_NOTIFY);
        }
        return $txt;
    }

    private function dryingStep2text($model, $step)
    {
        $this->SendDebug(__FUNCTION__, 'model=' . $model . ', step=' . $step, 0);
        $step2txt = [
            0 => [
                0 => 'Extra Dry',
                1 => 'Normal Plus',
                2 => 'Normal',
                3 => 'Slightly Dry',
                4 => 'Hand iron level 1',
                5 => 'Hand iron level 2',
                6 => 'Machine iron',
                7 => 'Smooth',
            ],
        ];

        if (isset($step2txt[$model][$step])) {
            $txt = $this->Translate($step2txt[$model][$step]);
        } elseif (isset($step2txt[0][$step])) {
            $txt = $this->Translate($step2txt[0][$step]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $step;
            $e = 'unknown value ' . $step;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_NOTIFY);
        }
        return $txt;
    }

    private function ventilationStep2text($model, $step)
    {
        $step2txt = [
            0 => [
                0 => 'None',
                1 => 'Step 1',
                2 => 'Step 2',
                3 => 'Step 3',
                4 => 'Step 4',
            ],
        ];

        if (isset($step2txt[$model][$step])) {
            $txt = $this->Translate($step2txt[$model][$step]);
        } elseif (isset($step2txt[0][$step])) {
            $txt = $this->Translate($step2txt[0][$step]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $step;
            $e = 'unknown value ' . $step;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_NOTIFY);
        }
        return $txt;
    }

    private function checkAction($func, $verbose)
    {
        $enabled = false;

        $actions = $this->getEnabledActions(false);
        $processAction = isset($actions['processAction']) ? $actions['processAction'] : [];
        $light = isset($actions['light']) ? $actions['light'] : [];
        $powerOff = isset($actions['powerOff']) ? $actions['powerOff'] : false;
        $powerOn = isset($actions['powerOn']) ? $actions['powerOn'] : false;

        switch ($func) {
            case 'Start':
                if (in_array(self::$PROCESS_START, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'Stop':
                if (in_array(self::$PROCESS_STOP, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'Pause':
                if (in_array(self::$PROCESS_PAUSE, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'StartSuperfreezing':
                if (in_array(self::$PROCESS_START_SUPERFREEZING, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'StopSuperfreezing':
                if (in_array(self::$PROCESS_STOP_SUPERFREEZING, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'StartSupercooling':
                if (in_array(self::$PROCESS_START_SUPERCOOLING, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'StopSupercooling':
                if (in_array(self::$PROCESS_STOP_SUPERCOOLING, $processAction)) {
                    $enabled = true;
                }
                break;
            case 'LightEnable':
                if (in_array(self::$LIGHT_ENABLE, $light)) {
                    $enabled = true;
                }
                break;
            case 'LightDisable':
                if (in_array(self::$LIGHT_DISABLE, $light)) {
                    $enabled = true;
                }
                break;
            case 'PowerOn':
                if ($powerOn == true) {
                    $enabled = true;
                }
                break;
            case 'PowerOff':
                if ($powerOff == true) {
                    $enabled = true;
                }
                break;
            case 'SetStarttime':
                /*
                   $state = $this->GetValue('State');
                   if (in_array($state, [self::$STATUS_ON, self::$STATUS_PROGRAMMED, self::$STATUS_WAITING_TO_START])) {
                        $enabled = true;
                   }
                 */
                break;
            default:
                break;
        }

        $this->SendDebug(__FUNCTION__, 'action "' . $func . '" is ' . ($enabled ? 'enabled' : 'disabled'), 0);
        if ($verbose && !$enabled) {
            $this->LogMessage(__FUNCTION__ . ': action "' . $func . '" is not enabled for ' . IPS_GetName($this->InstanceID), KL_WARNING);
        }
        return $enabled;
    }

    private function CallAction($func, $action)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'func=' . $func . ', action=' . print_r($action, true), 0);

        $fabNumber = $this->ReadPropertyString('fabNumber');

        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'Action', 'Ident' => $fabNumber, 'Action' => $action];
        $this->SendDebug(__FUNCTION__, 'SendData=' . print_r($SendData, true), 0);
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        $jdata = json_decode($data, true);

        return $jdata['Status'];
    }

    public function Start()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_START
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function Stop()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_STOP
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function Pause()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_PAUSE
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function StartSuperfreezing()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_START_SUPERFREEZING
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function StopSuperfreezing()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_STOP_SUPERFREEZING
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function StartSupercooling()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_START_SUPERCOOLING
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function StopSupercooling()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'processAction' => self::$PROCESS_STOP_SUPERCOOLING
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function LightEnable()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'light' => self::$LIGHT_ENABLE
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function LightDisable()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'light' => self::$LIGHT_DISABLE
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function PowerOn()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'powerOn' => true
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function PowerOff()
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'powerOff' => true
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function SetStarttime(int $hour, int $min)
    {
        if (!$this->checkAction(__FUNCTION__, true)) {
            return false;
        }

        $action = [
            'startTime' => [$hour, $min]
        ];

        return $this->CallAction(__FUNCTION__, $action);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return;
        }

        $r = false;
        switch ($Ident) {
            case 'StartTime':
                $hour = (int) date('H', $Value);
                $min = (int) date('i', $Value);
                $r = $this->SetStarttime($hour, $min);
                if ($r) {
                    $this->SetValue($Ident, $Value);
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' (' . $hour . ':' . $min . ') => ret=' . $r, 0);
                break;
            case 'Action':
                switch ($Value) {
                    case self::$ACTION_START:
                        $r = $this->Start();
                        break;
                    case self::$ACTION_PAUSE:
                        $r = $this->Pause();
                        break;
                    case self::$ACTION_STOP:
                        $r = $this->Stop();
                        break;
                    default:
                        $r = false;
                        break;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'Superfreezing':
                switch ($Value) {
                    case self::$ACTION_START:
                        $r = $this->StartSuperfreezing();
                        break;
                    case self::$ACTION_STOP:
                        $r = $this->StopSuperfreezing();
                        break;
                    default:
                        $r = false;
                        break;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'Supercooling':
                switch ($Value) {
                    case self::$ACTION_START:
                        $r = $this->StartSupercooling();
                        break;
                    case self::$ACTION_STOP:
                        $r = $this->StopSupercooling();
                        break;
                    default:
                        $r = false;
                        break;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'Light':
                switch ($Value) {
                    case self::$LIGHT_ENABLE:
                        $r = $this->LightEnable();
                        break;
                    case self::$LIGHT_DISABLE:
                        $r = $this->LightDisable();
                        break;
                    default:
                        $r = false;
                        break;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            case 'PowerSupply':
                switch ($Value) {
                    case self::$POWER_ON:
                        $r = $this->PowerOn();
                        break;
                    case self::$POWER_OFF:
                        $r = $this->PowerOff();
                        break;
                    default:
                        $r = false;
                        break;
                }
                $this->SendDebug(__FUNCTION__, $Ident . '=' . $Value . ' => ret=' . $r, 0);
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $Ident, 0);
                break;
        }
        if ($r) {
            $this->SetTimerInterval('UpdateData', 2000);
        }
    }

    private function getEnabledActions(bool $force)
    {
        if ($this->HasActiveParent() == false) {
            $this->SendDebug(__FUNCTION__, 'has no active parent', 0);
            $this->LogMessage('has no active parent instance', KL_WARNING);
            return false;
        }

        $data = $force ? '' : $this->GetBuffer('EnabledActions');
        if ($data == '') {
            $fabNumber = $this->ReadPropertyString('fabNumber');
            $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDeviceActions', 'Ident' => $fabNumber];
            $data = $this->SendDataToParent(json_encode($SendData));
            $this->SetBuffer('EnabledActions', $data);
            $actions = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'enabled actions=' . print_r($actions, true), 0);
        } else {
            $actions = json_decode($data, true);
        }
        return $actions;
    }
}
