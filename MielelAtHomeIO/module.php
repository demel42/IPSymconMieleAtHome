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
		$opts_language = [];
        $opts_language[] = ['label' => $this->Translate('english'), 'value' => 'en'];
		$opts_language[] = ['label' => $this->Translate('german'), 'value' => 'de'];

		$formElements = [];
		$formElements[] = ['type' => 'Label', 'label' => 'Miele@Home Account'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'userid', 'caption' => 'User-ID (email)'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'password', 'caption' => 'Password'];
		$formElements[] = ['type' => 'Label', 'label' => 'Miele@Home API-Access'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'client_id', 'caption' => 'Client-ID'];
		$formElements[] = ['type' => 'ValidationTextBox', 'name' => 'client_secret', 'caption' => 'Client-Secret'];
		$formElements[] = ['type' => 'Label', 'label' => ''];
		$formElements[] = ['type' => 'Select', 'name' => 'language', 'caption' => 'Language', 'options' => $opts_language];

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
		$r = $this->do_ApiCall('/v1/devices');
		echo print_r($r, true);

		$msg = $this->translate('valid account-data') . PHP_EOL;
		echo $msg;
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
                case 'CallUrl':
                    $ret = $this->SendCommand($jdata->Url);
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

    public function SendCommand(string $cmd_url)
    {
    }

    private function getToken()
    {
        $userid = $this->ReadPropertyString('userid');
        $password = $this->ReadPropertyString('password');
        $client_id = $this->ReadPropertyString('client_id');
        $client_secret = $this->ReadPropertyString('client_secret');

        $dtoken = $this->GetBuffer('Token');
        $jtoken = json_decode($dtoken, true);
        $code = isset($jtoken['code']) ? $jtoken['code'] : '';
        $token = isset($jtoken['token']) ? $jtoken['token'] : '';
        $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;

        if ($code == '') {
            $header = [
                    'Accept: application/json; charset=utf-8',
                    'Content-Type: application/x-www-form-urlencoded'
                ];
            $postdata = [
                    'email'         => $userid,
					'password'      => $password,
					'client_id'     => $client_id,
					'state'         => 'login',
					'response_type' => 'code',
					'redirect_uri'  => '/v1/devices',
                ];

            $statuscode = $this->do_HttpRequest('/auth', '', $header, $postdata, 'POST', $cdata);
			if ($statuscode == 0 && $cdata == '') {
				$statuscode = IS_INVALIDDATA;
			}
            $this->SendDebug(__FUNCTION__, 'statuscode=' .  $statuscode . ', cdata=' . print_r($cdata, true), 0);
            if ($statuscode != 0) {
				$this->SetStatus($statuscode);
                return false;
            }
            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
            $code = $jdata['code'];
			$expiration = 0;
		}

        if ($expiration < time()) {
			$params = [
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'code'          => $code,
					'grant_type'    => 'authorization_code',
					'state'         => 'token',
					'redirect_uri'  => '/v1/devices',
				];
            $header = [
                    'Accept: application/json; charset=utf-8',
                ];

            $statuscode = $this->do_HttpRequest('/token', $params, $header, '', 'GET', $cdata);
			if ($statuscode == 0 && $cdata == '') {
				$statuscode = IS_INVALIDDATA;
			}
            $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', cdata=' . print_r($cdata, true), 0);
            if ($statuscode != 0) {
				$this->SetStatus($statuscode);
                return false;
            }

            $jdata = json_decode($cdata, true);
            $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

            $token = $jtoken['access_token'];
            $expires_in = $jtoken['expires_in'];

            $jtoken = [
                    'code'             => $code,
                    'token'            => $token,
                    'expiration'       => time() + $expires_in
                ];
            $this->SetBuffer('Token', json_encode($jtoken));
        }

        return $jtoken;
    }

    private function do_ApiCall($func)
    {
        $language = $this->ReadPropertyString('language');

        $jtoken = $this->getToken();
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

		$statuscode = $this->do_HttpRequest($func, $params, $header, '', 'GET', $cdata);
		$this->SendDebug(__FUNCTION__, 'statuscode=', $statuscode . ', cdata=' . print_r($cdata, true), 0);
		if ($statuscode != 0) {
			$this->SetStatus($statuscode);
			return false;
		}

        $cdata = $this->do_HttpRequest($func, $header, $postdata);
        $this->SendDebug(__FUNCTION__, 'cdata=' . print_r($cdata, true), 0);

        $this->SetStatus(IS_ACTIVE);
        return $cdata;
    }

    private function do_HttpRequest($func, $params, $header, $postdata, $mode, &$data)
    {
		$url = 'https://api.mcs3.miele.com/thirdparty' . $func; 

		if ($params != '') {
			$n = 0;
			foreach ($params as $param => $value) {
				$url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode($value);
			}
		}

        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ': url=' . $url, 0);
		$this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);
        if ($postdata != '') {
            $this->SendDebug(__FUNCTION__, '    postdata=' . json_encode($postdata), 0);
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
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
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
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

        $statuscode = 0;
        $err = '';
        $data = '';

		// 200 = ok
		// 400 = bad request 
		// 401 = unauthorized
		// 404 = not found
		// 405 = method not allowed
		// 500 = internal server error
		// 503 = unavailable

		if ($httpcode == 200) {
			$data = $cdata;
		} elseif ($httpcode == 401) {
			$statuscode = IS_UNAUTHORIZED;
			$err = "got http-code $httpcode (unauthorized)";
		} elseif ($httpcode >= 500 && $httpcode <= 599) {
			$statuscode = IS_SERVERERROR;
			$err = "got http-code $httpcode (server error)";
		} else {
			$statuscode = IS_HTTPERROR;
			$err = "got http-code $httpcode";
		}

        if ($statuscode) {
            echo "url=$url => statuscode=$statuscode, err=$err\n";
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
        }

        return $statuscode;
    }
}
