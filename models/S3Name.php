<?php

class S3Name
{
    public $keyName;
    public $fileName;
    public $action;

    function __construct($keyName, $action)
    {
        $this->keyName = $keyName;

        // Create a Windows file name from the S3 keyname by replacing forward slashes with double underscores.
        // This is necessary to ensure that file names will be unique within a folder of subfolders.
        $this->fileName = str_replace("/", "__", $keyName);

        $this->action = $action;
    }
}