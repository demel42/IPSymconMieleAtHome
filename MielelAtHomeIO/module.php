<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class MieleAtHomeIO extends IPSModule
{
    use MieleAtHomeCommon;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('userid', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('client_id', '');
        $this->RegisterPropertyString('client_secret', '');

        $this->RegisterPropertyString('vg_selector', '');
        $this->RegisterPropertyString('language', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $userid = $this->ReadPropertyString('userid');
        $password = $this->ReadPropertyString('password');
        $client_id = $this->ReadPropertyString('client_id');
        $client_secret = $this->ReadPropertyString('client_secret');

        if ($userid != '' && $password != '' && $client_id != '' && $client_secret != '') {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_INVALIDCONFIG);
        }
    }

    public function GetConfigurationForm()
    {
        $opts_vg_selector = [];
        $opts_vg_selector[] = ['label' => $this->Translate('England'), 'value' => 'en-GB'];
        $opts_vg_selector[] = ['label' => $this->Translate('Germany'), 'value' => 'de-DE'];
        $opts_vg_selector[] = ['label' => $this->Translate('Switzerland'), 'value' => 'de-CH'];
        $opts_vg_selector[] = ['label' => $this->Translate('Austria'), 'value' => 'de-AT'];
        $opts_vg_selector[] = ['label' => $this->Translate('Netherlands'), 'value' => 'nl-NL'];
        $opts_vg_selector[] = ['label' => $this->Translate('Belgium'), 'value' => 'nl-BE'];
        $opts_vg_selector[] = ['label' => $this->Translate('Luxembourg'), 'value' => 'de-LU'];

        $opts_language = [];
        $opts_language[] = ['label' => $this->Translate('England'), 'value' => 'en'];
        $opts_language[] = ['label' => $this->Translate('Germany'), 'value' => 'de'];

        $formElements = [];
        $formElements[] = ['type' => 'Label', 'label' => 'Miele@Home Account'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'userid', 'caption' => 'User-ID (email)'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
        $formElements[] = ['type' => 'Label', 'label' => 'Miele@Home API-Access'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'client_id', 'caption' => 'Client-ID'];
        $formElements[] = ['type' => 'ValidationTextBox', 'name' => 'client_secret', 'caption' => 'Client-Secret'];
        $formElements[] = ['type' => 'Label', 'label' => ''];
        $formElements[] = ['type' => 'Select', 'name' => 'language', 'caption' => 'Language', 'options' => $opts_language];
        $formElements[] = ['type' => 'Select', 'name' => 'vg_selector', 'caption' => 'VG-Selector', 'options' => $opts_vg_selector];

        $formActions = [];
        $formActions[] = ['type' => 'Button', 'caption' => 'Test access', 'onClick' => 'MieleAtHomeIO_TestAccess($id);'];
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

    public function TestAccess()
    {
        $txt = '';

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall('/v1/devices/', $cdata, $msg);

        if ($r == false) {
            $txt .= $this->translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
            if ($msg != '') {
                $txt .= $this->translate('message') . ': ' . $msg . PHP_EOL;
            }
        } else {
            $txt = $this->translate('valid account-data') . PHP_EOL;
        }

        echo $txt;
    }

    protected function SendData($buf)
    {
        $data = ['DataID' => '{D39AEB86-E611-4752-81C7-DBF7E41E79E1}', 'Buffer' => $buf];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function ForwardData($data)
    {
        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetDevices':
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/', $ret, $msg);
                    break;
                case 'GetDeviceIdent':
                    $ident = $jdata['Ident'];
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/' . $ident . '/ident/', $ret, $msg);
                    break;
                case 'GetDeviceStatus':
                    $ident = $jdata['Ident'];
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/' . $ident . '/state/', $ret, $msg);
                    break;
                case 'Action':
                    $ident = $jdata['Ident'];
                    $action = $jdata['Action'];
                    $msg = '';
                    $r = $this->do_ActionCall('/v1/devices/' . $ident . '/actions', $action, $ret, $msg);
                    $ret = json_encode(['Status' => $r, 'Message'=> $msg]);
                    break;
                default:
                    $this->SendDebug(__FUNCTION__, 'unknown function "' . $jdata['Function'] . '"', 0);
                    break;
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'unknown message-structure', 0);
        }

        $this->SendDebug(__FUNCTION__, 'ret=' . print_r($ret, true), 0);
        return $ret;
    }

    private function getToken(&$msg)
    {
        $userid = $this->ReadPropertyString('userid');
        $password = $this->ReadPropertyString('password');
        $client_id = $this->ReadPropertyString('client_id');
        $client_secret = $this->ReadPropertyString('client_secret');
        $vg_selector = $this->ReadPropertyString('vg_selector');

        $dtoken = $this->GetBuffer('Token');
        $jtoken = json_decode($dtoken, true);
        $token = isset($jtoken['token']) ? $jtoken['token'] : '';
        $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;

        if ($expiration < time()) {
            $params = [
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'password',
					'username'      => $userid,
					'password'      => $password,
                    'state'         => 'token',
                    'redirect_uri'  => '/v1/devices',
                    'vg'            => $vg_selector,
                ];
            $header = [
                    'Accept: application/json; charset=utf-8',
					'Content-Type: application/x-www-form-urlencoded'
                ];

            $cdata = '';
            $msg = '';
            $statuscode = $this->do_HttpRequest('/thirdparty/token', $params, $header, '', 'POST', $cdata, $msg);
            if ($statuscode == 0 && $cdata == '') {
                $statuscode = IS_INVALIDDATA;
            }
            $this->SendDebug(__FUNCTION__, 'token: statuscode=' . $statuscode . ', cdata=' . print_r($cdata, true) . ', msg=' . $msg, 0);
            if ($statuscode != 0) {
                $this->SetStatus($statuscode);
                return '';
            }

            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $token = $jdata['access_token'];
            $expires_in = $jdata['expires_in'];

            $jtoken = [
                    'token'            => $token,
                    'expiration'       => time() + $expires_in
                ];
            $this->SetBuffer('Token', json_encode($jtoken));
        }

        return $jtoken;
    }

    private function do_ApiCall($func, &$data, &$msg)
    {
        $language = $this->ReadPropertyString('language');

        $jtoken = $this->getToken($msg);
        if ($jtoken == '') {
            return false;
        }
        $token = $jtoken['token'];

        $params = [
                'language' => $language,
            ];

        $header = [
                'Accept: application/json; charset=utf-8',
                'Authorization: Bearer ' . $token,
            ];

        $msg = '';
        $statuscode = $this->do_HttpRequest($func, $params, $header, '', 'GET', $data, $msg);
        $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', data=' . print_r($data, true), 0);
        if ($statuscode != 0) {
            $this->SetStatus($statuscode);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return $statuscode ? false : true;
    }

    private function do_ActionCall($func, $opts, &$data, &$msg)
    {
        $language = $this->ReadPropertyString('language');

        $jtoken = $this->getToken($msg);
        if ($jtoken == '') {
            return false;
        }
        $token = $jtoken['token'];

        $params = [
                'language' => $language,
            ];

        $header = [
                'Accept: */*',
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
            ];

        $postdata = $opts != '' ? json_encode($opts) : '';

        $msg = '';
        $statuscode = $this->do_HttpRequest($func, $params, $header, $postdata, 'PUT', $data, $msg);
        $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', data=' . print_r($data, true), 0);
        if ($statuscode != 0) {
            $this->SetStatus($statuscode);
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return $statuscode ? false : true;
    }

    private function do_HttpRequest($func, $params, $header, $postdata, $mode, &$data, &$msg)
    {
        $url = 'https://api.mcs3.miele.com' . $func;

        if ($params != '') {
            $n = 0;
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode($value);
            }
        }

        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ': url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);
        if ($postdata != '') {
            if (is_array($postdata)) {
                $postdata = http_build_query($postdata);
            }
            $this->SendDebug(__FUNCTION__, '    postdata=' . $postdata, 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
        }
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $msg = '';
        $data = '';

        if ($cdata != '') {
            $jdata = json_decode($cdata, true);
            if (isset($jdata['message'])) {
                $msg = $jdata['message'];
            }
        }

        if ($httpcode == 200 || $httpcode == 204) {
            $data = $cdata;
        } elseif ($httpcode == 302) {
            $data = $redirect_url;
        } elseif ($httpcode == 401) {
            $statuscode = IS_UNAUTHORIZED;
            $err = 'got http-code ' . $httpcode . ' (unauthorized)';
        } elseif ($httpcode >= 500 && $httpcode <= 599) {
            $statuscode = IS_SERVERERROR;
            $err = 'got http-code ' . $httpcode . ' (server error)';
        } else {
            $statuscode = IS_HTTPERROR;
            $err = 'got http-code ' . $httpcode;
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', msg=' . $msg, 0);
        }

        return $statuscode;
    }
}
