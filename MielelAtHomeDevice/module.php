<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class MieleAtHomeDevice extends IPSModule
{
    use MieleAtHomeCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyInteger('deviceId', 0);
        $this->RegisterPropertyString('deviceType', '');
        $this->RegisterPropertyString('fabNumber', '');
        $this->RegisterPropertyString('techType', '');

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterPropertyBoolean('map_status', false);
        $this->RegisterPropertyBoolean('map_programType', false);
        $this->RegisterPropertyBoolean('map_programPhase', false);
        $this->RegisterPropertyBoolean('map_dryingStep', false);
        $this->RegisterPropertyBoolean('map_ventilationStep', false);

        $this->CreateVarProfile('MieleAtHome.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass');
        $this->CreateVarProfile('MieleAtHome.Temperature', VARIABLETYPE_INTEGER, ' °C', 0, 0, 0, 0, 'Temperature');
        $this->CreateVarProfile('MieleAtHome.SpinningSpeed', VARIABLETYPE_INTEGER, ' U/min', 0, 0, 0, 0, '');

        $associations = [];
        $associations[] = ['Wert' => STATUS_UNKNOWN, 'Name' => $this->Translate('Unknown'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_RESERVED, 'Name' => $this->Translate('Reserved'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_PROGRAMMED, 'Name' => $this->Translate('Programmed'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_WAITING_TO_START, 'Name' => $this->Translate('Waiting to start'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_RUNNING, 'Name' => $this->Translate('Running'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_PAUSE, 'Name' => $this->Translate('Pause'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_END_PROGRAMMED, 'Name' => $this->Translate('End programmed'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_FAILURE, 'Name' => $this->Translate('Failure'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_PROGRAM_INTERRUPTED, 'Name' => $this->Translate('Program interrupted'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_IDLE, 'Name' => $this->Translate('Idle'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_RINSE_HOLD, 'Name' => $this->Translate('Rinse hold'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_SERVICE, 'Name' => $this->Translate('Service'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_SUPERFREEZING, 'Name' => $this->Translate('Superfreezing'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_SUPERCOOLING, 'Name' => $this->Translate('Supercooling'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_SUPERHEATING, 'Name' => $this->Translate('Superheating'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_NOT_CONNECTED, 'Name' => $this->Translate('Not connected'), 'Farbe' => -1];
        $associations[] = ['Wert' => STATUS_SUPERCOOLING_SUPERFREEZING, 'Name' => $this->Translate('Superfrost/cooling'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Status', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations);

        $associations = [];
        $associations[] = ['Wert' => false, 'Name' => $this->Translate('Closed'), 'Farbe' => -1];
        $associations[] = ['Wert' => true, 'Name' => $this->Translate('Opened'), 'Farbe' => 0xEE0000];
        $this->CreateVarProfile('MieleAtHome.Door', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, 'Door', $associations);

        $associations = [];
        $associations[] = ['Wert' => 1, 'Name' => $this->Translate('Enabled'), 'Farbe' => -1];
        $associations[] = ['Wert' => 2, 'Name' => $this->Translate('Disabled'), 'Farbe' => -1];
        $this->CreateVarProfile('MieleAtHome.Light', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, 'Light', $associations);

        $this->RegisterTimer('UpdateData', 0, 'MieleAtHomeDevice_UpdateData(' . $this->InstanceID . ');');

        $this->ConnectParent('{996743FB-1712-47A3-9174-858A08A13523}');
    }

    private function device2with($deviceId)
    {
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
        $with['Light'] = false;

        switch ($deviceId) {
            case DEVICE_WASHING_MACHINE:    // Waschmaschine
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['wash_temp'] = true;
                $with['SpinningSpeed'] = true;
                $with['Door'] = true;
                break;
            case DEVICE_TUMBLE_DRYER:      // Trockner
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['DryingStep'] = true;
                $with['Door'] = true;
                break;
            case DEVICE_DISHWASHER:         // Geschirrspüler
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['Door'] = true;
                break;
            case DEVICE_OVEN:               // Backofen
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['oven_temp'] = true;
                $with['Door'] = true;
                break;
            case DEVICE_OVEN_MICROWAVE:     // Backofen mit Mikrowelle
                $with['ProgramType'] = true;
                $with['ProgramPhase'] = true;
                $with['times'] = true;
                $with['oven_temp'] = true;
                $with['Door'] = true;
                break;
            case DEVICE_FRIDGE_FREEZER:     // Küh-/Gefrierkombination
                $with['fridge_temp'] = true;
                $with['freezer_temp'] = true;
                $with['Door'] = true;
                break;
        }
        return $with;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $deviceId = $this->ReadPropertyInteger('deviceId');
        $deviceType = $this->ReadPropertyString('deviceType');

        $with = $this->device2with($deviceId);

        $this->SendDebug(__FUNCTION__, 'with=' . print_r($with, true), 0);

        $vpos = 1;

        $this->MaintainVariable('State', $this->Translate('State'), VARIABLETYPE_INTEGER, 'MieleAtHome.Status', $vpos++, true);
        $this->MaintainVariable('StateAsText', $this->Translate('State'), VARIABLETYPE_STRING, '', $vpos++, true);
        $this->MaintainVariable('Failure', $this->Translate('Failure'), VARIABLETYPE_BOOLEAN, 'Alert', $vpos++, true);

        $this->MaintainVariable('ProgramType', $this->Translate('Program'), VARIABLETYPE_STRING, '', $vpos++, $with['ProgramType']);

        $this->MaintainVariable('ProgramPhase', $this->Translate('Phase'), VARIABLETYPE_STRING, '', $vpos++, $with['ProgramPhase']);

        $this->MaintainVariable('StartTime', $this->Translate('Start at'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $with['times']);
        $this->MaintainVariable('ElapsedTime', $this->Translate('Elapsed time'), VARIABLETYPE_INTEGER, 'MieleAtHome.Duration', $vpos++, $with['times']);
        $this->MaintainVariable('RemainingTime', $this->Translate('Remaining time'), VARIABLETYPE_INTEGER, 'MieleAtHome.Duration', $vpos++, $with['times']);
        $this->MaintainVariable('EndTime', $this->Translate('End at'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, $with['times']);

        $this->MaintainVariable('Wash_TargetTemperature', $this->Translate('Temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['wash_temp']);

        $this->MaintainVariable('SpinningSpeed', $this->Translate('Spinning speed'), VARIABLETYPE_INTEGER, 'MieleAtHome.SpinningSpeed', $vpos++, $with['SpinningSpeed']);

        $this->MaintainVariable('DryingStep', $this->Translate('Drying step'), VARIABLETYPE_STRING, '', $vpos++, $with['DryingStep']);

        $this->MaintainVariable('VentilationStep', $this->Translate('Ventilation step'), VARIABLETYPE_STRING, '', $vpos++, $with['VentilationStep']);

        $this->MaintainVariable('Fridge_TargetTemperature', $this->Translate('Fridge: target temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['fridge_temp']);
        $this->MaintainVariable('Fridge_Temperature', $this->Translate('Fridge: temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['fridge_temp']);

        $this->MaintainVariable('Freezer_TargetTemperature', $this->Translate('Freezer: target temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['freezer_temp']);
        $this->MaintainVariable('Freezer_Temperature', $this->Translate('Freezer: temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['freezer_temp']);

        $this->MaintainVariable('Door', $this->Translate('Door'), VARIABLETYPE_BOOLEAN, 'MieleAtHome.Door', $vpos++, $with['Door']);

        $this->MaintainVariable('Light', $this->Translate('Light'), VARIABLETYPE_INTEGER, 'MieleAtHome.Light', $vpos++, $with['Light']);

        $this->MaintainVariable('Oven_TargetTemperature', $this->Translate('Target temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['oven_temp']);
        $this->MaintainVariable('Oven_Temperature', $this->Translate('Temperature'), VARIABLETYPE_INTEGER, 'MieleAtHome.Temperature', $vpos++, $with['oven_temp']);

        $vpos = 100;
        $this->MaintainVariable('LastChange', $this->Translate('last change'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $techType = $this->ReadPropertyString('techType');
        $fabNumber = $this->ReadPropertyString('fabNumber');
        $this->SetSummary($techType . ' (#' . $fabNumber . ')');

        $this->SetStatus(IS_ACTIVE);

        $this->SetUpdateInterval();
    }

    public function GetConfigurationForm()
    {
        $formElements = [];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'deviceId', 'caption' => 'Device id'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'deviceType', 'caption' => 'Device type'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'fabNumber', 'caption' => 'Fabrication number'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'techType', 'caption' => 'Model'];

        $formElements[] = ['type' => 'Label', 'label' => 'mapping code to text of field ...'];

        $formElements[] = ['type' => 'CheckBox', 'name' => 'map_status', 'caption' => ' ... Status'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'map_programType', 'caption' => ' ... Program'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'map_programPhase', 'caption' => ' ... Phase'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'map_dryingStep', 'caption' => ' ... Drying step'];
        $formElements[] = ['type' => 'CheckBox', 'name' => 'map_ventilationStep', 'caption' => ' ... Ventilation step'];

        $formElements[] = ['type' => 'Label', 'label' => 'Update data every X seconds'];
        $formElements[] = ['type' => 'IntervalBox', 'name' => 'update_interval', 'caption' => 'Seconds'];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Update data', 'onClick' => 'MieleAtHomeDevice_UpdateData($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconMieleAtHome/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    protected function SetUpdateInterval()
    {
        $sec = $this->ReadPropertyInteger('update_interval');
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    public function UpdateData()
    {
        $fabNumber = $this->ReadPropertyString('fabNumber');
        $deviceId = $this->ReadPropertyInteger('deviceId');

        $map_status = $this->ReadPropertyBoolean('map_status');
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

        $off = $this->GetArrayElem($jdata, 'status.value_raw', 0) == 1;
        $delayed = $this->GetArrayElem($jdata, 'status.value_raw', 0) == 4;
        $is_changed = false;

        $value_raw = $this->GetArrayElem($jdata, 'status.value_raw', 0);
        $status = $map_status ? '' : $this->GetArrayElem($jdata, 'status.value_localized', '');
        if ($status == '') {
            $status = $this->status2text($deviceId, $value_raw);
        }
        $this->SaveValue('StateAsText', $status, $is_changed);
        $r = IPS_GetVariableProfile('MieleAtHome.Status');
        $st = STATUS_UNKNOWN;
        foreach ($r['Associations'] as $a) {
            if ($a['Value'] == $value_raw) {
                $st = $value_raw;
                break;
            }
        }
        $this->SaveValue('State', $st, $is_changed);

        $signalFailure = $this->GetArrayElem($jdata, 'signalFailure', false);
        $this->SaveValue('Failure', $signalFailure, $is_changed);

        $dt = new DateTime(date('d.m.Y H:i:00'));
        $now = $dt->format('U');

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

        if ($with['ProgramPhase']) {
            if ($off) {
                $programPhase = '';
            } else {
                $programPhase = $map_programPhase ? '' : $this->GetArrayElem($jdata, 'programPhase.value_localized', '');
                if ($programPhase == '') {
                    $value_raw = $this->GetArrayElem($jdata, 'programPhase.value_raw', 0);
                    $programPhase = $this->programPhase2text($deviceId, $value_raw);
                }
            }
            $this->SaveValue('ProgramPhase', $programPhase, $is_changed);
        }

        if ($with['times']) {
            if ($off) {
                $remainingTime = 0;
                $elapsedTime = 0;
                $startTime = 0;
                $endTime = 0;
            } else {
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
                } else {
                    $elapsedTime_H = $this->GetArrayElem($jdata, 'elapsedTime.0', 0);
                    $elapsedTime_M = $this->GetArrayElem($jdata, 'elapsedTime.1', 0);
                    $elapsedTime = $elapsedTime_H * 60 + $elapsedTime_M;

                    if ($remainingTime > 0) {
                        $endTime = $now + $remainingTime * 60;
                    }
                    if ($elapsedTime > 0) {
                        $startTime = $now - $elapsedTime * 60;
                    }
                }
            }
            $this->SaveValue('RemainingTime', $remainingTime, $is_changed);
            $this->SaveValue('ElapsedTime', $elapsedTime, $is_changed);
            $this->SaveValue('StartTime', $startTime, $is_changed);
            $this->SaveValue('EndTime', $endTime, $is_changed);
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
                $spinningSpeed = $this->GetArrayElem($jdata, 'spinningSpeed', 0);
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
            $signalDoor = $this->GetArrayElem($jdata, 'signalDoor', false);
            $this->SaveValue('Door', $signalDoor, $is_changed);
        }

        if ($with['Light']) {
            $light = $this->GetArrayElem($jdata, 'light', false);
            $this->SaveValue('Light', $light, $is_changed);
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

        if ($is_changed) {
            $this->SetValue('LastChange', $now);
        }
    }

    private function status2text($model, $status)
    {
        $status2txt = [
                0 => [
                        STATUS_RESERVED            => 'Reserved',
                        STATUS_OFF                 => 'Off',
                        STATUS_ON                  => 'On',
                        STATUS_PROGRAMMED          => 'Programmed',
                        STATUS_WAITING_TO_START    => 'Waiting to start',
                        STATUS_RUNNING             => 'Running',
                        STATUS_PAUSE               => 'Pause',
                        STATUS_END_PROGRAMMED      => 'End programmed',
                        STATUS_FAILURE             => 'Failure',
                        STATUS_PROGRAM_INTERRUPTED => 'Program interrupted',
                        STATUS_IDLE                => 'Idle',
                        STATUS_RINSE_HOLD          => 'Rinse hold',
                        STATUS_SERVICE             => 'Service',
                        STATUS_SUPERFREEZING       => 'Superfreezing',
                        STATUS_SUPERCOOLING        => 'Supercooling',
                        STATUS_SUPERHEATING        => 'Superheating',
                        STATUS_NOT_CONNECTED       => 'Not connected'
                    ],

                DEVICE_FRIDGE_FREEZER => [
                        STATUS_SUPERCOOLING_SUPERFREEZING => 'Superfreezing/cooling',
                    ],
            ];

        if (isset($status2txt[$model][$status])) {
            $txt = $this->Translate($status2txt[$model][$status]);
        } elseif (isset($status2txt[0][$status])) {
            $txt = $this->Translate($status2txt[0][$status]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $status;
            $e = 'unknown value ' . $status;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
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

                DEVICE_TUMBLE_DRYER => [
                        2 => 'Automatic plus',
                    ],

                DEVICE_DISHWASHER => [
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
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
        }
        return $txt;
    }

    private function programPhase2text($model, $phase)
    {
        $phase2txt = [
            DEVICE_WASHING_MACHINE => [
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

            DEVICE_TUMBLE_DRYER => [
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
                    538 => 'Slightly dry',
                    539 => 'Safety cooling',
                ],

                DEVICE_DISHWASHER => [
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
            ];

        if (isset($phase2txt[$model][$phase])) {
            $txt = $this->Translate($phase2txt[$model][$phase]);
        } else {
            $txt = $this->Translate('unknown value') . ' ' . $phase;
            $e = 'unknown value ' . $phase;
            $this->SendDebug(__FUNCTION__, $e, 0);
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
        }
        return $txt;
    }

    private function dryingStep2text($model, $step)
    {
        $step2xt = [
                0 => [
                        1 => 'Normal Plus',
                        2 => 'Normal',
                        3 => 'Slightly Dry',
                        4 => 'Hand iron level 1',
                        5 => 'Hand iron level 2',
                        6 => 'Machine iron',
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
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
        }
        return $txt;
    }

    private function ventilationStep2text($model, $step)
    {
        $step2xt = [
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
            $this->LogMessage(__FUNCTION__ . ': ' . $e, KL_WARNING);
        }
        return $txt;
    }

    private function CallAction($func, $deviceIds, $states, $action)
    {
        $this->SendDebug(__FUNCTION__, 'func=' . $func . ', action=' . print_r($action, true) . ', states=' . print_r($states, true) . ', deviceIds=' . print_r($deviceIds, true), 0);

        $deviceId = $this->ReadPropertyInteger('deviceId');
        if ($deviceIds != [] && !in_array($deviceId, $deviceIds)) {
            $this->SendDebug(__FUNCTION__, 'func ' . $func . ': deviceId ' . $deviceId . ' is not in ' . implode(',', $deviceIds), 0);
            $this->LogMessage(__FUNCTION__ . ': func ' . $func . ' is not allowed for deviceId ' . $deviceId, KL_WARNING);
            return false;
        }

        $state = $this->GetValue('State');
        if ($states != [] && !in_array($state, $states)) {
            $this->SendDebug(__FUNCTION__, 'func ' . $func . ': state ' . $state . ' is not in ' . implode(',', $states), 0);
            $this->LogMessage(__FUNCTION__ . ': func ' . $func . ' is not allowed for state ' . $state, KL_WARNING);
            return false;
        }

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
        $deviceIds = [
                DEVICE_WASHING_MACHINE,
                DEVICE_TUMBLE_DRYER,
                DEVICE_DISHWASHER,
                DEVICE_WASHER_DRYER,
            ];

        $states = [
                STATUS_WAITING_TO_START,
            ];

        $action = [
                'processAction' => 1
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function Stop()
    {
        $deviceIds = [
                DEVICE_WASHING_MACHINE,
                DEVICE_TUMBLE_DRYER,
                DEVICE_DISHWASHER,
                DEVICE_OVEN_MICROWAVE,
                DEVICE_COFFEE_SYSTEM,
                DEVICE_HOOD,
                DEVICE_WASHER_DRYER,
                DEVICE_STEAM_OVEN_COMBINATION,
                DEVICE_STEAM_OVEN_MICROWAVE_COMBINATION,
                DEVICE_DIALOGOVEN,
            ];

        $states = [
                STATUS_WAITING_TO_START,
                STATUS_RUNNING,
                STATUS_PAUSE,
            ];

        $action = [
                'processAction' => 2
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function Pause()
    {
        $deviceIds = [
            ];

        $states = [
            ];

        $action = [
                'processAction' => 3
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function StartSuperfreezing()
    {
        $deviceIds = [
                DEVICE_FREEZER,
                DEVICE_FRIDGE_FREEZER,
                DEVICE_WINE_CABINET_FREEZER_COMBINATION
            ];

        $states = [
                STATUS_RUNNING
            ];

        $action = [
                'processAction' => 4
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function StopSuperfreezing()
    {
        $deviceIds = [
                DEVICE_FREEZER,
                DEVICE_FRIDGE_FREEZER,
                DEVICE_WINE_CABINET_FREEZER_COMBINATION
            ];

        $states = [
                STATUS_SUPERFREEZING,
                STATUS_SUPERCOOLING_SUPERFREEZING
            ];

        $action = [
                'processAction' => 5
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function StartSupercooling()
    {
        $deviceIds = [
                DEVICE_FRIDGE,
                DEVICE_FRIDGE_FREEZER
            ];

        $states = [
                STATUS_RUNNING
            ];

        $action = [
                'processAction' => 6
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function StopSupercooling()
    {
        $deviceIds = [
                DEVICE_FRIDGE,
                DEVICE_FRIDGE_FREEZER
            ];

        $states = [
                STATUS_SUPERCOOLING,
                STATUS_SUPERCOOLING_SUPERFREEZING
            ];

        $action = [
                'processAction' => 7
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function LightEnable()
    {
        $deviceIds = [
                DEVICE_COFFEE_SYSTEM,
                DEVICE_HOOD,
                DEVICE_WINE_CABINET,
                DEVICE_WINE_CONDITIONING_UNIT,
                DEVICE_WINE_STORAGE_CONDITIONING_UNIT,
                DEVICE_WINE_CABINET_FREEZER_COMBINATION,
            ];

        $states = [
                STATUS_RUNNING
            ];

        $action = [
                'light' => 1
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function LightDisable()
    {
        $deviceIds = [
                DEVICE_COFFEE_SYSTEM,
                DEVICE_HOOD,
                DEVICE_WINE_CABINET,
                DEVICE_WINE_CONDITIONING_UNIT,
                DEVICE_WINE_STORAGE_CONDITIONING_UNIT,
                DEVICE_WINE_CABINET_FREEZER_COMBINATION,
            ];

        $states = [
                STATUS_RUNNING
            ];

        $action = [
                'light' => 2
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }

    public function SetStarttime(int $hour, int $min)
    {
        $deviceIds = [
                DEVICE_WASHING_MACHINE,
                DEVICE_TUMBLE_DRYER,
                DEVICE_DISHWASHER,
            ];

        $states = [
                STATUS_RUNNING
            ];

        $action = [
                'startTime' => [$hour, $min]
            ];

        return $this->CallAction(__FUNCTION__, $deviceIds, $states, $action);
    }
}
