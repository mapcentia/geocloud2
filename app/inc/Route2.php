<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableMethods;
use app\api\v4\ApiInterface;
use app\api\v4\Schema;
use app\exceptions\GC2Exception;
use Closure;
use ReflectionClass;
use ReflectionException;

/**
 * Class Route2
 *
 * This class is used to handle routing in the application.
 */
class Route2
{
    /**
     * @var array
     */
    static array $params;

    /**
     * @param string $uri
     * @param AbstractApi $controller
     * @param Closure|null $func
     * @throws GC2Exception
     */
    static public function add(string $uri, ApiInterface $controller, Closure $func = null): void
    {
        $signatureMatch = true;
        $e = [];
        $r = [];
        $action = "index";
        $time_start = Util::microtime_float();
        $uri = trim($uri, "/");
        $requestUri = trim(strtok($_SERVER["REQUEST_URI"], '?'), "/");

        $routeSignature = explode("/", $uri);
        $requestSignature = explode("/", $requestUri);
        $sizeOfRouteSignature = sizeof($routeSignature);

        if (sizeof($requestSignature) > sizeof($routeSignature)) {
            $signatureMatch = false;
        } else {
            for ($i = 0; $i < $sizeOfRouteSignature; $i++) {
                if ($routeSignature[$i][0] == '{' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == '}') {
                    if (isset($requestSignature[$i])) {
                        $r[trim($routeSignature[$i], "{}")] = trim($requestSignature[$i], "{}");
                    } else {
                        $signatureMatch = false;
                    }
                } else if ($routeSignature[$i][0] == '[' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == ']') {
                    if (isset($requestSignature[$i])) {
                        $r[trim($routeSignature[$i], "[]")] = trim($requestSignature[$i], "[]");
                    }
                } else if ($routeSignature[$i][0] == '(' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == ')') {
                    if (isset($requestSignature[$i])) {
                        $action = Input::getMethod() . "_" . trim($requestSignature[$i], "()");
                    } else {
                        $signatureMatch = false;
                    }
                } else if (isset($requestSignature[$i]) && $requestSignature[$i] == $routeSignature[$i]) {
                    $e[] = $requestSignature[$i];
                } else if (isset($routeSignature[$i + 1]) && $requestSignature[$i + 1][0] != "[") {
                    $signatureMatch = $requestSignature[$i] == $routeSignature[$i];
                } else {
                    $signatureMatch = false;
                }
                if (!$signatureMatch) {
                    break;
                }
            }
        }

        if ($signatureMatch) {
            self::$params = $r;
            if ($func) {
                $func($r);
            }
            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);

            $reflectionClass = new ReflectionClass($controller);
            if (!method_exists($controller, $action)) {
                $method = Input::getMethod();
                $action = $method . "_index";
                $attributes = $reflectionClass->getAttributes(AcceptableMethods::class);
                foreach ($attributes as $attribute) {
                    $listener = $attribute->newInstance();
                    $listener->setHeaders();
                    if ($listener::class == AcceptableMethods::class) {
                        $allowedMethods = array_map('strtolower', $listener->getAllowedMethods());
                        if (!in_array($method, $allowedMethods)) {
                            $listener->throwException();
                        }
                        if ($method == "options") {
                            $listener->options();
                        }
                    }
                }
            }
            $controller->validate();
            $response = $controller->$action($r);
            $code = "200";
            if (isset($response["code"])) {
                $code = $response["code"];
                unset($response["code"]);
            }
            header("HTTP/1.0 $code " . Util::httpCodeText($code));
            $response["_execution_time"] = round((Util::microtime_float() - $time_start), 3);
            header('Content-type: application/json; charset=utf-8');
            echo json_encode($response, JSON_UNESCAPED_UNICODE);
            exit();
        }
    }

    /**
     * @return never
     */
    static public function miss(): never
    {
        header('HTTP/1.0 404 Not Found');
        echo "<h1>404 Not Found</h1>";
        exit();
    }

    /**
     * @param string $parameter
     * @return string|null
     */
    static public function getParam(string $parameter): ?string
    {
        if (isset(self::$params[$parameter])) {
            return urldecode(self::$params[$parameter]);
        } else {
            return null;
        }
    }
}