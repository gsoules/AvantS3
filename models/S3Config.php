<?php

define('CONFIG_LABEL_S3_BUCKET', __('Bucket'));
define('CONFIG_LABEL_S3_KEY', __('Key'));
define('CONFIG_LABEL_S3_REGION', __('Region'));
define('CONFIG_LABEL_S3_SECRET', __('Secret'));

class S3Config extends ConfigOptions
{
    const OPTION_S3_BUCKET = 'avants3_bucket';
    const OPTION_S3_KEY = 'avants3__key';
    const OPTION_S3_REGION = 'avants3__region';
    const OPTION_S3_SECRET = 'avants3__secret';

    public static function getOptionValueForBucket()
    {
        return self::getOptionText(self::OPTION_S3_BUCKET);
    }

    public static function getOptionValueForKey()
    {
        return self::getOptionText(self::OPTION_S3_KEY);
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
        self::saveOptionDataForKey();
        self::saveOptionDataForRegion();
        self::saveOptionDataForSecret();
    }

    public static function saveOptionDataForBucket()
    {
        self::saveOptionText(self::OPTION_S3_BUCKET , CONFIG_LABEL_S3_BUCKET);
    }

    public static function saveOptionDataForKey()
    {
        self::saveOptionText(self::OPTION_S3_KEY , CONFIG_LABEL_S3_KEY);
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
