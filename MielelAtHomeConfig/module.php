<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

// Model of Sensor
if (!defined('SENSOR_NORMALLY_CLOSE_START')) {
    define('SENSOR_NORMALLY_CLOSE_START', 11);
}
if (!defined('SENSOR_NORMALLY_OPEN_STOP')) {
    define('SENSOR_NORMALLY_OPEN_STOP', 12);
}
if (!defined('SENSOR_NORMALLY_CLOSE_STOP')) {
    define('SENSOR_NORMALLY_CLOSE_STOP', 13);
}
if (!defined('SENSOR_NORMALLY_OPEN_START')) {
    define('SENSOR_NORMALLY_OPEN_START', 14);
}
if (!defined('SENSOR_FLOW_METER')) {
    define('SENSOR_FLOW_METER', 30);
}

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
        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'LastData'];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, "data=$data", 0);

        $options = [];
        if ($data != '') {
            $controllers = json_decode($data, true);
            foreach ($controllers as $controller) {
                $controller_name = $controller['name'];
                $controller_id = $controller['controller_id'];
                $options[] = ['label' => $controller_name, 'value' => $controller_id];
            }
        }

        $formElements = [];

        $formActions = [];
        $formActions[] = ['type' => 'Select', 'name' => 'controller_id', 'caption' => 'Controller', 'options' => $options];
        $formActions[] = ['type' => 'Button', 'label' => 'Import of device', 'onClick' => 'MieleAtHomeConfig_Doit($id, $controller_id);'];
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

    private function FindOrCreateInstance($guid, $controller_id, $connector, $name, $info, $properties, $pos)
    {
        $instID = '';

        $instIDs = IPS_GetInstanceListByModuleID($guid);
        foreach ($instIDs as $id) {
            $cfg = IPS_GetConfiguration($id);
            $jcfg = json_decode($cfg, true);
            if (!isset($jcfg['controller_id'])) {
                continue;
            }
            if ($jcfg['controller_id'] == $controller_id) {
                if ($connector == '' || $jcfg['connector'] == $connector) {
                    $instID = $id;
                    break;
                }
            }
        }

        if ($instID == '') {
            $instID = IPS_CreateInstance($guid);
            if ($instID == '') {
                echo 'unable to create instance "' . $name . '"';
                return $instID;
            }
            IPS_SetProperty($instID, 'controller_id', $controller_id);
            if (is_numeric($connector)) {
                IPS_SetProperty($instID, 'connector', $connector);
            }
            foreach ($properties as $key => $property) {
                IPS_SetProperty($instID, $key, $property);
            }
            IPS_SetName($instID, $name);
            IPS_SetInfo($instID, $info);
            IPS_SetPosition($instID, $pos);
        }

        IPS_ApplyChanges($instID);

        return $instID;
    }

    public function Doit(?string $controller_id)
    {
        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'LastData'];
        $data = $this->SendDataToParent(json_encode($SendData));

        $this->SendDebug(__FUNCTION__, "data=$data", 0);

        $statuscode = 0;
        $do_abort = false;

        if ($data != '') {
            $controllers = json_decode($data, true);
            if ($controller_id != '') {
                $controller_found = false;
                foreach ($controllers as $controller) {
                    if ($controller_id == $controller['controller_id']) {
                        $controller_found = true;
                        break;
                    }
                }
                if (!$controller_found) {
                    $err = "controller \"$controller_id\" don't exists";
                    $statuscode = 202;
                }
            } else {
                $err = 'no controller selected';
                $statuscode = 203;
            }
            if ($statuscode) {
                echo "statuscode=$statuscode, err=$err";
                $this->SendDebug(__FUNCTION__, $err, 0);
                $this->SetStatus($statuscode);
                $do_abort = true;
            }
        } else {
            $err = 'no data';
            $statuscode = 201;
            echo "statuscode=$statuscode, err=$err";
            $this->SendDebug(__FUNCTION__, $err, 0);
            $this->SetStatus($statuscode);
            $do_abort = true;
        }

        if ($do_abort) {
            return -1;
        }

        $this->SetStatus(102);

        $this->SendDebug(__FUNCTION__, 'controller=' . print_r($controller, true), 0);

        // MieleAtHomeController
        $controller_name = $controller['name'];
        $info = 'Controller (' . $controller_name . ')';
        $properties = [];

        $pos = 1000;
        $instID = $this->FindOrCreateInstance('{C2672DE6-E854-40C0-86E0-DE1B6B4C3CAB}', $controller_id, '', $controller_name, $info, $properties, $pos++);

        // MieleAtHomeSensor
        $pos = 1100;
        $sensors = $controller['sensors'];
        if (count($sensors) > 0) {
            foreach ($sensors as $i => $value) {
                $sensor = $sensors[$i];
                $connector = $sensor['input'] + 1;
                $sensor_name = $sensor['name'];
                $type = $sensor['type'];
                $mode = $sensor['mode'];

                // type=1, mode=1 => normally close - start
                // type=1, mode=2 => normally open - stop
                // type=1, mode=3 => normally close - stop
                // type=1, mode=4 => normally open - start
                // type=3, mode=0 => flow meter

                if ($type == 1 && $mode == 1) {
                    $model = SENSOR_NORMALLY_CLOSE_START;
                } elseif ($type == 1 && $mode == 2) {
                    $model = SENSOR_NORMALLY_OPEN_STOP;
                } elseif ($type == 1 && $mode == 3) {
                    $model = SENSOR_NORMALLY_CLOSE_STOP;
                } elseif ($type == 1 && $mode == 4) {
                    $model = SENSOR_NORMALLY_OPEN_START;
                } elseif ($type == 3 && $mode == 0) {
                    $model = SENSOR_FLOW_METER;
                } else {
                    continue;
                }

                $info = $this->Translate('Sensor') . ' ' . $connector . ' (' . $controller_name . '\\' . $sensor_name . ')';
                $properties = ['model' => $model];
                $instID = $this->FindOrCreateInstance('C2672DE6-E854-40C0-86E0-DE1B6B4C3CAB', $controller_id, $connector, $sensor_name, $info, $properties, $pos++);
            }
        }
    }
}
