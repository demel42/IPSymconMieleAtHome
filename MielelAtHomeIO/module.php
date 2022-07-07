<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';
require_once __DIR__ . '/../libs/images.php';

class MieleAtHomeIO extends IPSModule
{
    use MieleAtHome\StubsCommonLib;
    use MieleAtHomeLocalLib;
    use MieleAtHomeImagesLib;

    private $oauthIdentifer = 'miele_at_home';

    private $ModuleDir;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->ModuleDir = __DIR__;
    }

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

        $this->RegisterPropertyInteger('OAuth_Type', self::$CONNECTION_UNDEFINED);

        $this->RegisterAttributeString('RefreshToken', '');

        $this->RegisterAttributeString('UpdateInfo', '');

        $this->InstallVarProfiles(false);

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($tstamp, $senderID, $message, $data)
    {
        parent::MessageSink($tstamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
            if ($oauth_type == self::$CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == self::$CONNECTION_DEVELOPER) {
            $userid = $this->ReadPropertyString('userid');
            if ($userid == '') {
                $this->SendDebug(__FUNCTION__, '"userid" is needed', 0);
                $r[] = $this->Translate('Username must be specified');
            }
            $password = $this->ReadPropertyString('password');
            if ($password == '') {
                $this->SendDebug(__FUNCTION__, '"password" is needed', 0);
                $r[] = $this->Translate('Password must be specified');
            }
            $client_id = $this->ReadPropertyString('client_id');
            if ($client_id == '') {
                $this->SendDebug(__FUNCTION__, '"client_id" is needed', 0);
                $r[] = $this->Translate('Client-ID must be specified');
            }
            $client_secret = $this->ReadPropertyString('client_secret');
            if ($client_secret == '') {
                $this->SendDebug(__FUNCTION__, '"client_secret" is needed', 0);
                $r[] = $this->Translate('Client-Secret must be specified');
            }
        }

        return $r;
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        switch ($oauth_type) {
            case self::$CONNECTION_DEVELOPER:
                $this->MaintainStatus(IS_ACTIVE);
                break;
            case self::$CONNECTION_OAUTH:
                if ($this->GetConnectUrl() == false) {
                    $this->MaintainStatus(self::$IS_NOSYMCONCONNECT);
                    return;
                }
                $refresh_token = $this->ReadAttributeString('RefreshToken');
                if ($refresh_token == '') {
                    $this->MaintainStatus(self::$IS_NOLOGIN);
                } else {
                    $this->MaintainStatus(IS_ACTIVE);
                }
                break;
            default:
                break;
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            if ($oauth_type == self::$CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
        }
    }

    private function Login()
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
        $httpcode = 0;
        if ($cdata == false) {
            $this->LogMessage('file_get_contents() failed: url=' . $url . ', context=' . print_r($context, true), KL_WARNING);
            $this->SendDebug(__FUNCTION__, 'file_get_contents() failed: url=' . $url . ', context=' . print_r($context, true), 0);
        } elseif (isset($http_response_header[0]) && preg_match('/HTTP\/[0-9\.]+\s+([0-9]*)/', $http_response_header[0], $r)) {
            $httpcode = $r[1];
        } else {
            $this->LogMessage('missing http_response_header, cdata=' . $cdata, KL_WARNING);
            $this->SendDebug(__FUNCTION__, 'missing http_response_header, cdata=' . $cdata, 0);
        }
        $this->SendDebug(__FUNCTION__, ' => httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        if ($httpcode && $httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode == 409) {
                $data = $cdata;
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_NODATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                if (!isset($jdata['token_type']) || $jdata['token_type'] != 'Bearer') {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                }
            }
        }
        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
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
                $jtoken = json_decode($data, true);
                $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
                $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
                $type = isset($jtoken['type']) ? $jtoken['type'] : self::$CONNECTION_UNDEFINED;
                if ($type != self::$CONNECTION_OAUTH) {
                    $this->WriteAttributeString('RefreshToken', '');
                    $this->SendDebug(__FUNCTION__, 'connection-type changed', 0);
                    $access_token = '';
                } elseif ($expiration < time()) {
                    $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
                    $access_token = '';
                }
                if ($access_token != '') {
                    $this->SendDebug(__FUNCTION__, 'access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
                    return $access_token;
                }
            } else {
                $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
            }
            $refresh_token = $this->ReadAttributeString('RefreshToken');
            $this->SendDebug(__FUNCTION__, 'refresh_token=' . print_r($refresh_token, true), 0);
            if ($refresh_token == '') {
                $this->SendDebug(__FUNCTION__, 'has no refresh_token', 0);
                $this->WriteAttributeString('RefreshToken', '');
                $this->SetBuffer('AccessToken', '');
                $this->MaintainStatus(self::$IS_NOLOGIN);
                return false;
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
                $this->WriteAttributeString('RefreshToken', $refresh_token);
            }
        }
        $this->SendDebug(__FUNCTION__, 'new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        $jtoken = [
            'access_token' => $access_token,
            'expiration'   => $expiration,
            'type'         => self::$CONNECTION_OAUTH
        ];
        $this->SetBuffer('AccessToken', json_encode($jtoken));
        return $access_token;
    }

    protected function ProcessOAuthData()
    {
        if (!isset($_GET['code'])) {
            $this->SendDebug(__FUNCTION__, 'code missing, _GET=' . print_r($_GET, true), 0);
            $this->WriteAttributeString('RefreshToken', '');
            $this->SetBuffer('AccessToken', '');
            $this->MaintainStatus(self::$IS_NOLOGIN);
            return;
        }
        $refresh_token = $this->FetchRefreshToken($_GET['code']);
        $this->SendDebug(__FUNCTION__, 'refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('RefreshToken', $refresh_token);
        if ($this->GetStatus() == self::$IS_NOLOGIN) {
            $this->MaintainStatus(IS_ACTIVE);
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Miele@Home I/O');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == self::$CONNECTION_OAUTH) {
            $formElements[] = [
                'type'    => 'Label',
                'caption' => $this->GetConnectStatusText(),
            ];
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance'
        ];

        $formElements[] = [
            'type'    => 'Select',
            'options' => [
                [
                    'caption' => 'Please select a connection type',
                    'value'   => self::$CONNECTION_UNDEFINED
                ],
                [
                    'caption' => 'Miele@Home via IP-Symcon Connect',
                    'value'   => self::$CONNECTION_OAUTH
                ],
                [
                    'caption' => 'Miele@Home Developer Key',
                    'value'   => self::$CONNECTION_DEVELOPER
                ]
            ],
            'name'    => 'OAuth_Type',
            'caption' => 'Connection Type',
        ];

        switch ($oauth_type) {
            case self::$CONNECTION_OAUTH:
                $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Push "Login" in the action part of this configuration form.'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'At the webpage from Miele log in with your Miele@Home username and your Miele@Home password.'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'If the connection to IP-Symcon was successfull you get the message: "Miele@Home successfully connected!". Close the browser window.'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Return to this configuration form.'
                        ],
                    ],
                    'caption' => 'Miele@Home Login'
                ];
                break;
            case self::$CONNECTION_DEVELOPER:
                $formElements[] = [
                    'type'    => 'ExpansionPanel',
                    'items'   => [
                        [
                            'type'    => 'Label',
                            'caption' => 'Miele@Home Account via Miele@mobile-App or from https://www.miele.de'
                        ],
                        [
                            'name'    => 'userid',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'User-ID (email)'
                        ],
                        [
                            'name'    => 'password',
                            'type'    => 'PasswordTextBox',
                            'caption' => 'Password'
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Miele@Home Language Settings'
                        ],
                        [
                            'type'    => 'Select',
                            'options' => [
                                [
                                    'caption' => $this->Translate('England'),
                                    'value'   => 'en'
                                ],
                                [
                                    'caption' => $this->Translate('Germany'),
                                    'value'   => 'de'
                                ],
                            ],
                            'name'    => 'language',
                            'caption' => 'Language',
                        ],

                        [
                            'type'    => 'Select',
                            'options' => [
                                [
                                    'caption' => $this->Translate('England'),
                                    'value'   => 'en-GB'
                                ],
                                [
                                    'caption' => $this->Translate('Germany'),
                                    'value'   => 'de-DE'
                                ],
                                [
                                    'caption' => $this->Translate('Switzerland'),
                                    'value'   => 'de-CH'
                                ],
                                [
                                    'caption' => $this->Translate('Austria'),
                                    'value'   => 'de-AT'
                                ],
                                [
                                    'caption' => $this->Translate('Netherlands'),
                                    'value'   => 'nl-NL'
                                ],
                                [
                                    'caption' => $this->Translate('Belgium'),
                                    'value'   => 'nl-BE'
                                ],
                                [
                                    'caption' => $this->Translate('Luxembourg'),
                                    'value'   => 'de-LU'
                                ],
                            ],
                            'name'    => 'vg_selector',
                            'caption' => 'VG-Selector',
                        ],
                        [
                            'type'    => 'Label',
                            'caption' => 'Miele@Home API-Access from https://www.miele.com/developer'
                        ],
                        [
                            'name'    => 'client_id',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'Client-ID'
                        ],
                        [
                            'name'    => 'client_secret',
                            'type'    => 'ValidationTextBox',
                            'caption' => 'Client-Secret'
                        ],
                    ],
                    'caption' => 'Miele@Home Access-Details',
                ];
                break;
        default:
                break;
        }

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == self::$CONNECTION_OAUTH) {
            $formActions[] = [
                'type'    => 'Label',
                'caption' => 'Login with your Miele@Home username and Miele@Home password:'
            ];
            $formActions[] = [
                'type'    => 'Button',
                'caption' => 'Login at Miele@Home',
                'onClick' => 'echo "' . $this->Login() . '";',
            ];
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Test access',
            'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "TestAccess", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
                [
                    'type'    => 'Button',
                    'caption' => 'Clear Token',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }
        switch ($ident) {
            case 'ClearToken':
                $this->ClearToken();
                break;
            case 'TestAccess':
                $this->TestAccess();
                break;
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
    }

    private function TestAccess()
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            $msg = $this->GetStatusText();
            $this->PopupMessage($msg);
            return;
        }

        $cdata = '';
        $msg = '';
        $r = $this->do_ApiCall('/v1/devices', $cdata, $msg);

        $txt = '';
        if ($r == false) {
            $txt .= $this->Translate('invalid account-data') . PHP_EOL;
            $txt .= PHP_EOL;
            if ($msg != '') {
                $txt .= $this->Translate('message') . ': ' . $msg . PHP_EOL;
            }
        } else {
            $txt = $this->Translate('valid account-data') . PHP_EOL;
            $devices = json_decode($cdata, true);
            $n_devices = count($devices);
            $txt .= $n_devices . ' ' . $this->Translate('registered devices found');
        }
        $this->PopupMessage($txt);
    }

    private function ClearToken()
    {
        $refresh_token = $this->ReadAttributeString('RefreshToken');
        $this->SendDebug(__FUNCTION__, 'clear refresh_token=' . $refresh_token, 0);
        $this->WriteAttributeString('RefreshToken', '');

        $jtoken = json_decode($this->GetBuffer('AccessToken'), true);
        $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
        $this->SendDebug(__FUNCTION__, 'clear access_token=' . $access_token, 0);
        $this->SetBuffer('AccessToken', '');

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == self::$CONNECTION_OAUTH) {
            $this->MaintainStatus(self::$IS_NOLOGIN);
        }
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
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $ret = '';

        if (isset($jdata['Function'])) {
            switch ($jdata['Function']) {
                case 'GetDevices':
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices', $ret, $msg);
                    break;
                case 'GetDeviceIdent':
                    $ident = $jdata['Ident'];
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/' . $ident . '/ident', $ret, $msg);
                    break;
                case 'GetDeviceStatus':
                    $ident = $jdata['Ident'];
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/' . $ident . '/state', $ret, $msg);
                    break;
                case 'GetDeviceActions':
                    $ident = $jdata['Ident'];
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/' . $ident . '/actions', $ret, $msg);
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

    private function GetAccessToken(&$msg)
    {
        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        switch ($oauth_type) {
            case self::$CONNECTION_OAUTH:
                $access_token = $this->FetchAccessToken();
                break;
            case self::$CONNECTION_DEVELOPER:
                $userid = $this->ReadPropertyString('userid');
                $password = $this->ReadPropertyString('password');
                $client_id = $this->ReadPropertyString('client_id');
                $client_secret = $this->ReadPropertyString('client_secret');
                $vg_selector = $this->ReadPropertyString('vg_selector');

                $dtoken = $this->GetBuffer('Token');
                $jtoken = json_decode($dtoken, true);
                $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
                $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
                $type = isset($jtoken['type']) ? $jtoken['type'] : self::$CONNECTION_UNDEFINED;

                if ($type != self::$CONNECTION_DEVELOPER || $expiration < time()) {
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
                        $statuscode = self::$IS_INVALIDDATA;
                    }
                    $this->SendDebug(__FUNCTION__, 'token: statuscode=' . $statuscode . ', cdata=' . print_r($cdata, true) . ', msg=' . $msg, 0);
                    if ($statuscode != 0) {
                        $this->MaintainStatus($statuscode);
                        return '';
                    }

                    $jdata = json_decode($cdata, true);
                    $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

                    $access_token = $jdata['access_token'];
                    $expires_in = $jdata['expires_in'];

                    $jtoken = [
                        'access_token' => $access_token,
                        'expiration'   => time() + $expires_in,
                        'type'         => self::$CONNECTION_DEVELOPER
                    ];
                    $this->SetBuffer('Token', json_encode($jtoken));
                }
                break;
            default:
                $access_token = false;
                break;
        }
        return $access_token;
    }

    private function do_ApiCall($func, &$data, &$msg)
    {
        $language = $this->ReadPropertyString('language');

        $access_token = $this->GetAccessToken($msg);
        if ($access_token == false) {
            return false;
        }

        $params = [
            'language' => $language,
        ];

        $header = [
            'Accept: application/json; charset=utf-8',
            'Authorization: Bearer ' . $access_token,
        ];

        $msg = '';
        $statuscode = $this->do_HttpRequest($func, $params, $header, '', 'GET', $data, $msg);
        $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', data=' . print_r($data, true), 0);
        if ($statuscode != 0) {
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
        return $statuscode ? false : true;
    }

    private function do_ActionCall($func, $opts, &$data, &$msg)
    {
        $language = $this->ReadPropertyString('language');

        $access_token = $this->GetAccessToken($msg);
        if ($access_token == false) {
            return false;
        }

        $params = [
            'language' => $language,
        ];

        $header = [
            'Accept: */*',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token,
        ];

        $postdata = $opts != '' ? json_encode($opts) : '';

        $msg = '';
        $statuscode = $this->do_HttpRequest($func, $params, $header, $postdata, 'PUT', $data, $msg);
        $this->SendDebug(__FUNCTION__, 'statuscode=' . $statuscode . ', data=' . print_r($data, true), 0);
        if ($statuscode != 0) {
            $this->MaintainStatus($statuscode);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
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
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode == 200 || $httpcode == 204) {
            $data = $cdata;
        } elseif ($httpcode == 302) {
            $data = $redirect_url;
        } elseif ($httpcode == 401) {
            $statuscode = self::$IS_UNAUTHORIZED;
            $err = 'got http-code ' . $httpcode . ' (unauthorized)';
        } elseif ($httpcode >= 500 && $httpcode <= 599) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got http-code ' . $httpcode . ' (server error)';
        } else {
            $statuscode = self::$IS_HTTPERROR;
            $err = 'got http-code ' . $httpcode;
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', msg=' . $msg, 0);
        }

        return $statuscode;
    }
}
