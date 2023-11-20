<?php

declare(strict_types=1);

trait MieleAtHomeLocalLib
{
    public static $IS_UNAUTHORIZED = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;
    public static $IS_NOLOGIN = IS_EBASE + 14;
    public static $IS_NODATA = IS_EBASE + 15;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];
        $formStatus[] = ['code' => self::$IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_NODATA:
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    public static $CONNECTION_UNDEFINED = 0;
    public static $CONNECTION_OAUTH = 1;
    public static $CONNECTION_DEVELOPER = 2;

    public static $DEVICE_WASHING_MACHINE = 1;
    public static $DEVICE_TUMBLE_DRYER = 2;
    public static $DEVICE_DISHWASHER = 7;
    public static $DEVICE_DISHWASHER_SEMIPROF = 8;
    public static $DEVICE_OVEN = 12;
    public static $DEVICE_OVEN_MICROWAVE = 13;
    public static $DEVICE_HOB_HIGHLIGHT = 14;
    public static $DEVICE_STEAM_OVEN = 15;
    public static $DEVICE_MICROWAVE = 16;
    public static $DEVICE_COFFEE_SYSTEM = 17;
    public static $DEVICE_HOOD = 18;
    public static $DEVICE_FRIDGE = 19;
    public static $DEVICE_FREEZER = 20;
    public static $DEVICE_FRIDGE_FREEZER = 21;
    public static $DEVICE_VACUUM_CLEANER = 23;
    public static $DEVICE_WASHER_DRYER = 24;
    public static $DEVICE_DISH_WARMER = 25;
    public static $DEVICE_HOB_INDUCTION = 27;
    public static $DEVICE_HOB_GAS = 28;
    public static $DEVICE_STEAM_OVEN_COMBINATION = 31;
    public static $DEVICE_WINE_CABINET = 32;
    public static $DEVICE_WINE_CONDITIONING_UNIT = 33;
    public static $DEVICE_WINE_STORAGE_CONDITIONING_UNIT = 34;
    public static $DEVICE_DOUBLE_OVEN = 39;
    public static $DEVICE_DOUBLE_STEAM_OVEN = 40;
    public static $DEVICE_DOUBLE_STEAM_OVEN_COMBINATION = 41;
    public static $DEVICE_DOUBLE_MICROWAVE = 42;
    public static $DEVICE_DOUBLE_MICROWAVE_OVEN = 43;
    public static $DEVICE_STEAM_OVEN_MICROWAVE_COMBINATION = 45;
    public static $DEVICE_VACUUM_DRAWER = 48;
    public static $DEVICE_DIALOGOVEN = 67;
    public static $DEVICE_WINE_CABINET_FREEZER_COMBINATION = 68;

    public static $STATE_UNKNOWN = -1;
    public static $STATE_RESERVED = 0;
    public static $STATE_OFF = 1;
    public static $STATE_ON = 2;
    public static $STATE_PROGRAMMED = 3;
    public static $STATE_WAITING_TO_START = 4;
    public static $STATE_RUNNING = 5;
    public static $STATE_PAUSE = 6;
    public static $STATE_END_PROGRAMMED = 7;
    public static $STATE_FAILURE = 8;
    public static $STATE_PROGRAM_INTERRUPTED = 9;
    public static $STATE_IDLE = 10;
    public static $STATE_RINSE_HOLD = 11;
    public static $STATE_SERVICE = 12;
    public static $STATE_SUPERFREEZING = 13;
    public static $STATE_SUPERCOOLING = 14;
    public static $STATE_SUPERHEATING = 15;

    public static $STATE_SUPERCOOLING_SUPERFREEZING = 146;

    public static $STATE_NOT_CONNECTED = 255;

    public static $ACTION_UNDEF = 0;
    public static $ACTION_START = 1;
    public static $ACTION_PAUSE = 2;
    public static $ACTION_STOP = 3;

    public static $LIGHT_UNDEF = 0;
    public static $LIGHT_ENABLE = 1;
    public static $LIGHT_DISABLE = 2;

    public static $POWER_UNDEF = 0;
    public static $POWER_ON = 1;
    public static $POWER_OFF = 2;

    public static $PROCESS_START = 1;
    public static $PROCESS_STOP = 2;
    public static $PROCESS_PAUSE = 3;
    public static $PROCESS_START_SUPERFREEZING = 4;
    public static $PROCESS_STOP_SUPERFREEZING = 5;
    public static $PROCESS_START_SUPERCOOLING = 6;
    public static $PROCESS_STOP_SUPERCOOLING = 7;

    public static $OPERATIONMODE_NORMAL = 0;
    public static $OPERATIONMODE_SABBATH = 1;
    public static $OPERATIONMODE_PARTY = 2;
    public static $OPERATIONMODE_HOLIDAY = 3;

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('No'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('Yes'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.YesNo', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $this->CreateVarProfile('MieleAtHome.Duration', VARIABLETYPE_INTEGER, ' min', 0, 0, 0, 0, 'Hourglass', [], $reInstall);
        $this->CreateVarProfile('MieleAtHome.Temperature', VARIABLETYPE_INTEGER, ' °C', 0, 0, 0, 0, 'Temperature', [], $reInstall);
        $this->CreateVarProfile('MieleAtHome.SpinningSpeed', VARIABLETYPE_INTEGER, ' U/min', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('MieleAtHome.BatteryLevel', VARIABLETYPE_INTEGER, ' %', 0, 0, 0, 0, '', [], $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => '-', 'Farbe' => -1],
            ['Wert' => 1, 'Name' => '%d %%', 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.WorkProgress', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$STATE_UNKNOWN, 'Name' => $this->Translate('Unknown'), 'Farbe' => -1],
            ['Wert' => self::$STATE_RESERVED, 'Name' => $this->Translate('Reserved'), 'Farbe' => -1],
            ['Wert' => self::$STATE_OFF, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => self::$STATE_ON, 'Name' => $this->Translate('On'), 'Farbe' => -1],
            ['Wert' => self::$STATE_PROGRAMMED, 'Name' => $this->Translate('Programmed'), 'Farbe' => -1],
            ['Wert' => self::$STATE_WAITING_TO_START, 'Name' => $this->Translate('Waiting to start'), 'Farbe' => -1],
            ['Wert' => self::$STATE_RUNNING, 'Name' => $this->Translate('Running'), 'Farbe' => -1],
            ['Wert' => self::$STATE_PAUSE, 'Name' => $this->Translate('Pause'), 'Farbe' => -1],
            ['Wert' => self::$STATE_END_PROGRAMMED, 'Name' => $this->Translate('End programmed'), 'Farbe' => -1],
            ['Wert' => self::$STATE_FAILURE, 'Name' => $this->Translate('Failure'), 'Farbe' => -1],
            ['Wert' => self::$STATE_PROGRAM_INTERRUPTED, 'Name' => $this->Translate('Program interrupted'), 'Farbe' => -1],
            ['Wert' => self::$STATE_IDLE, 'Name' => $this->Translate('Idle'), 'Farbe' => -1],
            ['Wert' => self::$STATE_RINSE_HOLD, 'Name' => $this->Translate('Rinse hold'), 'Farbe' => -1],
            ['Wert' => self::$STATE_SERVICE, 'Name' => $this->Translate('Service'), 'Farbe' => -1],
            ['Wert' => self::$STATE_SUPERFREEZING, 'Name' => $this->Translate('Superfreezing'), 'Farbe' => -1],
            ['Wert' => self::$STATE_SUPERCOOLING, 'Name' => $this->Translate('Supercooling'), 'Farbe' => -1],
            ['Wert' => self::$STATE_SUPERHEATING, 'Name' => $this->Translate('Superheating'), 'Farbe' => -1],
            ['Wert' => self::$STATE_NOT_CONNECTED, 'Name' => $this->Translate('Not connected'), 'Farbe' => -1],
            ['Wert' => self::$STATE_SUPERCOOLING_SUPERFREEZING, 'Name' => $this->Translate('Superfreezing/cooling'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Status', VARIABLETYPE_INTEGER, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => false, 'Name' => $this->Translate('Closed'), 'Farbe' => -1],
            ['Wert' => true, 'Name' => $this->Translate('Opened'), 'Farbe' => 0xEE0000],
        ];
        $this->CreateVarProfile('MieleAtHome.Door', VARIABLETYPE_BOOLEAN, '', 0, 0, 0, 1, 'Door', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$LIGHT_ENABLE, 'Name' => $this->Translate('On'), 'Farbe' => -1],
            ['Wert' => self::$LIGHT_DISABLE, 'Name' => $this->Translate('Off'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Light', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, 'Light', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$POWER_ON, 'Name' => $this->Translate('switch on'), 'Farbe' => -1],
            ['Wert' => self::$POWER_OFF, 'Name' => $this->Translate('switch off'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.PowerSupply', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, 'Power', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1],
            ['Wert' => self::$ACTION_PAUSE, 'Name' => $this->Translate('pause'), 'Farbe' => -1],
            ['Wert' => self::$ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Action', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1],
            ['Wert' => self::$ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Superfreezing', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$ACTION_START, 'Name' => $this->Translate('start'), 'Farbe' => -1],
            ['Wert' => self::$ACTION_STOP, 'Name' => $this->Translate('stop'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Supercooling', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => self::$OPERATIONMODE_NORMAL, 'Name' => $this->Translate('Normal operation mode'), 'Farbe' => -1],
            ['Wert' => self::$OPERATIONMODE_SABBATH, 'Name' => $this->Translate('Sabbath mode'), 'Farbe' => -1],
            ['Wert' => self::$OPERATIONMODE_PARTY, 'Name' => $this->Translate('Party mode'), 'Farbe' => -1],
            ['Wert' => self::$OPERATIONMODE_HOLIDAY, 'Name' => $this->Translate('Holiday mode'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.OperationMode', VARIABLETYPE_INTEGER, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => '-', 'Farbe' => -1],
            ['Wert' => 0.1, 'Name' => '%.1f kWh', 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Energy', VARIABLETYPE_FLOAT, '', 0, 0, 0, 1, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 0, 'Name' => '-', 'Farbe' => -1],
            ['Wert' => 1, 'Name' => '%.0f l', 'Farbe' => -1],
        ];
        $this->CreateVarProfile('MieleAtHome.Water', VARIABLETYPE_FLOAT, '', 0, 0, 0, 1, '', $associations, $reInstall);
    }
}
