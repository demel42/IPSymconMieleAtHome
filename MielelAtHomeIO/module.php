<?php

require_once __DIR__ . '/../libs/common.php';  // globale Funktionen

class MieleAtHomeIO extends IPSModule
{
    use MieleAtHomeCommon;

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

        $this->RegisterAttributeString('RefreshToken', '');

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
            $refresh_token = $this->ReadAttributeString('RefreshToken');
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
        $access_token = jdata['access_token'];
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
            $refresh_token = $this->ReadAttributeString('RefreshToken');
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
                $this->WriteAttributeString('RefreshToken', $refresh_token);
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
            $this->WriteAttributeString('RefreshToken', '');
            return;
        }
        $refresh_token = $this->FetchRefreshToken($_GET['code']);
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('RefreshToken', $refresh_token);
    }

    /***********************************************************
     * Configuration Form
     ***********************************************************/

    /**
     * build configuration form.
     *
     * @return string
     */
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

    /**
     * return form configurations on configuration step.
     *
     * @return array
     */
    protected function GetFormElements()
    {
        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');

        $formElements = [];

        $formElements[] = [
                'type'    => 'CheckBox',
                'name'    => 'module_disable',
                'caption' => 'Instance is disabled'
            ];

        $formElements[] = [
                'type'  => 'Image',
                'image' => 'data:image/png;base64, iVBORw0KGgoAAAANSUhEUgAAAQsAAACMCAYAAABrl4yGAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAA3ZpVFh0WE1MOmNvbS5hZG9iZS54bXAAAAAAADw/eHBhY2tldCBiZWdpbj0i77u/IiBpZD0iVzVNME1wQ2VoaUh6cmVTek5UY3prYzlkIj8+IDx4OnhtcG1ldGEgeG1sbnM6eD0iYWRvYmU6bnM6bWV0YS8iIHg6eG1wdGs9IkFkb2JlIFhNUCBDb3JlIDUuNi1jMDY3IDc5LjE1Nzc0NywgMjAxNS8wMy8zMC0yMzo0MDo0MiAgICAgICAgIj4gPHJkZjpSREYgeG1sbnM6cmRmPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5LzAyLzIyLXJkZi1zeW50YXgtbnMjIj4gPHJkZjpEZXNjcmlwdGlvbiByZGY6YWJvdXQ9IiIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bWxuczp4bXA9Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC8iIHhtcE1NOk9yaWdpbmFsRG9jdW1lbnRJRD0ieG1wLmRpZDo4ZjVkYzkxZS01NjQ4LTdlNDQtOGQ4OC1iZjdlYjIyMmEwMGMiIHhtcE1NOkRvY3VtZW50SUQ9InhtcC5kaWQ6QjJBNEQ2NTRBMDFBMTFFOUFENjJBMEEzMkMyRjE4NjIiIHhtcE1NOkluc3RhbmNlSUQ9InhtcC5paWQ6QjJBNEQ2NTNBMDFBMTFFOUFENjJBMEEzMkMyRjE4NjIiIHhtcDpDcmVhdG9yVG9vbD0iQWRvYmUgUGhvdG9zaG9wIENDIDIwMTUgKFdpbmRvd3MpIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6OGY1ZGM5MWUtNTY0OC03ZTQ0LThkODgtYmY3ZWIyMjJhMDBjIiBzdFJlZjpkb2N1bWVudElEPSJ4bXAuZGlkOjhmNWRjOTFlLTU2NDgtN2U0NC04ZDg4LWJmN2ViMjIyYTAwYyIv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'
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

    /**
     * return form actions by token.
     *
     * @return array
     */
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

    // Information

    /** Returns all information about appliances linked to an user account.
     * @return array
     */
    public function GetDevices()
    {
        $ret = '';
        $msg = '';
        $state = $this->do_ApiCall('/v1/devices/', $ret, $msg);
        $devices = ['Status' => $state, 'Data' => $ret, 'Message'=> $msg];
        return $devices;
    }

    /** Returns all information about a single appliance linked to an user account
     * @param $device_id
     *
     * @return array
     */
    public function GetDevice($device_id)
    {
        $ret = '';
        $msg = '';
        $state = $this->do_ApiCall('/v1/devices/' . $device_id, $ret, $msg);
        $device = ['Status' => $state, 'Data' => $ret, 'Message'=> $msg];
        return $device;
    }

    /** Returns the ident information of a single device
     * @param $device_id
     *
     * @return array
     */
    public function GetDeviceIdent($device_id)
    {
        $ret = '';
        $msg = '';
        $state = $this->do_ApiCall('/v1/devices/' . $device_id . '/ident', $ret, $msg);
        $device_ident = ['Status' => $state, 'Data' => $ret, 'Message'=> $msg];
        return $device_ident;
    }

    /** Returns the state information of a single device
     * @param $device_id
     *
     * @return array
     */
    public function GetDeviceState($device_id)
    {
        $ret = '';
        $msg = '';
        $state = $this->do_ApiCall('/v1/devices/' . $device_id . '/state', $ret, $msg);
        $device_state = ['Status' => $state, 'Data' => $ret, 'Message'=> $msg];
        return $device_state;
    }

    public function TestAccess()
    {
        $txt = '';

        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, 'instance is inactive, skip', 0);
            echo $this->translate('Instance is inactive') . PHP_EOL;
            return;
        }

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
        $inst = IPS_GetInstance($this->InstanceID);
        if ($inst['InstanceStatus'] == IS_INACTIVE) {
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
                    'token'            => $token
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
