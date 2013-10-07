<?php
namespace app\conf;

use app\conf\App;

class IncludePath
{
    function __construct()
    {
        ini_set("display_errors", "On");
        error_reporting(3);
        set_include_path(get_include_path() . PATH_SEPARATOR . App::$param['path'] . PATH_SEPARATOR . App::$param['path'] . "app" . PATH_SEPARATOR . App::$param['path'] . "app/libs/PEAR/");
    }
}