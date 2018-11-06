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

        $this->CreateVarProfile('MieleAtHome.Duration', vtInteger, ' min', 0, 0, 0, 0, 'Hourglass');
        $this->CreateVarProfile('MieleAtHome.Temperature', vtInteger, ' °C', 0, 0, 0, 0, 'Temperature');
        $this->CreateVarProfile('MieleAtHome.SpinningSpeed', vtInteger, ' U/min', 0, 0, 0, 0, '');

        $this->RegisterTimer('UpdateData', 0, 'MieleAtHomeDevice_UpdateData(' . $this->InstanceID . ');');

        $this->ConnectParent('{996743FB-1712-47A3-9174-858A08A13523}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $deviceId = $this->ReadPropertyInteger('deviceId');
        $deviceType = $this->ReadPropertyString('deviceType');

        $vpos = 1;

        /*
        switch ($deviceId) {
            case DEVICE_WASHING_MACHINE:	// Waschmaschine
                break;
            default:
                $this->SetStatus(IS_INVALIDCONFIG);
                return;
        }
        */

        $this->MaintainVariable('State', $this->Translate('State'), vtString, '', $vpos++, true);
        $this->MaintainVariable('Failure', $this->Translate('Failure'), vtBoolean, 'Alert', $vpos++, true);
        $this->MaintainVariable('ProgramType', $this->Translate('Program'), vtString, '', $vpos++, true);
        $this->MaintainVariable('ProgramPhase', $this->Translate('Phase'), vtString, '', $vpos++, true);

        $this->MaintainVariable('ElapsedTime', $this->Translate('Elapsed time'), vtInteger, 'MieleAtHome.Duration', $vpos++, true);
        $this->MaintainVariable('RemainingTime', $this->Translate('Remaining time'), vtInteger, 'MieleAtHome.Duration', $vpos++, true);

        switch ($deviceId) {
            case DEVICE_WASHING_MACHINE:	// Waschmaschine
                $this->MaintainVariable('TargetTemperature', $this->Translate('Temperature'), vtInteger, 'MieleAtHome.Temperature', $vpos++, true);
                $this->MaintainVariable('SpinningSpeed', $this->Translate('Spinning speed'), vtInteger, 'MieleAtHome.SpinningSpeed', $vpos++, true);
                break;
        }

        $this->MaintainVariable('LastChange', $this->Translate('last change'), vtInteger, '~UnixTimestamp', $vpos++, true);

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

        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDeviceStatus', 'Ident' => $fabNumber];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        if ($data == '') {
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        $off = $this->GetArrayElem($jdata, 'status.value_raw', 0) == 1;
        $is_changed = false;

        $status = $this->GetArrayElem($jdata, 'status.value_localized', '');
        $this->SaveValue('State', $status, $is_changed);

        $signalFailure = $this->GetArrayElem($jdata, 'signalFailure', false);
        $this->SaveValue('Failure', $signalFailure, $is_changed);

        if ($off) {
            $programType = '';
            $programPhase = '';
            $remainingTime = 0;
            $elapsedTime = 0;
        } else {
            $programType = $this->GetArrayElem($jdata, 'programType.value_localized', '');
            $programPhase = $this->GetArrayElem($jdata, 'programPhase.value_localized', '');
            $remainingTime_H = $this->GetArrayElem($jdata, 'remainingTime.0', 0);
            $remainingTime_M = $this->GetArrayElem($jdata, 'remainingTime.1', 0);
            $remainingTime = $remainingTime_H * 60 + $remainingTime_M;
            $elapsedTime_H = $this->GetArrayElem($jdata, 'elapsedTime.0', 0);
            $elapsedTime_M = $this->GetArrayElem($jdata, 'elapsedTime.1', 0);
            $elapsedTime = $elapsedTime_H * 60 + $elapsedTime_M;
        }

        $this->SaveValue('ProgramType', $programType, $is_changed);
        $this->SaveValue('ProgramPhase', $programPhase, $is_changed);
        $this->SaveValue('RemainingTime', $remainingTime, $is_changed);
        $this->SaveValue('ElapsedTime', $elapsedTime, $is_changed);

        switch ($deviceId) {
            case DEVICE_WASHING_MACHINE:	// Waschmaschine
                if ($off) {
                    $targetTemperature = 0;
                    $spinningSpeed = 0;
                } else {
                    $targetTemperature = $this->GetArrayElem($jdata, 'targetTemperature.0.value_localized', 0);
                    if ($targetTemperature == -32768) {
                        $targetTemperature = 0;
                    }
                    $spinningSpeed = $this->GetArrayElem($jdata, 'spinningSpeed', 0);
                }

                $this->SaveValue('TargetTemperature', $targetTemperature, $is_changed);
                $this->SaveValue('SpinningSpeed', $spinningSpeed, $is_changed);
                break;
        }

        if ($is_changed) {
            $this->SetValue('LastChange', time());
        }
    }
}
