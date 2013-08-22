<?php
//include "app/conf/main.php";
spl_autoload_register(function ($className) {
    $ds = DIRECTORY_SEPARATOR;
    $dir = __DIR__;
    $className = strtr($className, '\\', $ds);
    $file = "{$dir}{$ds}{$className}.php";
    if (is_readable($file)) {
        require_once $file;
    }
});
print_r(app\inc\Route::getRequest());

$request = app\inc\Route::getRequest();

if ($request[1] == "api") {
    $class = "app\\{$request[1]}\\{$request[2]}\\{$request[3]}\\".ucfirst($request[4])."_c";
    $controller = new $class();
    $o = app\inc\Route::getMethod()."_index";
    echo $controller->$o();
}