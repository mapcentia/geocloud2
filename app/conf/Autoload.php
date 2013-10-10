<?php
namespace app\conf;

class Autoload
{
    function __construct()
    {
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