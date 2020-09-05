<?php

declare(strict_types=1);

trait MieleAtHomeLocalLib
{
    public static $IS_INVALIDCONFIG = IS_EBASE + 1;
    public static $IS_UNAUTHORIZED = IS_EBASE + 2;
    public static $IS_SERVERERROR = IS_EBASE + 3;
    public static $IS_HTTPERROR = IS_EBASE + 4;
    public static $IS_INVALIDDATA = IS_EBASE + 5;
    public static $IS_NOSYMCONCONNECT = IS_EBASE + 6;
    public static $IS_NOLOGIN = IS_EBASE + 7;
    public static $IS_NODATA = IS_EBASE + 8;

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

    public static $STATUS_UNKNOWN = -1;
    public static $STATUS_RESERVED = 0;
    public static $STATUS_OFF = 1;
    public static $STATUS_ON = 2;
    public static $STATUS_PROGRAMMED = 3;
    public static $STATUS_WAITING_TO_START = 4;
    public static $STATUS_RUNNING = 5;
    public static $STATUS_PAUSE = 6;
    public static $STATUS_END_PROGRAMMED = 7;
    public static $STATUS_FAILURE = 8;
    public static $STATUS_PROGRAM_INTERRUPTED = 9;
    public static $STATUS_IDLE = 10;
    public static $STATUS_RINSE_HOLD = 11;
    public static $STATUS_SERVICE = 12;
    public static $STATUS_SUPERFREEZING = 13;
    public static $STATUS_SUPERCOOLING = 14;
    public static $STATUS_SUPERHEATING = 15;

    public static $STATUS_SUPERCOOLING_SUPERFREEZING = 146;

    public static $STATUS_NOT_CONNECTED = 255;

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

    private function GetFormStatus()
    {
        $formStatus = [];

        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_INVALIDCONFIG, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid configuration)'];
        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NOSYMCONCONNECT, 'icon' => 'error', 'caption' => 'Instance is inactive (no Symcon-Connect)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];
        $formStatus[] = ['code' => self::$IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];

        return $formStatus;
    }
}
