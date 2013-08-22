<?php
include "conf/main.php";
spl_autoload_register(function ($className) {
    $ds = DIRECTORY_SEPARATOR;
    $dir = __DIR__;
    $className = strtr($className, '\\', $ds);
    $file = "{$dir}{$ds}{$className}.php";
    if (is_readable($file)) {
        require_once $file;
    }
});
print_r(inc\Route::getRequest());

$request = inc\Route::getRequest();

//$controller = new api\v1\elasticsearch\Search_c();
$class = "{$request[1]}\\{$request[2]}\\{$request[3]}";
$controller = new $class();