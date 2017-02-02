<?php
namespace app\inc;

use \app\inc\Util;

class Route
{
    static function add($uri, $func = "", $silent = false)
    {
        $time_start = Util::microtime_float();
        $requestUri = strtok($_SERVER["REQUEST_URI"], '?');
        if (strpos($requestUri, $uri) !== false) {
            if ($func) {
                $func();
            }
            $uri = trim($uri, "/");
            $e = explode("/", $uri);
            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);
            $uri = implode($e, "/");
            $n = sizeof($e);
            $className = strtr($uri, '/', '\\');
            $class = "app\\{$className}";
            $action = Input::getMethod() . "_" . Input::getPath()->part($n + 1);
            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $action)) {
                    $response = $controller->$action();
                } else {
                    $action = Input::getMethod() . "_index";
                    if (method_exists($controller, $action)) {
                        $response = $controller->$action();
                    } else {
                        header('HTTP/1.0 404 Not Found');
                        echo "<h1>404 Not Found</h1>";
                        exit();
                    }
                }
            }
            //header('charset=utf-8');
            //header('Content-Type: text/plain; charset=utf-8');
            $code = (isset($response["code"])) ? $response["code"] : "200";
            header("HTTP/1.0 {$code} " . Util::httpCodeText($code));
            if (isset($response["json"])) {
                echo Response::passthru($response["json"]);
            } elseif (isset($response["text"])) {
                echo Response::passthru($response["text"], "text/plain");
            } elseif (isset($response["csv"])) {
                //header('Content-Disposition: attachment; filename="data.csv"');
                echo Response::passthru($response["csv"], "text/plain");
            } else {
                if (!$silent) {
                    $response["_execution_time"] = round((Util::microtime_float() - $time_start), 3);
                    echo Response::toJson($response);
                }
            }
            exit();
        }
    }
}