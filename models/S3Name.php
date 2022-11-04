<?php

class S3Name
{
    public $keyName;
    public $fileName;
    public $action;

    function __construct($keyName, $action)
    {
        $this->keyName = $keyName;
        $this->fileName = str_replace("/", "__", $keyName);
        $this->action = $action;
    }

}