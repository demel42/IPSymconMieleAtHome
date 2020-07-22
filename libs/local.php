<?php

declare(strict_types=1);

if (!defined('CONNECTION_UNDEFINED')) {
    define('CONNECTION_UNDEFINED', 0);
    define('CONNECTION_OAUTH', 1);
    define('CONNECTION_DEVELOPER', 2);
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

trait MieleAtHomeLocal
{
    public static $IS_INVALIDCONFIG = IS_EBASE + 1;
    public static $IS_UNAUTHORIZED = IS_EBASE + 2;
    public static $IS_SERVERERROR = IS_EBASE + 3;
    public static $IS_HTTPERROR = IS_EBASE + 4;
    public static $IS_INVALIDDATA = IS_EBASE + 5;
    public static $IS_NOSYMCONCONNECT = IS_EBASE + 6;
    public static $IS_NOLOGIN = IS_EBASE + 7;
    public static $IS_NODATA = IS_EBASE + 8;

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
