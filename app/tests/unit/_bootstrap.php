<?php

spl_autoload_register(function ($className) {
    $ds = DIRECTORY_SEPARATOR;
    $dir = "/var/www/geocloud2/";
    $className = strtr($className, '\\', $ds);
    $file = "{$dir}{$className}.php";
    if (is_readable($file)) {
        require_once $file;
    }
});