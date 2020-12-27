<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use Closure;

class Route
{
    /**
     * @var array<mixed>
     */
    static $params;

    /**
     * @param string $uri
     * @param Closure|null $func
     * @param bool $silent
     */
    static public function add(string $uri, Closure $func = null, bool $silent = false): void
    {
        $signatureMatch = false;
        $e = [];
        $r = [];
        $time_start = Util::microtime_float();
        $uri = trim($uri, "/");
        $requestUri = trim(strtok($_SERVER["REQUEST_URI"], '?'), "/");

        $routeSignature = explode("/", $uri);
        $requestSignature = explode("/", $requestUri);
        $sizeOfRouteSignature = sizeof($routeSignature);

        /*     if (sizeof($requestSignature) > sizeof($routeSignature)) {
                 $signatureMatch = false;
             } else {*/

        for ($i = 0; $i < $sizeOfRouteSignature; $i++) {
            if ($routeSignature[$i][0] == '{' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == '}') {
                if (!empty($requestSignature[$i])) {
                    $r[trim($routeSignature[$i], "{}")] = trim($requestSignature[$i], "{}");
                } else {
                    $signatureMatch = false;
                }
            } else if ($routeSignature[$i][0] == '[' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == ']') {
                if (!empty($requestSignature[$i])) {
                    $r[trim($routeSignature[$i], "[]")] = trim($requestSignature[$i], "[]");
                }
            } else {
                $e[] = $requestSignature[$i];
                $signatureMatch = $requestSignature[$i] == $routeSignature[$i];
            }
            if (!$signatureMatch) {
                break;
            }
        }
        //  }

        if ($signatureMatch) {

            self::$params = $r;

            if ($func) {
                $func($r);
            }

            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);
            $uri = implode($e, "/");
            $n = sizeof($e);
            $className = strtr($uri, '/', '\\');
            $class = "app\\{$className}";
            $action = Input::getMethod() . "_" . Input::getPath()->part($n + 1);
            if (class_exists($class)) {
                $controller = new $class();
                if (method_exists($controller, $action)) {
                    $response = $controller->$action($r);
                } else {
                    $action = Input::getMethod() . "_index";
                    if (method_exists($controller, $action)) {
                        $response = $controller->$action($r);
                    } else {
                        self::miss();
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
                header('Content-Disposition: attachment; filename="data.csv"');
                echo Response::passthru($response["csv"], "csv/plain");
            } else {
                if (!$silent) {
                    $response["_execution_time"] = round((Util::microtime_float() - $time_start), 3);
                    echo Response::toJson($response);
                }
            }
            exit();
        }
    }

    /**
     *
     */
    static public function miss(): void
    {
        header('HTTP/1.0 404 Not Found');
        echo "<h1>404 Not Found</h1>";
        exit();
    }

    /**
     * @param string $parameter
     * @return string|null
     */
    static public function getParam(string $parameter)
    {
        return isset(self::$params[$parameter]) ? self::$params[$parameter] : null;
    }


}