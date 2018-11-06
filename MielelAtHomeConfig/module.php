<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class MieleAtHomeConfig extends IPSModule
{
    use MieleAtHomeCommon;

    public function Create()
    {
        parent::Create();

        $this->ConnectParent('{996743FB-1712-47A3-9174-858A08A13523}');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetStatus(102);
    }

    public function GetConfigurationForm()
    {
        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDevices'];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        $options = [];
        if ($data != '') {
            $devices = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'devices=' . print_r($devices, true), 0);
            foreach ($devices as $device) {
                $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);
                $ident = $device['ident'];

                $type = $ident['type']['value_localized'];
                $name = $ident['deviceName'];
                $fabNumber = $ident['deviceIdentLabel']['fabNumber'];

                if ($name == '') {
                    $name = $type . ' (#' . $fabNumber . ')';
                }

                $options[] = ['label' => $name, 'value' => $fabNumber];
            }
        }

        $formElements = [];

        $formActions = [];
        $formActions[] = ['type' => 'Select', 'name' => 'fabNumber', 'caption' => 'Device', 'options' => $options];
        $formActions[] = ['type' => 'Button', 'label' => 'Import of device', 'onClick' => 'MieleAtHomeConfig_Doit($id, $fabNumber);'];
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

    private function FindOrCreateInstance($guid, $fabNumber, $deviceName, $info, $properties, $pos)
    {
        $instID = '';

        $instIDs = IPS_GetInstanceListByModuleID($guid);
        foreach ($instIDs as $id) {
            $cfg = IPS_GetConfiguration($id);
            $jcfg = json_decode($cfg, true);
            if (!isset($jcfg['fabNumber'])) {
                continue;
            }
            if ($jcfg['fabNumber'] == $fabNumber) {
                $instID = $id;
                break;
            }
        }

        if ($instID == '') {
            $instID = IPS_CreateInstance($guid);
            if ($instID == '') {
                echo $this->Translate('unable to create instance') . ' "' . $deviceName . '"';
                return $instID;
            }
            IPS_SetProperty($instID, 'fabNumber', $fabNumber);
            foreach ($properties as $key => $property) {
                IPS_SetProperty($instID, $key, $property);
            }
            IPS_SetName($instID, $deviceName);
            IPS_SetInfo($instID, $info);
            IPS_SetPosition($instID, $pos);
        }

        IPS_ApplyChanges($instID);

        return $instID;
    }

    public function Doit(?string $fabNumber)
    {
        if ($fabNumber == '') {
            $this->SetStatus(IS_INVALIDCONFIG);
            echo $this->Translate('no device selected') . PHP_EOL;
            return -1;
        }

        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDeviceIdent', 'Ident' => $fabNumber];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);
        if ($data == '') {
            $this->SetStatus(IS_INVALIDCONFIG);
            echo $this->Translate('unknown device') . ' "' . $fabNumber . '"' . PHP_EOL;
            return -1;
        }

        $device = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'device=' . print_r($device, true), 0);

        $deviceId = $device['type']['value_raw'];
		/*
        switch ($deviceId) {
            case DEVICE_WASHING_MACHINE:	// Waschmaschine
                break;
            default:
                echo $this->Translate('unkown device id') . ' ' . $deviceId . ' [' . $deviceType . ']' . PHP_EOL;
                $this->SetStatus(IS_INVALIDCONFIG);
                return -1;
        }
		*/

        $deviceType = $device['type']['value_localized'];
        $fabNumber = $device['deviceIdentLabel']['fabNumber'];
        $techType = $device['deviceIdentLabel']['techType'];

        $deviceName = $device['deviceName'];
        if ($deviceName == '') {
            $deviceName = $deviceType;
        }
        $info = $deviceType . ' (' . $techType . ')';
        $properties = [
                'deviceId'     => $deviceId,
                'deviceType'   => $deviceType,
                'fabNumber'    => $fabNumber,
                'techType'     => $techType,
            ];

        $pos = 1000;
        $instID = $this->FindOrCreateInstance('{C2672DE6-E854-40C0-86E0-DE1B6B4C3CAB}', $fabNumber, $deviceName, $info, $properties, $pos++);

        $this->SetStatus(IS_ACTIVE);
    }
}
