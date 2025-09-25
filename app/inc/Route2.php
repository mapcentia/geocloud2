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
    public bool $isMatched = false;
    public ?array $jwt;

    /**
     * @param string $uri
     * @param AbstractApi $controller
     * @param Closure|null $func
     * @throws GC2Exception
     */
    public function add(string $uri, ApiInterface $controller, ?Closure $func = null): void
    {
        if ($this->isMatched) {
            return;
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
                        $action = 'index';
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
            $this->isMatched = true;
            $this->params = $r;
            if ($func) {
                $func($r);
            }
            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);

            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            if (!method_exists($controller, $action)) {
                if ($action != "index") {
                    $this->isMatched = false;
                    return;
                }
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
                            return;
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
            $data = $response->getData();
            $status = $response->getStatus();
            header("HTTP/1.0 $status " . Util::httpCodeText($status));

            // Ensure no Content-Type (or body) is sent for 204/303
            if ($status == 204) {
                header_remove('Content-Type');
                header_remove('Content-Length');
                return;
            }
            header('Content-type: application/json; charset=utf-8');
            if ($data) {
                if (!array_is_list($data)) {
                    $data["_execution_time"] = round((Util::microtime_float() - $time_start), 3);
                }
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            }
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

    public function miss(): void
    {
        if (!$this->isMatched) {
            header('HTTP/1.0 404 Not Found');
            echo "<h1>404 Not Found</h1>";
        }
    }
}