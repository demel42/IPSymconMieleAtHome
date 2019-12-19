<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/images.php';  // eingebettete Images

class MieleAtHomeConfig extends IPSModule
{
    use MieleAtHomeCommon;
    use MieleAtHomeImages;

    public function Create()
    {
        parent::Create();

        $this->ConnectParent('{996743FB-1712-47A3-9174-858A08A13523}');
        $this->RegisterPropertyInteger('ImportCategoryID', 0);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetStatus(IS_ACTIVE);
    }

    private function getConfiguratorValues()
    {
        $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDevices'];
        $data = $this->SendDataToParent(json_encode($SendData));
        $this->SendDebug(__FUNCTION__, 'data=' . $data, 0);

        $config_list = [];
        if ($data != '') {
            $guid = '{C2672DE6-E854-40C0-86E0-DE1B6B4C3CAB}'; // Miele@Home Device
            $instIDs = IPS_GetInstanceListByModuleID($guid);

            $devices = json_decode($data, true);
            $this->SendDebug(__FUNCTION__, 'devices=' . json_encode($devices), 0);
            foreach ($devices as $fabNumber => $device) {
                $this->SendDebug(__FUNCTION__, 'fabNumber=' . $fabNumber . ', device=' . json_encode($device), 0);

                $instanceID = 0;
                foreach ($instIDs as $instID) {
                    if ($fabNumber == IPS_GetProperty($instID, 'fabNumber')) {
                        $MieleatHome_device_name = IPS_GetName($instID);
                        $this->SendDebug(__FUNCTION__, 'device found: ' . utf8_decode($MieleatHome_device_name) . ' (' . $instID . ')', 0);
                        $instanceID = $instID;
                        break;
                    }
                }

                $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => 'GetDeviceIdent', 'Ident' => $fabNumber];
                $device_data = $this->SendDataToParent(json_encode($SendData));
                $this->SendDebug(__FUNCTION__, 'device_data=' . $device_data, 0);

                $device = json_decode($device_data, true);
                $deviceId = $device['type']['value_raw'];
                $deviceType = $device['type']['value_localized'];
                $techType = $device['deviceIdentLabel']['techType'];
                $deviceName = $device['deviceName'];
                if ($deviceName == '') {
                    $deviceName = $deviceType;
                }

                $create = [
                    'moduleID'      => $guid,
                    'location'      => $this->SetLocation(),
                    'configuration' => [
                        'deviceId'   => $deviceId,
                        'deviceType' => $deviceType,
                        'fabNumber'  => $fabNumber,
                        'techType'   => $techType
                    ]
                ];
                if (IPS_GetKernelVersion() >= 5.1) {
                    $create['info'] = $deviceType . ' (' . $techType . ')';
                }

                $entry = [
                    'instanceID'  => $instanceID,
                    'id'          => $deviceId,
                    'name'        => $deviceName,
                    'tech_type'   => $techType,
                    'device_type' => $deviceType,
                    'fabNumber'   => $fabNumber,
                    'create'      => $create
                ];

                $config_list[] = $entry;
            }
        }
        return $config_list;
    }

    private function SetLocation()
    {
        $tree_position = [];
        $category = $this->ReadPropertyInteger('ImportCategoryID');
        if ($category > 0 && IPS_ObjectExists($category)) {
            $tree_position[] = IPS_GetName($category);
            $parent = IPS_GetObject($category)['ParentID'];
            while ($parent > 0) {
                if ($parent > 0) {
                    $tree_position[] = IPS_GetName($parent);
                }
                $parent = IPS_GetObject($parent)['ParentID'];
            }
            $tree_position = array_reverse($tree_position);
        }
        return $tree_position;
    }

    public function GetConfigurationForm()
    {
        $formElements = $this->GetFormElements();
        $formActions = $this->GetFormActions();
        $formStatus = $this->GetFormStatus();

        $form = json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
        if ($form == '') {
            $this->SendDebug(__FUNCTION__, 'json_error=' . json_last_error_msg(), 0);
            $this->SendDebug(__FUNCTION__, '=> formElements=' . print_r($formElements, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formActions=' . print_r($formActions, true), 0);
            $this->SendDebug(__FUNCTION__, '=> formStatus=' . print_r($formStatus, true), 0);
        }
        return $form;
    }

    protected function GetFormElements()
    {
        $formElements = [];

        $values = $this->getConfiguratorValues();

        $formElements[] = [
            'type'  => 'Image',
            'image' => 'data:image/png;base64,' . $this->GetBrandImage()
        ];

        $formElements[] = [
            'type'    => 'Label',
            'caption' => 'category for Miele@Home devices to be created:'
        ];
        $formElements[] = [
            'name'    => 'ImportCategoryID',
            'type'    => 'SelectCategory',
            'caption' => 'category'
        ];

        $formElements[] = [
            'name'     => 'MieleatHomeConfiguration',
            'type'     => 'Configurator',
            'rowCount' => count($values),
            'add'      => false,
            'delete'   => false,
            'sort'     => [
                'column'    => 'name',
                'direction' => 'ascending'
            ],

            'columns' => [
                [
                    'caption' => 'ID',
                    'name'    => 'id',
                    'width'   => '200px',
                    'visible' => false
                ],
                [
                    'caption' => 'device name',
                    'name'    => 'name',
                    'width'   => 'auto'
                ],
                [
                    'caption' => 'Model',
                    'name'    => 'tech_type',
                    'width'   => '250px'
                ],
                [
                    'caption' => 'Label',
                    'name'    => 'device_type',
                    'width'   => '300px'
                ],
                [
                    'caption' => 'Fabrication number',
                    'name'    => 'fabNumber',
                    'width'   => '200px'
                ]
            ],
            'values' => $values
        ];

        return $formElements;
    }

    protected function GetFormActions()
    {
        $formActions = [];

        if (IPS_GetKernelVersion() < 5.2) {
            $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Module description',
                'onClick' => 'echo "https://github.com/demel42/IPSymconMieleAtHome/blob/master/README.md";'
            ];
        }

        return $formActions;
    }
}
