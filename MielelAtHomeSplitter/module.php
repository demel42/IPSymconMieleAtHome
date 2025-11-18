<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';
require_once __DIR__ . '/../libs/images.php';

class MieleAtHomeSplitter extends IPSModule
{
    use MieleAtHome\StubsCommonLib;
    use MieleAtHomeLocalLib;
    use MieleAtHomeImagesLib;

    private $oauthIdentifer = 'miele_at_home';

    private $SemaphoreID;

    private function GetSemaphoreTM()
    {
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');
        $semaphoreTM = ((($curl_exec_timeout + ceil($curl_exec_delay)) * $curl_exec_attempts) + 1) * 1000;

        //$this->SendDebug(__FUNCTION__, 'semaphoreTM='.$semaphoreTM, 0);
        return $semaphoreTM;
    }

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonConstruct(__DIR__);
        $this->SemaphoreID = __CLASS__ . '_' . $InstanceID;
    }

    public function __destruct()
    {
        $this->CommonDestruct();
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

        $this->RegisterPropertyBoolean('collectApiCallStats', true);

        $this->RegisterPropertyInteger('curl_exec_timeout', 15);
        $this->RegisterPropertyInteger('curl_exec_attempts', 3);
        $this->RegisterPropertyFloat('curl_exec_delay', 1);

        $this->RegisterAttributeString('ApiRefreshToken', json_encode([]));
        $this->RegisterAttributeString('ApiAccessToken', json_encode([]));
        $this->RegisterAttributeInteger('ConnectionType', self::$CONNECTION_UNDEFINED);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('RenewTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "RenewToken", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);

        $this->RequireParent('{2FADB4B7-FDAB-3C64-3E2C-068A4809849A}');
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
            if ($oauth_type == self::$CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
            $module_disable = $this->ReadPropertyBoolean('module_disable');
            if ($module_disable == false) {
                $this->SetRefreshTimer();
            }
        }
        if (IPS_GetKernelRunlevel() == KR_READY && $message == IM_CHANGESTATUS && $senderID == $this->GetConnectionID()) {
            $this->SendDebug(__FUNCTION__, 'timestamp=' . $timestamp . ', senderID=' . $senderID . ', message=' . $message . ', data=' . print_r($data, true), 0);
            if ($data[0] == IS_ACTIVE && $data[1] != IS_ACTIVE) {
                $module_disable = $this->ReadPropertyBoolean('module_disable');
                if ($module_disable == false) {
                    $this->MaintainTimer('RenewTimer', 60 * 1000);
                }
            }
        }
    }

    public function GetConfigurationForParent()
    {
        $headers = [
            [
                'Name'  => 'Accept',
                'Value' => 'text/event-stream',
            ],
            [
                'Name'  => 'Accept-Language',
                'Value' => 'de',
            ],
        ];
        $access_token = $this->GetAccessToken();
        if ($access_token != '') {
            $headers[] = [
                'Name'  => 'Authorization',
                'Value' => 'Bearer ' . $access_token,
            ];
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        $active = $module_disable != true && $access_token != [];

        $r = IPS_GetConfiguration($this->GetConnectionID());
        $j = [
            'Active'     => $active,
            'Headers'    => json_encode($headers),
            'URL'        => 'https://api.mcs3.miele.com/v1/devices/all/events',
            'VerifyHost' => true,
            'VerifyPeer' => true,
        ];
        $d = json_encode($j);
        $this->SendDebug(__FUNCTION__, $d, 0);
        return $d;
    }

    // bei jeder Änderung des access_token muss dieser im SSE-Client als Header gesetzt werden
    // Rücksprache mit NT per Mail am 07.11.2023
    private function UpdateConfigurationForParent()
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $d = $this->GetConfigurationForParent();
        IPS_SetConfiguration($this->GetConnectionID(), $d);
        IPS_ApplyChanges($this->GetConnectionID());
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

        $this->UnregisterMessage($this->GetConnectionID(), IM_CHANGESTATUS);

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

        $vpos = 1000;
        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        $this->MaintainMedia('ApiCallStats', $this->Translate('API call statistics'), MEDIATYPE_DOCUMENT, '.txt', false, $vpos++, $collectApiCallStats);

        if ($collectApiCallStats) {
            $apiLimits = [];
            $apiNotes = '';
            $this->ApiCallSetInfo($apiLimits, $apiNotes);
        }

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $connection_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($this->ReadAttributeInteger('ConnectionType') != $connection_type) {
            $this->ClearToken();
            $this->WriteAttributeInteger('ConnectionType', $connection_type);
        }

        $this->MaintainStatus(IS_ACTIVE);

        $this->RegisterMessage($this->GetConnectionID(), IM_CHANGESTATUS);

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == self::$CONNECTION_OAUTH) {
            if ($this->GetConnectUrl() == false) {
                $this->MaintainStatus(self::$IS_NOSYMCONCONNECT);
                return;
            }
            $refresh_token = $this->ReadAttributeString('ApiRefreshToken');
            if ($refresh_token == '') {
                $this->MaintainStatus(self::$IS_NOLOGIN);
                return;
            }
        }

        if (IPS_GetKernelRunlevel() == KR_READY) {
            if ($oauth_type == self::$CONNECTION_OAUTH) {
                $this->RegisterOAuth($this->oauthIdentifer);
            }
            $this->SetRefreshTimer();
        }
    }

    private function SetRefreshTimer()
    {
        $msec = 0;

        $jtoken = @json_decode($this->ReadAttributeString('ApiAccessToken'), true);
        if ($jtoken != false) {
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration) {
                $sec = $expiration - time() - (60 * 15);
                if ($sec > (24 * 60 * 60)) {
                    $sec = 24 * 60 * 60;
                }
                $msec = $sec > 0 ? $sec * 1000 : 100;
            }
        }

        $this->MaintainTimer('RenewTimer', $msec);
    }

    private function GetRefreshToken()
    {
        $jtoken = @json_decode($this->ReadAttributeString('ApiRefreshToken'), true);
        if ($jtoken != false) {
            $refresh_token = isset($jtoken['refresh_token']) ? $jtoken['refresh_token'] : '';
            if ($refresh_token != '') {
                $this->SendDebug(__FUNCTION__, 'use old refresh_token', 0);
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved refresh_token', 0);
            $refresh_token = '';
        }
        return $refresh_token;
    }

    private function SetRefreshToken($refresh_token = '')
    {
        $jtoken = [
            'tstamp'        => time(),
            'refresh_token' => $refresh_token,
        ];
        $this->WriteAttributeString('ApiRefreshToken', json_encode($jtoken));
        if ($refresh_token == '') {
            $this->SendDebug(__FUNCTION__, 'clear refresh_token', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'set new refresh_token=' . $refresh_token, 0);
        }
    }

    private function GetAccessToken()
    {
        $jtoken = @json_decode($this->ReadAttributeString('ApiAccessToken'), true);
        if ($jtoken != false) {
            $access_token = isset($jtoken['access_token']) ? $jtoken['access_token'] : '';
            $expiration = isset($jtoken['expiration']) ? $jtoken['expiration'] : 0;
            if ($expiration < time()) {
                $this->SendDebug(__FUNCTION__, 'access_token expired', 0);
                $access_token = '';
            }
            if ($access_token != '') {
                $this->SendDebug(__FUNCTION__, 'use old access_token, valid until ' . date('d.m.y H:i:s', $expiration), 0);
            }
        } else {
            $this->SendDebug(__FUNCTION__, 'no saved access_token', 0);
            $access_token = '';
        }
        return $access_token;
    }

    private function RenewToken()
    {
        $this->SendDebug(__FUNCTION__, '', 0);
        $this->GetApiAccessToken(true);
    }

    private function SetAccessToken($access_token = '', $expiration = 0)
    {
        $jtoken = [
            'tstamp'       => time(),
            'access_token' => $access_token,
            'expiration'   => $expiration,
        ];
        $this->WriteAttributeString('ApiAccessToken', json_encode($jtoken));
        if ($access_token == '') {
            $this->SendDebug(__FUNCTION__, 'clear access_token', 0);
        } else {
            $this->SendDebug(__FUNCTION__, 'set new access_token=' . $access_token . ', valid until ' . date('d.m.y H:i:s', $expiration), 0);
        }
        $this->UpdateConfigurationForParent();
        if ($expiration) {
            $this->SetRefreshTimer();
        }
    }

    private function Login()
    {
        $url = 'https://oauth.ipmagic.de/authorize/' . $this->oauthIdentifer . '?username=' . urlencode(IPS_GetLicensee());
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        return $url;
    }

    protected function ProcessOAuthData()
    {
        if (!isset($_GET['code'])) {
            $this->SendDebug(__FUNCTION__, '"code" missing, _GET=' . print_r($_GET, true), 0);
            $this->SendDebug(__FUNCTION__, 'clear refresh- & access-token', 0);
            $this->SetRefreshToken('');
            $this->SetAccessToken('');
            $this->MaintainStatus(self::$IS_NOLOGIN);
            return;
        }

        $code = $_GET['code'];
        $this->SendDebug(__FUNCTION__, 'code=' . $code, 0);

        $jdata = $this->Call4ApiToken(['code' => $code]);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'got no token, clear refresh- & access-token', 0);
            $this->SetRefreshToken('');
            $this->SetAccessToken('');
            return false;
        }

        $access_token = $jdata['access_token'];
        $expiration = time() + $jdata['expires_in'];
        $this->SetAccessToken($access_token, $expiration);
        $refresh_token = $jdata['refresh_token'];
        $this->SetRefreshToken($refresh_token);

        if ($this->GetStatus() == self::$IS_NOLOGIN) {
            $this->MaintainStatus(IS_ACTIVE);
        }
    }

    protected function Call4ApiToken($content)
    {
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');

        $url = 'https://oauth.ipmagic.de/access_token/' . $this->oauthIdentifer;
        $this->SendDebug(__FUNCTION__, 'url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    content=' . print_r($content, true), 0);

        $headerfields = [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ];

        $time_start = microtime(true);
        $curl_opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $this->build_header($headerfields),
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => http_build_query($content),
            CURLOPT_HEADER         => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $curl_exec_timeout,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);

        $statuscode = 0;
        $err = '';
        $jbody = false;

        $attempt = 1;
        do {
            $response = curl_exec($ch);
            $cerrno = curl_errno($ch);
            $cerror = $cerrno ? curl_error($ch) : '';
            if ($cerrno) {
                $this->SendDebug(__FUNCTION__, ' => attempt=' . $attempt . ', got curl-errno ' . $cerrno . ' (' . $cerror . ')', 0);
                IPS_Sleep((int) floor($curl_exec_delay * 1000));
            }
        } while ($cerrno && $attempt++ <= $curl_exec_attempts);

        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        $httpcode = $curl_info['http_code'];

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's, attempts=' . $attempt, 0);

        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } else {
            $header_size = $curl_info['header_size'];
            $head = substr($response, 0, $header_size);
            $body = substr($response, $header_size);

            $this->SendDebug(__FUNCTION__, ' => head=' . $head, 0);
            if ($body == '' || ctype_print($body)) {
                $this->SendDebug(__FUNCTION__, ' => body=' . $body, 0);
            } else {
                $this->SendDebug(__FUNCTION__, ' => body potentially contains binary data, size=' . strlen($body), 0);
            }
        }
        if ($statuscode == 0) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } elseif ($httpcode != 200) {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        }
        if ($statuscode == 0) {
            if ($body == '') {
                $statuscode = self::$IS_NODATA;
                $err = 'no data';
            } else {
                $jbody = json_decode($body, true);
                if ($jbody == '') {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                } else {
                    if (!isset($jbody['token_type']) || $jbody['token_type'] != 'Bearer') {
                        $statuscode = self::$IS_INVALIDDATA;
                        $err = 'malformed response';
                    }
                }
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, '    statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->MaintainStatus($statuscode);
            return false;
        }
        return $jbody;
    }

    private function DeveloperApiAccessToken()
    {
        $userid = $this->ReadPropertyString('userid');
        $password = $this->ReadPropertyString('password');
        $client_id = $this->ReadPropertyString('client_id');
        $client_secret = $this->ReadPropertyString('client_secret');
        $vg_selector = $this->ReadPropertyString('vg_selector');

        $header = [
            'Accept: application/json; charset=utf-8',
            'Content-Type: application/x-www-form-urlencoded'
        ];

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

        $cdata = '';
        $msg = '';
        $statuscode = $this->do_HttpRequest('/thirdparty/token', $params, $header, '', 'POST', $cdata, $msg);
        if ($statuscode == 0 && $cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
        }
        $this->SendDebug(__FUNCTION__, 'token: statuscode=' . $statuscode . ', cdata=' . print_r($cdata, true) . ', msg=' . $msg, 0);
        if ($statuscode != 0) {
            $this->MaintainStatus($statuscode);
            return false;
        }

        $jdata = json_decode($cdata, true);
        $this->SendDebug(__FUNCTION__, 'jdata=' . print_r($jdata, true), 0);

        return $jdata;
    }

    private function GetApiAccessToken($renew = false)
    {
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        if ($renew == false) {
            $access_token = $this->GetAccessToken();
            if ($access_token != '') {
                IPS_SemaphoreLeave($this->SemaphoreID);
                return $access_token;
            }
        }

        $connection_type = $this->ReadPropertyInteger('OAuth_Type');
        switch ($connection_type) {
            case self::$CONNECTION_OAUTH:
                $refresh_token = $this->GetRefreshToken();
                if ($refresh_token == '') {
                    $this->SendDebug(__FUNCTION__, 'has no refresh_token, clear access-token', 0);
                    $this->SetAccessToken('');
                    $this->MaintainStatus(self::$IS_NOLOGIN);
                    IPS_SemaphoreLeave($this->SemaphoreID);
                    return false;
                }
                $jdata = $this->Call4ApiToken(['refresh_token' => $refresh_token]);
                break;
            case self::$CONNECTION_DEVELOPER:
                $jdata = $this->DeveloperApiAccessToken();
                break;
            default:
                $jdata = false;
                break;
        }
        if ($jdata == false) {
            $this->SendDebug(__FUNCTION__, 'got no access_token, clear access-token', 0);
            $this->SetAccessToken('');
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'token jdata=' . print_r($jdata, true), 0);
        $access_token = $jdata['access_token'];
        $expiration = time() + $jdata['expires_in'];
        $this->SetAccessToken($access_token, $expiration);
        if (isset($jdata['refresh_token'])) {
            $refresh_token = $jdata['refresh_token'];
            $this->SetRefreshToken($refresh_token);
        }

        $this->MaintainStatus(IS_ACTIVE);
        IPS_SemaphoreLeave($this->SemaphoreID);
        return $access_token;
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('Miele@Home Splitter');

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
                    'caption' => $this->Translate('England'),
                    'value'   => 'en'
                ],
                [
                    'caption' => $this->Translate('Germany'),
                    'value'   => 'de'
                ],
            ],
            'name'    => 'language',
            'caption' => 'Miele@Home Language Settings'
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

        $formElements[] = [
            'type'    => 'ExpansionPanel',
            'items'   => [
                [
                    'type'    => 'Label',
                    'caption' => 'Behavior of HTTP requests at the technical level'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'suffix'  => 'Seconds',
                    'name'    => 'curl_exec_timeout',
                    'caption' => 'Timeout of an HTTP call'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'minimum' => 0,
                    'name'    => 'curl_exec_attempts',
                    'caption' => 'Number of attempts after communication failure'
                ],
                [
                    'type'     => 'NumberSpinner',
                    'minimum'  => 0.1,
                    'maximum'  => 60,
                    'digits'   => 1,
                    'suffix'   => 'Seconds',
                    'name'     => 'curl_exec_delay',
                    'caption'  => 'Delay between attempts'
                ],
            ],
            'caption' => 'Communication'
        ];

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'collectApiCallStats',
            'caption' => 'Collect data of API calls'
        ];

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

        $items = [
            $this->GetInstallVarProfilesFormItem(),
            [
                'type'    => 'Button',
                'caption' => 'Clear token',
                'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ClearToken", "");',
            ],
        ];

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $items[] = $this->GetApiCallStatsFormItem();
        }

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => $items,
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'ClearToken':
                $this->ClearToken();
                break;
            case 'TestAccess':
                $this->TestAccess();
                break;
            case 'RenewToken':
                $this->RenewToken();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        switch ($ident) {
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

        $access_token = $this->GetApiAccessToken();
        if ($access_token == false) {
            $msg = $this->Translate('invalid account-data') . PHP_EOL;
            $this->PopupMessage($msg);
            return;
        }

        $this->UpdateConfigurationForParent();

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
        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return false;
        }

        $this->SendDebug(__FUNCTION__, 'clear refresh- & access-token', 0);
        $this->SetRefreshToken('');
        $this->SetAccessToken('');

        $oauth_type = $this->ReadPropertyInteger('OAuth_Type');
        if ($oauth_type == self::$CONNECTION_OAUTH) {
            $this->MaintainStatus(self::$IS_NOLOGIN);
        }

        IPS_SemaphoreLeave($this->SemaphoreID);
    }

    protected function SendData($buf)
    {
        $data = [
            'DataID' => '{D39AEB86-E611-4752-81C7-DBF7E41E79E1}',
            'Buffer' => $buf
        ];
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($data, true), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function ReceiveData($data)
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);
        if (isset($jdata['Event']) && in_array($jdata['Event'], ['devices', 'actions'])) {
            $ndata = [
                'DataID' => '{40C8346D-9284-1D83-67C7-F9B46EF28C05}',
                'Event'  => $jdata['Event'],
                'Data'   => $jdata['Data'],
            ];
            $this->SendDebug(__FUNCTION__, 'ndata=' . print_r($ndata, true), 0);
            $this->SendDataToChildren(json_encode($ndata));
        }
    }

    public function ForwardData($data)
    {
        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return false;
        }

        $jdata = json_decode($data, true);
        $this->SendDebug(__FUNCTION__, 'data=' . print_r($jdata, true), 0);

        $callerID = $jdata['CallerID'];
        $this->SendDebug(__FUNCTION__, 'caller=' . $callerID . '(' . IPS_GetName($callerID) . ')', 0);
        $_IPS['CallerID'] = $callerID;

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
                case 'GetDevicePrograms':
                    $ident = $jdata['Ident'];
                    $msg = '';
                    $r = $this->do_ApiCall('/v1/devices/' . $ident . '/programs', $ret, $msg);
                    break;
                case 'Action':
                    $ident = $jdata['Ident'];
                    $action = $jdata['Action'];
                    $msg = '';
                    $r = $this->do_ActionCall('/v1/devices/' . $ident . '/actions', $action, $ret, $msg);
                    $ret = json_encode(['Status' => $r, 'Message'=> $msg]);
                    break;
                case 'Program':
                    $ident = $jdata['Ident'];
                    $program = $jdata['Program'];
                    $msg = '';
                    $r = $this->do_ProgramCall('/v1/devices/' . $ident . '/programs', $program, $ret, $msg);
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

    private function do_ApiCall($func, &$data, &$msg)
    {
        $access_token = $this->GetApiAccessToken();
        if ($access_token == false) {
            return false;
        }

        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }

        $language = $this->ReadPropertyString('language');

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
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
        IPS_SemaphoreLeave($this->SemaphoreID);
        return $statuscode ? false : true;
    }

    private function do_ActionCall($func, $opts, &$data, &$msg)
    {
        $access_token = $this->GetApiAccessToken();
        if ($access_token == false) {
            return false;
        }

        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }

        $language = $this->ReadPropertyString('language');

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
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
        IPS_SemaphoreLeave($this->SemaphoreID);
        return $statuscode ? false : true;
    }

    private function do_ProgramCall($func, $opts, &$data, &$msg)
    {
        $access_token = $this->GetApiAccessToken();
        if ($access_token == false) {
            return false;
        }

        if (IPS_SemaphoreEnter($this->SemaphoreID, $this->GetSemaphoreTM()) == false) {
            $this->SendDebug(__FUNCTION__, 'unable to lock sempahore ' . $this->SemaphoreID, 0);
            return;
        }

        $language = $this->ReadPropertyString('language');

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
            IPS_SemaphoreLeave($this->SemaphoreID);
            return false;
        }

        $this->MaintainStatus(IS_ACTIVE);
        IPS_SemaphoreLeave($this->SemaphoreID);
        return $statuscode ? false : true;
    }

    private function do_HttpRequest($func, $params, $header, $postdata, $mode, &$data, &$msg)
    {
        $curl_exec_timeout = $this->ReadPropertyInteger('curl_exec_timeout');
        $curl_exec_attempts = $this->ReadPropertyInteger('curl_exec_attempts');
        $curl_exec_delay = $this->ReadPropertyFloat('curl_exec_delay');

        $url = $this->build_url('https://api.mcs3.miele.com' . $func, $params);

        $this->SendDebug(__FUNCTION__, 'http-' . $mode . ': url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '    header=' . print_r($header, true), 0);

        if ($postdata != '') {
            if (is_array($postdata)) {
                $postdata = http_build_query($postdata);
            }
            $this->SendDebug(__FUNCTION__, '    postdata=' . $postdata, 0);
        }

        $curl_opts = [
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $header,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $curl_exec_timeout,
        ];
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                $curl_opts[CURLOPT_POST] = true;
                $curl_opts[CURLOPT_POSTFIELDS] = $postdata;
                break;
            case 'PUT':
                $curl_opts[CURLOPT_POSTFIELDS] = $postdata;
                $curl_opts[CURLOPT_CUSTOMREQUEST] = $mode;
                break;
            case 'DELETE':
                $curl_opts[CURLOPT_CUSTOMREQUEST] = $mode;
                break;
        }

        $time_start = microtime(true);

        $statuscode = 0;
        $err = '';
        $msg = '';
        $data = '';

        $ch = curl_init();
        curl_setopt_array($ch, $curl_opts);

        $attempt = 1;
        do {
            $cdata = curl_exec($ch);
            $cerrno = curl_errno($ch);
            $cerror = $cerrno ? curl_error($ch) : '';
            if ($cerrno) {
                $this->SendDebug(__FUNCTION__, ' => attempt=' . $attempt . ', got curl-errno ' . $cerrno . ' (' . $cerror . ')', 0);
                IPS_Sleep((int) floor($curl_exec_delay * 1000));
            }
        } while ($cerrno && $attempt++ <= $curl_exec_attempts);

        $curl_info = curl_getinfo($ch);
        curl_close($ch);

        $httpcode = $curl_info['http_code'];
        $redirect_url = $curl_info['redirect_url'];

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's, attempts=' . $attempt, 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);

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
        } elseif ($httpcode == 400) {
            $patternV = [
                ' is not in the correct state',
                ' can\'t be powered on from its current state',
            ];
            if (preg_match('#' . implode('|', $patternV) . '#', $msg, $r)) {
                $this->SendDebug(__FUNCTION__, 'ignore http-code ' . $httpcode . ' (bad request)', 0);
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . ' (bad request)';
            }
        } elseif ($httpcode == 401) {
            $statuscode = self::$IS_UNAUTHORIZED;
            $err = 'got http-code ' . $httpcode . ' (unauthorized)';
        } elseif ($httpcode == 500) {
            if (preg_match('#^GENERIC_TECHNICAL_ERROR \(#', $msg, $r)) {
                $this->SendDebug(__FUNCTION__, 'ignore http-code ' . $httpcode . ' (server error)', 0);
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            }
        } elseif ($httpcode > 500 && $httpcode <= 599) {
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

        $collectApiCallStats = $this->ReadPropertyBoolean('collectApiCallStats');
        if ($collectApiCallStats) {
            $this->ApiCallCollect($url, $err, $statuscode);
        }

        return $statuscode;
    }

    private function build_url($url, $params)
    {
        $n = 0;
        if (is_array($params)) {
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode(strval($value));
            }
        }
        return $url;
    }

    private function build_header($headerfields)
    {
        $header = [];
        foreach ($headerfields as $key => $value) {
            $header[] = $key . ': ' . $value;
        }
        return $header;
    }
}
