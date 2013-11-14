<?php
namespace app\inc;

class Route
{
    static function add($uri, $func = "")
    {
        $requestUri = strtok($_SERVER["REQUEST_URI"], '?');
//        $requestUri = rtrim($requestUri, "/");
        if (strpos($requestUri, $uri) !== false) {
            if ($func) {
                $func();
            }
            $uri = rtrim($uri, "/");
            $e = explode("/", $uri);
            $e[count($e)-1] = ucfirst($e[count($e)-1]);
            $uri = implode($e, "/");
            $n = sizeof($e);
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