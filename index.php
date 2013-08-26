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
use \app\inc\Input;

print_r(Input::getPath());


$request = Input::getPath();

if ($request[1] == "api") {
    $class = "app\\{$request[1]}\\{$request[2]}\\{$request[3]}\\".ucfirst($request[4])."_c";
    $controller = new $class();
    $method = Input::getMethod()."_index";
    echo $controller->$method();
}