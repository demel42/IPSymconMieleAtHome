<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class MieleAtHomeIO extends IPSModule
{
    use MieleAtHomeCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('api_key', '');

        $this->RegisterPropertyInteger('update_interval', '60');

        $this->RegisterTimer('UpdateData', 0, 'MieleAtHomeIO_UpdateData(' . $this->InstanceID . ');');
        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $api_key = $this->ReadPropertyString('api_key');

        if ($api_key != '') {
            $this->SetUpdateInterval();
            $this->SetStatus(StatusCode_active);
        } else {
            $this->SetStatus(StatusCode_inactive);
        }
    }

    protected function SetUpdateInterval()
    {
        $sec = $this->ReadPropertyInteger('update_interval');
        $msec = $sec > 0 ? $sec * 1000 : 0;
        $this->SetTimerInterval('UpdateData', $msec);
    }

    protected function SendData($buf)
    {
        $data = ['DataID' => '{D39AEB86-E611-4752-81C7-DBF7E41E79E1}', 'Buffer' => $buf];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function ForwardData($data)
    {
        $jdata = json_decode($data);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata->Function)) {
            switch ($jdata->Function) {
                case 'LastData':
                    $ret = $this->GetBuffer('LastData');
                    break;
                case 'CmdUrl':
                    $ret = $this->SendCommand($jdata->Url);
                    $this->SetTimerInterval('UpdateData', 500);
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

    public function UpdateData()
    {
    }

    public function SendCommand(string $cmd_url)
    {
    }

    private function do_HttpRequest($url)
    {
    }
}
