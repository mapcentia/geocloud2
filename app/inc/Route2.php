<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\ApiInterface;
use app\exceptions\GC2Exception;
use Closure;
use ReflectionClass;
use ReflectionMethod;

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
    public array $params;

    /**
     * @param string $uri
     * @param AbstractApi $controller
     * @param Closure|null $func
     * @throws GC2Exception
     */
    public function add(string $uri, ApiInterface $controller, ?Closure $func = null): void
    {
        if (headers_sent()) {
            goto end;
        }
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
                } else if (isset($routeSignature[$i + 1]) && isset($requestSignature[$i + 1][0]) && $requestSignature[$i + 1][0] != "[") {
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
            $this->params = $r;
            if ($func) {
                $func($r);
            }
            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);

            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            if (!method_exists($controller, $action)) {
                $method = Input::getMethod();
                $contentType = Input::getContentType() ? trim(explode(';', Input::getContentType())[0]) : "application/json";
                $accepts = Input::getAccept() ? array_map(fn($str) => trim(explode(';', $str)[0]), explode(',', Input::getAccept())) : ["*/*"];
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
                        if ($method == "options" || $method == "head") {
                            if ($method == "options") {
                                $m = Input::getAccessControlRequestMethod();
                                $m = $m ? strtolower($m) : null;
                                if (!in_array($m, $allowedMethods)) {
                                    $listener->throwException();
                                }
                            }
                            $listener->options();
                            goto end;
                        }
                    }
                }
                foreach ($reflectionMethods as $reflectionMethod) {
                    if ($reflectionMethod->getName() == $action) {
                        $attributes = $reflectionMethod->getAttributes(AcceptableContentTypes::class);
                        foreach ($attributes as $attribute) {
                            $listener = $attribute->newInstance();
                            if ($listener::class == AcceptableContentTypes::class) {
                                $allowedContentTypes = array_map('strtolower', $listener->getAllowedContentTypes());
                                if (!in_array($contentType, $allowedContentTypes)) {
                                    $listener->throwException($contentType);
                                }
                            }
                        }
                        $attributes = $reflectionMethod->getAttributes(AcceptableAccepts::class);
                        foreach ($attributes as $attribute) {
                            $listener = $attribute->newInstance();
                            if ($listener::class == AcceptableAccepts::class) {
                                $allowedAccepts = array_map('strtolower', $listener->getAllowedAccepts());
                                if (!in_array('*/*', $accepts) && count(array_intersect($accepts, $allowedAccepts)) == 0) {
                                    $listener->throwException($accepts);
                                }
                            }
                        }
                    }
                }
            }
            $controller->validate();
            $response = $controller->$action($r);
            $code = $response["code"] ?? '200';
            if (count($response) > 0) {
                unset($response["code"]);
                header("HTTP/1.0 $code " . Util::httpCodeText($code));
                header('Content-type: application/json; charset=utf-8');
                if (!array_is_list($response)) {
                    $response["_execution_time"] = round((Util::microtime_float() - $time_start), 3);
                }
                if (!in_array($code, ['204', '303'])) {
                    echo json_encode($response, JSON_UNESCAPED_UNICODE);
                } else {
                    header_remove('Content-type');
                }
            }
            end:
            flush();
        }
    }

    /**
     * @param string $parameter
     * @return string|null
     */
    public function getParam(string $parameter): ?string
    {
        if (isset($this->params[$parameter])) {
            return urldecode($this->params[$parameter]);
        } else {
            return null;
        }
    }
}