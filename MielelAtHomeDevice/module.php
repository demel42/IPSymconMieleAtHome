<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class MieleAtHomeDevice extends IPSModule
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

        $vpos = 1;

        // $info = 'Controller (#' . $controller_id . ')';
        // $this->SetSummary($info);

        $this->SetStatus(StatusCode_active);
    }

    public function GetConfigurationForm()
    {
        $formElements = [];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'label' => 'Update data', 'onClick' => 'MieleAtHomeDevice_UpdateData($id);'];
        $formActions[] = ['type' => 'Label', 'label' => '____________________________________________________________________________________________________'];
        $formActions[] = [
                            'type'    => 'Button',
                            'caption' => 'Module description',
                            'onClick' => 'echo "https://github.com/demel42/IPSymconMieleAtHome/blob/master/README.md";'
                        ];

        $formStatus = [];
        $formStatus[] = ['code' => StatusCode_creating, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => StatusCode_active, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => StatusCode_inactive, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];

        $formStatus[] = ['code' => StatusCode_Unauthorized, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => StatusCode_ServerError, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => StatusCode_HttpError, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => StatusCode_InvalidData, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return json_encode(['elements' => $formElements, 'actions' => $formActions, 'status' => $formStatus]);
    }

    public function ReceiveData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        if (isset($jdata->Buffer)) {
            $this->DecodeData($jdata->Buffer);
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }
    }

    protected function DecodeData($buf)
    {
        $this->SetStatus(StatusCode_active);
    }

    public function ForwardData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata->Function)) {
            switch ($jdata->Function) {
                case 'CmdUrl':
                    $SendData = ['DataID' => '{AE164AF6-A49F-41BD-94F3-B4829AAA0B55}', 'Function' => $jdata->Function, 'Url' => $jdata->Url];
                    $ret = $this->SendDataToParent(json_encode($SendData));
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata->Function . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }
}
