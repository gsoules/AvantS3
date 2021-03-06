<?php

define('CONFIG_LABEL_S3_BUCKET', __('Bucket'));
define('CONFIG_LABEL_S3_CONSOLE', __('Console'));
define('CONFIG_LABEL_S3_KEY', __('Key'));
define('CONFIG_LABEL_S3_PATH', __('Path'));
define('CONFIG_LABEL_S3_REGION', __('Region'));
define('CONFIG_LABEL_S3_SECRET', __('Secret'));

class S3Config extends ConfigOptions
{
    const OPTION_S3_BUCKET = 'avants3_bucket';
    const OPTION_S3_CONSOLE = 'avants3_console';
    const OPTION_S3_KEY = 'avants3_key';
    const OPTION_S3_PATH = 'avants3_path';
    const OPTION_S3_REGION = 'avants3_region';
    const OPTION_S3_SECRET = 'avants3_secret';

    public static function getOptionValueForBucket()
    {
        return self::getOptionText(self::OPTION_S3_BUCKET);
    }

    public static function getOptionValueForConsole()
    {
        return self::getOptionText(self::OPTION_S3_CONSOLE);
    }

    public static function getOptionValueForKey()
    {
        return self::getOptionText(self::OPTION_S3_KEY);
    }

    public static function getOptionValueForPath()
    {
        return self::getOptionText(self::OPTION_S3_PATH);
    }

    public static function getOptionValueForRegion()
    {
        return self::getOptionText(self::OPTION_S3_REGION);
    }

    public static function getOptionValueForSecret()
    {
        return self::getOptionText(self::OPTION_S3_SECRET);
    }

    public static function saveConfiguration()
    {
        self::saveOptionDataForBucket();
        self::saveOptionDataForConsole();
        self::saveOptionDataForKey();
        self::saveOptionDataForPath();
        self::saveOptionDataForRegion();
        self::saveOptionDataForSecret();
    }

    public static function saveOptionDataForBucket()
    {
        self::saveOptionText(self::OPTION_S3_BUCKET , CONFIG_LABEL_S3_BUCKET);
    }

    public static function saveOptionDataForConsole()
    {
        self::saveOptionText(self::OPTION_S3_CONSOLE , CONFIG_LABEL_S3_CONSOLE);
    }

    public static function saveOptionDataForKey()
    {
        self::saveOptionText(self::OPTION_S3_KEY , CONFIG_LABEL_S3_KEY);
    }

    public static function saveOptionDataForPath()
    {
        self::saveOptionText(self::OPTION_S3_PATH , CONFIG_LABEL_S3_PATH);
    }

    public static function saveOptionDataForRegion()
    {
        self::saveOptionText(self::OPTION_S3_REGION , CONFIG_LABEL_S3_REGION);
    }

    public static function saveOptionDataForSecret()
    {
        self::saveOptionText(self::OPTION_S3_SECRET, CONFIG_LABEL_S3_SECRET);
    }
}
