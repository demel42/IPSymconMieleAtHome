<?php

declare(strict_types=1);

if (!defined('CONNECTION_UNDEFINED')) {
    define('CONNECTION_UNDEFINED', 0);
    define('CONNECTION_OAUTH', 1);
    define('CONNECTION_DEVELOPER', 2);
}

if (!defined('VARIABLETYPE_BOOLEAN')) {
    define('VARIABLETYPE_BOOLEAN', 0);
    define('VARIABLETYPE_INTEGER', 1);
    define('VARIABLETYPE_FLOAT', 2);
    define('VARIABLETYPE_STRING', 3);
}

if (!defined('IS_INVALIDCONFIG')) {
    define('IS_INVALIDCONFIG', IS_EBASE + 1);
    define('IS_UNAUTHORIZED', IS_EBASE + 2);
    define('IS_SERVERERROR', IS_EBASE + 3);
    define('IS_HTTPERROR', IS_EBASE + 4);
    define('IS_INVALIDDATA', IS_EBASE + 5);
    define('IS_NOSYMCONCONNECT', IS_EBASE + 6);
}

if (!defined('DEVICE_WASHING_MACHINE')) {
    define('DEVICE_WASHING_MACHINE', 1);
    define('DEVICE_TUMBLE_DRYER', 2);
    define('DEVICE_DISHWASHER', 7);
    define('DEVICE_DISHWASHER_SEMIPROF', 8);
    define('DEVICE_OVEN', 12);
    define('DEVICE_OVEN_MICROWAVE', 13);
    define('DEVICE_HOB_HIGHLIGHT', 14);
    define('DEVICE_STEAM_OVEN', 15);
    define('DEVICE_MICROWAVE', 16);
    define('DEVICE_COFFEE_SYSTEM', 17);
    define('DEVICE_HOOD', 18);
    define('DEVICE_FRIDGE', 19);
    define('DEVICE_FREEZER', 20);
    define('DEVICE_FRIDGE_FREEZER', 21);
    define('DEVICE_VACUUM_CLEANER', 23);
    define('DEVICE_WASHER_DRYER', 24);
    define('DEVICE_DISH_WARMER', 25);
    define('DEVICE_HOB_INDUCTION', 27);
    define('DEVICE_HOB_GAS', 28);
    define('DEVICE_STEAM_OVEN_COMBINATION', 31);
    define('DEVICE_WINE_CABINET', 32);
    define('DEVICE_WINE_CONDITIONING_UNIT', 33);
    define('DEVICE_WINE_STORAGE_CONDITIONING_UNIT', 34);
    define('DEVICE_DOUBLE_OVEN', 39);
    define('DEVICE_DOUBLE_STEAM_OVEN', 40);
    define('DEVICE_DOUBLE_STEAM_OVEN_COMBINATION', 41);
    define('DEVICE_DOUBLE_MICROWAVE', 42);
    define('DEVICE_DOUBLE_MICROWAVE_OVEN', 43);
    define('DEVICE_STEAM_OVEN_MICROWAVE_COMBINATION', 45);
    define('DEVICE_VACUUM_DRAWER', 48);
    define('DEVICE_DIALOGOVEN', 67);
    define('DEVICE_WINE_CABINET_FREEZER_COMBINATION', 68);
}

if (!defined('STATUS_RESERVED')) {
    define('STATUS_UNKNOWN', -1);
    define('STATUS_RESERVED', 0);
    define('STATUS_OFF', 1);
    define('STATUS_ON', 2);
    define('STATUS_PROGRAMMED', 3);
    define('STATUS_WAITING_TO_START', 4);
    define('STATUS_RUNNING', 5);
    define('STATUS_PAUSE', 6);
    define('STATUS_END_PROGRAMMED', 7);
    define('STATUS_FAILURE', 8);
    define('STATUS_PROGRAM_INTERRUPTED', 9);
    define('STATUS_IDLE', 10);
    define('STATUS_RINSE_HOLD', 11);
    define('STATUS_SERVICE', 12);
    define('STATUS_SUPERFREEZING', 13);
    define('STATUS_SUPERCOOLING', 14);
    define('STATUS_SUPERHEATING', 15);

    define('STATUS_SUPERCOOLING_SUPERFREEZING', 146);

    define('STATUS_NOT_CONNECTED', 255);
}

trait MieleAtHomeCommon
{
    protected function SetValue($Ident, $Value)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return;
        }

        @$ret = parent::SetValue($Ident, $Value);
        if ($ret == false) {
            $this->SendDebug(__FUNCTION__, 'mismatch of value "' . $Value . '" for variable ' . $Ident, 0);
        }
    }

    protected function GetValue($Ident)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return false;
        }

        $ret = parent::GetValue($Ident);
        return $ret;
    }

    protected function GetValueFormatted($Ident)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return false;
        }

        $ret = GetValueFormatted($varID);
        return $ret;
    }

    private function SaveValue($Ident, $Value, &$IsChanged)
    {
        @$varID = $this->GetIDForIdent($Ident);
        if ($varID == false) {
            $this->SendDebug(__FUNCTION__, 'missing variable ' . $Ident, 0);
            return;
        }

        if (parent::GetValue($Ident) != $Value) {
            $IsChanged = true;
        }

        @$ret = parent::SetValue($Ident, $Value);
        if ($ret == false) {
            $this->SendDebug(__FUNCTION__, 'mismatch of value "' . $Value . '" for variable ' . $Ident, 0);
            return;
        }
    }

    private function CreateVarProfile($Name, $ProfileType, $Suffix, $MinValue, $MaxValue, $StepSize, $Digits, $Icon, $Associations = '')
    {
        if (!IPS_VariableProfileExists($Name)) {
            IPS_CreateVariableProfile($Name, $ProfileType);
            IPS_SetVariableProfileText($Name, '', $Suffix);
            if (in_array($ProfileType, [VARIABLETYPE_INTEGER, VARIABLETYPE_FLOAT])) {
                IPS_SetVariableProfileValues($Name, $MinValue, $MaxValue, $StepSize);
                IPS_SetVariableProfileDigits($Name, $Digits);
            }
            IPS_SetVariableProfileIcon($Name, $Icon);
            if ($Associations != '') {
                foreach ($Associations as $a) {
                    $w = isset($a['Wert']) ? $a['Wert'] : '';
                    $n = isset($a['Name']) ? $a['Name'] : '';
                    $i = isset($a['Icon']) ? $a['Icon'] : '';
                    $f = isset($a['Farbe']) ? $a['Farbe'] : 0;
                    IPS_SetVariableProfileAssociation($Name, $w, $n, $i, $f);
                }
            }
        }
    }

    // Inspired from module SymconTest/HookServe
    private function RegisterHook($WebHook)
    {
        $ids = IPS_GetInstanceListByModuleID('{015A6EB8-D6E5-4B93-B496-0D3F77AE9FE1}');
        if (count($ids) > 0) {
            $hooks = json_decode(IPS_GetProperty($ids[0], 'Hooks'), true);
            $found = false;
            foreach ($hooks as $index => $hook) {
                if ($hook['Hook'] == $WebHook) {
                    if ($hook['TargetID'] == $this->InstanceID) {
                        return;
                    }
                    $hooks[$index]['TargetID'] = $this->InstanceID;
                    $found = true;
                }
            }
            if (!$found) {
                $hooks[] = ['Hook' => $WebHook, 'TargetID' => $this->InstanceID];
            }
            IPS_SetProperty($ids[0], 'Hooks', json_encode($hooks));
            IPS_ApplyChanges($ids[0]);
        }
    }

    // Inspired from module SymconTest/HookServe
    private function GetMimeType($extension)
    {
        $lines = file(IPS_GetKernelDirEx() . 'mime.types');
        foreach ($lines as $line) {
            $type = explode("\t", $line, 2);
            if (count($type) == 2) {
                $types = explode(' ', trim($type[1]));
                foreach ($types as $ext) {
                    if ($ext == $extension) {
                        return $type[0];
                    }
                }
            }
        }
        return 'text/plain';
    }

    private function GetArrayElem($data, $var, $dflt)
    {
        $ret = $data;
        $vs = explode('.', $var);
        foreach ($vs as $v) {
            if (!isset($ret[$v])) {
                $ret = $dflt;
                break;
            }
            $ret = $ret[$v];
        }
        return $ret;
    }

    // inspired by Nall-chan
    //   https://github.com/Nall-chan/IPSSqueezeBox/blob/6bbdccc23a0de51bb3fbc114cefc3acf23c27a14/libs/SqueezeBoxTraits.php
    public function __get($name)
    {
        $n = strpos($name, 'Multi_');
        if (strpos($name, 'Multi_') === 0) {
            $curCount = $this->GetBuffer('BufferCount_' . $name);
            if ($curCount == false) {
                $curCount = 0;
            }
            $data = '';
            for ($i = 0; $i < $curCount; $i++) {
                $data .= $this->GetBuffer('BufferPart' . $i . '_' . $name);
            }
        } else {
            $data = $this->GetBuffer($name);
        }
        return unserialize($data);
    }

    public function __set($name, $value)
    {
        $data = serialize($value);
        $n = strpos($name, 'Multi_');
        if (strpos($name, 'Multi_') === 0) {
            $oldCount = $this->GetBuffer('BufferCount_' . $name);
            if ($oldCount == false) {
                $oldCount = 0;
            }
            $parts = str_split($data, 8000);
            $newCount = count($parts);
            $this->SetBuffer('BufferCount_' . $name, $newCount);
            for ($i = 0; $i < $newCount; $i++) {
                $this->SetBuffer('BufferPart' . $i . '_' . $name, $parts[$i]);
            }
            for ($i = $newCount; $i < $oldCount; $i++) {
                $this->SetBuffer('BufferPart' . $i . '_' . $name, '');
            }
        } else {
            $this->SetBuffer($name, $data);
        }
    }

    private function SetMultiBuffer($name, $value)
    {
        $this->{'Multi_' . $name} = $value;
    }

    private function GetMultiBuffer($name)
    {
        $value = $this->{'Multi_' . $name};
        return $value;
    }

    private function bool2str($bval)
    {
        if (is_bool($bval)) {
            return $bval ? 'true' : 'false';
        }
        return $bval;
    }

    private function GetFormStatus()
    {
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
        $formStatus[] = ['code' => IS_NOSYMCONCONNECT, 'icon' => 'error', 'caption' => 'Instance is inactive (no Symcon-Connect)'];

        return $formStatus;
    }

    private function GetConnectUrl()
    {
        $instID = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}')[0];
        $url = CC_GetConnectURL($instID);
        return $url;
    }
}
