<?php
namespace app\conf;

class Autoload
{
    function __construct()
    {
        ini_set("display_errors", "On");
        error_reporting(3);
        set_include_path(get_include_path() . PATH_SEPARATOR . $this->basePath . PATH_SEPARATOR . $this->basePath . "app" . PATH_SEPARATOR . $this->basePath . "app/libs/PEAR/");
        spl_autoload_register(function ($className) {
            $ds = DIRECTORY_SEPARATOR;
            $dir = "/mnt/hgfs/Documents/www/geocloud2/";
            $className = strtr($className, '\\', $ds);
            $file = "{$dir}{$className}.php";
            //echo $file . "<br>";
            //die();
            if (is_readable($file)) {
                require_once $file;
            }
        });
    }
}