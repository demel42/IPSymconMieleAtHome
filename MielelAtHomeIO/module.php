<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen
require_once __DIR__ . '/../libs/images.php';  // eingebettete Images

class MieleAtHomeIO extends IPSModule
{
    use MieleAtHomeCommon;
    use MieleAtHomeImages;

    //This one needs to be available on our OAuth client backend.
    //Please contact us to register for an identifier: https://www.symcon.de/kontakt/#OAuth
    private $oauthIdentifer = 'miele_at_home';

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('userid', '');
        $this->RegisterPropertyString('password', '');
        $this->RegisterPropertyString('client_id', '');
        $this->RegisterPropertyString('client_secret', '');

        $this->RegisterPropertyString('vg_selector', '');
        $this->RegisterPropertyString('language', '');

        $this->RegisterPropertyInteger('OAuth_Type', CONNECTION_UNDEFINED);

        if (IPS_GetKernelVersion() >= 5.1) {
            $this->RegisterAttributeString('RefreshToken', '');
        } else {
            $this->RegisterPropertyString('RefreshToken', '');
        }

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
            if ($oauth_type == CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == CONNECTION_DEVELOPER) {
            $userid = $this->ReadPropertyString('userid');
            $password = $this->ReadPropertyString('password');
            $client_id = $this->ReadPropertyString('client_id');
            $client_secret = $this->ReadPropertyString('client_secret');
            if ($userid != '' && $password != '' && $client_id != '' && $client_secret != '') {
                $this->SetStatus(IS_ACTIVE);
            } else {
                $this->SetStatus(IS_INVALIDCONFIG);
            }
        } else {
			if ($this->GetConnectUrl() == false) {
                $this->SetStatus(IS_NOSYMCONCONNECT);
                return;
            }

            if (IPS_GetKernelVersion() >= 5.1) {
                $refresh_token = $this->ReadAttributeString('RefreshToken');
            } else {
                $refresh_token = $this->ReadPropertyString('RefreshToken');
            }
            if ($refresh_token != '') {
                $this->SetStatus(IS_ACTIVE);
            } else {
                $this->SetStatus(IS_INVALIDCONFIG);
            }
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
        }
    }

    private function RegisterOAuth($WebOAuth)
    {
        $ids = IPS_GetInstanceListByModuleID('{F99BF07D-CECA-438B-A497-E4B55F139D37}');
        if (count($ids) > 0) {
            $clientIDs = json_decode(IPS_GetProperty($ids[0], 'ClientIDs'), true);
            $found = false;
            foreach ($clientIDs as $index => $clientID) {
                if ($clientID['ClientID'] == $WebOAuth) {
                    if ($clientID['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $clientIDs[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $clientIDs[] = ['ClientID' => $WebOAuth, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'ClientIDs', json_encode($clientIDs));
            IPS_ApplyChanges($ids[0]);
        }
    }

    public function Register()
    {
        $url = 'https://oauth.ipmagic.de/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        return $url;
    }

    protected function Call4AccessToken($content)
    {
        $url = 'https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer;
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    content=' . print_r($content, true), 0);

        $statuscode = 0;
        $err = '';
        $jdata = false;

        $time_start = microtime(true);
        $options = [
            'http' => [
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($content)
                ]
            ];
        $context = stream_context_create($options);
        $cdata = @file_get_contents($url, false, $context);
        $duration = round(microtime(true) - $time_start, 2);
        if (preg_match('/HTTP\/[0-9\.]+\s+([0-9]*)/', $http_response_header[0], $r)) {
            $httpcode = $r[1];
        } else {
            $this->SendDebug(__FUNCTION__, 'http_response_header=' . print_r($http_response_header, true), 0);
            $httpcode = 0;
        }
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        if ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode == 409) {
                $data = $cdata;
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = IS_NODATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                if (!isset($jdata['token_type']) || $jdata['token_type'] != 'Bearer') {
                    $statuscode = IS_INVALIDDATA;
                    $err = 'malformed response';
                }
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }
        return $jdata;
    }

    private function FetchRefreshToken($code)
    {
        $this->SendDebug(__FUNCTION__, 'code=' . $code, 0);
        $jdata = $this->Call4AccessToken(['code' => $code]);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'got no token', 0);
            $this->SetBuffer('AccessToken', '');
            return false;
        }
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        $access_token = $jdata['access_token'];
        $expiration = time() + $jdata['expires_in'];
        $refresh_token = $jdata['refresh_token'];
        $this->FetchAccessToken($access_token, $expiration);
        return $refresh_token;
    }

    private function FetchAccessToken($access_token = '', $expiration = 0)
    {
        if ($access_token == '' && $expiration == 0) {
            $data = $this->GetBuffer('AccessToken');
            if ($data != '') {
                $jdata = json_decode($data, true);
                if (time() < $jdata['expiration']) {
                    $this->SendDebug(__FUNCTION__, 'access_token=' . $jdata['access_token'] . ', valid until ' . date('d.m.y H:i:s', $jdata['expiration']), 0);
                    return $jdata['access_token'];
                } else {
                    $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'access_token not saved', 0);
            }
            if (IPS_GetKernelVersion() >= 5.1) {
                $refresh_token = $this->ReadAttributeString('RefreshToken');
            } else {
                $refresh_token = $this->ReadPropertyString('RefreshToken');
            }
            $jdata = $this->Call4AccessToken(['refresh_token' => $refresh_token]);
            if ($jdata == false) {
                $this->SendDebug(__FUNCTION__, 'got no access_token', 0);
                $this->SetBuffer('AccessToken', '');
                return false;
            }
            $access_token = $jdata['access_token'];
            $expiration = time() + $jdata['expires_in'];
            if (isset($jdata['refresh_token'])) {
                $refresh_token = $jdata['refresh_token'];
                $this->SendDebug(__FUNCTION__, 'new refresh_token=' . $refresh_token, 0);
                if (IPS_GetKernelVersion() >= 5.1) {
                    $this->WriteAttributeString('RefreshToken', $refresh_token);
                } else {
                    IPS_SetProperty($this->InstanceID, 'RefreshToken', $refresh_token);
                    IPS_ApplyChanges($this->InstanceID);
                }
            }
        }
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $this->SetBuffer('AccessToken', json_encode(['access_token' => $access_token, 'expiration' => $expiration]));
        return $access_token;
    }

    protected function ProcessOAuthData()
    {
        if (!isset($_GET['code'])) {
            $this->SendDebug(__FUNCTION__, 'code missing, _GET=' . print_r($_GET, true), 0);
            $this->SetStatus(IS_INVALIDCONFIG);
            if (IPS_GetKernelVersion() >= 5.1) {
                $this->WriteAttributeString('RefreshToken', '');
            } else {
                IPS_SetProperty($this->InstanceID, 'RefreshToken', '');
                IPS_ApplyChanges($this->InstanceID);
            }
            return;
        }
        $refresh_token = $this->FetchRefreshToken($_GET['code']);
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);
        if (IPS_GetKernelVersion() >= 5.1) {
            $this->WriteAttributeString('RefreshToken', $refresh_token);
        } else {
            IPS_SetProperty($this->InstanceID, 'RefreshToken', $refresh_token);
            IPS_ApplyChanges($this->InstanceID);
        }
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
        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');

        $formElements = [];

        $formElements[] = [
                'type'    => 'CheckBox',
                'name'    => 'module_disable',
                'caption' => 'Instance is disabled'
            ];

        if ($oauth_type == CONNECTION_OAUTH) {
            $instID = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0];
            if (IPS_GetInstance($instID)['InstanceStatus'] != IS_ACTIVE) {
                $msg = 'Error: Symcon Connect is not active!';
            } else {
                $msg = 'Status: Symcon Connect is OK!';
            }
            $formElements[] = [
                    'type'    => 'Label',
                    'caption' => $msg
                ];
        }

        $formElements[] = [
                'type'  => 'Image',
                'image' => 'data:image/png;base64,' . $this->GetBrandImage()
            ];

        $formElements[] = [
                'type'    => 'Label',
                'caption' => 'Please select a connection type. Either connect via the Miele@Home username and password and IP Symcon Connect or alternatively as a developer with your own developer key.'
            ];
        $formElements[] = [
                'type'    => 'Select',
                'name'    => 'OAuth_Type',
                'caption' => 'Connection Type',
                'options' => [
                    [
                        'caption' => 'Please select a connection type',
                        'value'   => CONNECTION_UNDEFINED
                    ],
                    [
                        'caption' => 'Miele@Home via IP-Symcon Connect',
                        'value'   => CONNECTION_OAUTH
                    ],
                    [
                        'caption' => 'Miele@Home Developer Key',
                        'value'   => CONNECTION_DEVELOPER
                    ]
                ]
            ];

        if ($oauth_type == CONNECTION_OAUTH) {
            $formElements[] = [
                    'type'    => 'Label',
                    'caption' => 'Push "Register" in the action part of this configuration form.'
                ];
            $formElements[] = [
                    'type'    => 'Label',
                    'caption' => 'At the webpage from Miele log in with your Miele@Home username and your Miele@Home password.'
                ];
            $formElements[] = [
                    'type'    => 'Label',
                    'caption' => 'If the connection to IP-Symcon was successfull you get the message: "Miele@Home successfully connected!". Close the browser window.'
                ];
            $formElements[] = [
                    'type'    => 'Label',
                    'caption' => 'Return to this configuration form.'
                ];
        }

        if ($oauth_type == CONNECTION_DEVELOPER) {
            $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Miele@Home Account',
                    'items'   => [
                        [
                            'name'    => 'userid',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'User-ID (email)'
                        ],
                        [
                            'name'    => 'password',
                            'type'    => 'PasswordTextBox',
                            'caption' => 'Password'
                        ]
                    ]
                ];
            $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Miele@Home API-Access',
                    'items'   => [
                        [
                            'name'    => 'client_id',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'Client-ID'
                        ],
                        [
                            'name'    => 'client_secret',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'Client-Secret'
                        ]
                    ]
                ];

            $opts_language = [];
            $opts_language[] = ['caption' => $this->Translate('England'), 'value'   => 'en'];
            $opts_language[] = ['caption' => $this->Translate('Germany'), 'value'   => 'de'];

            $opts_vg_selector = [];
            $opts_vg_selector[] = ['label' => $this->Translate('England'), 'value' => 'en-GB'];
            $opts_vg_selector[] = ['label' => $this->Translate('Germany'), 'value' => 'de-DE'];
            $opts_vg_selector[] = ['label' => $this->Translate('Switzerland'), 'value' => 'de-CH'];
            $opts_vg_selector[] = ['label' => $this->Translate('Austria'), 'value' => 'de-AT'];
            $opts_vg_selector[] = ['label' => $this->Translate('Netherlands'), 'value' => 'nl-NL'];
            $opts_vg_selector[] = ['label' => $this->Translate('Belgium'), 'value' => 'nl-BE'];
            $opts_vg_selector[] = ['label' => $this->Translate('Luxembourg'), 'value' => 'de-LU'];

            $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'caption' => 'Miele@Home Language Settings',
                    'items'   => [
                        [
                            'type'    => 'Select',
                            'name'    => 'language',
                            'caption' => 'Language',
                            'options' => $opts_language
                        ],
                        [
                            'type'    => 'Select',
                            'name'    => 'vg_selector',
                            'caption' => 'VG-Selector',
                            'options' => $opts_vg_selector
                        ]
                    ]
                ];
        }

        return $formElements;
    }

    protected function GetFormActions()
    {
        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');

        $formActions = [];

        if ($oauth_type == CONNECTION_OAUTH) {
            $formActions[] = [
                        'type'    => 'Label',
                        'caption' => 'Register with your Miele@Home username and Miele@Home password:'
                    ];
            $formActions[] = [
                        'type'    => 'Button',
                        'caption' => 'Register',
                        'onClick' => 'echo MieleAtHome_Register($id);'
                    ];
        }

        $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Test access',
                'onClick' => 'MieleAtHome_TestAccess($id);'
            ];
        $formActions[] = [
                'type'  => 'Label',
                'label' => '____________________________________________________________________________________________________'
            ];
        $formActions[] = [
                    'type'    => 'Button',
                    'caption' => 'Module description',
                    'onClick' => 'echo "https://github.com/demel42/IPSymconMieleAtHome/blob/master/README.md";'
                ];

        return $formActions;
    }

    public function TestAccess()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is inactive') . PHP_EOL;
            return;
        }

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall('/v1/devices/', $cdata, $msg);

        $txt = '';
        if ($r == false) {
            $txt .= $this->translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
            if ($msg != '') {
                $txt .= $this->translate('message') . ': ' . $msg . PHP_EOL;
            }
        } else {
            $txt = $this->translate('valid account-data') . PHP_EOL;
            $devices = json_decode($cdata, true);
            $n_devices = count($devices);
            $txt .= $n_devices . ' ' . $this->Translate('registered devices found');
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
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            return false;
        }

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

    /** Get Token
     * @param $msg
     *
     * @return array|mixed|string
     */
    private function getToken(&$msg)
    {
        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');

        if ($oauth_type == CONNECTION_OAUTH) {
            $token = $this->FetchAccessToken();
            $jtoken = [
                    'token' => $token
                ];
        }

        if ($oauth_type == CONNECTION_DEVELOPER) {
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
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
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

        if ($cerrno) {
            $statuscode = IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode == 200 || $httpcode == 204) {
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
