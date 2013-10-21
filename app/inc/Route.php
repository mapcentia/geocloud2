<?php
namespace app\inc;

use \app\inc\Input;

class Route
{
    static function add($uri, $func="")
    {
        $requestUri = str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']);
        if (strpos($requestUri, $uri) !== false) {
            if ($func){
                $func();
            }
            // Remove trailing "/"
            if (substr($uri, -1) == "/"){
                $uri = rtrim($uri,"/");
            }
            $n = sizeof(explode("/", $uri));
            $className = strtr($uri, '/', '\\');
            $class = "app\\{$className}";

            $action = Input::getMethod() . "_" . Input::getPath()->part($n + 1);
                if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $action)) {
                    echo $controller->$action();
                } else {
                    $method = Input::getMethod() . "_index";
                    echo $controller->$method();
                }
            } else echo "Not class<br>";
        }
    }
}