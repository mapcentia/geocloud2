<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\Controller;
use app\api\v4\ApiInterface;
use app\api\v4\Responses\StreamedResponse;
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
    public ?string $action;

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
        $time_start = Util::microtime_float();
        $requestUri = trim(strtok($_SERVER["REQUEST_URI"], '?'), "/");

        $match = self::matchSignature($uri, $requestUri);
        $signatureMatch = $match !== null;
        $e = $match['literals'] ?? [];
        $r = $match['params'] ?? [];
        $action = $match['action'] ?? "index";

        if ($signatureMatch) {
            $this->isMatched = true;
            $this->params = $r;
            $this->action = $action;
            if ($func) {
                $func($r);
            }
            $e[count($e) - 1] = ucfirst($e[count($e) - 1]);

            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);
            $method = Input::getMethod();
            $action = $method . "_" . $action;
            if (!method_exists($controller, $action)) {
                $this->isMatched = false;
                return;
            }
            $contentType = Input::getContentType() ? trim(explode(';', Input::getContentType())[0]) : "application/json";
            $accepts = Input::getAccept() ? array_map(fn($str) => trim(explode(';', $str)[0]), explode(',', Input::getAccept())) : ["*/*"];
            // Check AcceptableMethods
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
            // Check scope
            $attributes = $reflectionClass->getAttributes(Controller::class);
            foreach ($attributes as $attribute) {
                $listener = $attribute->newInstance();
                if ($listener::class == Controller::class) {
                    if (isset($this->jwt)) {
                        $listener->checkScope($this->jwt);
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
            try {
                $controller->validate();
                $response = $controller->$action($r);
            } finally {
                // Roll back any transaction the controller left open on a
                // cached PDO. Safe no-op when commit() already closed it.
                Model::rollbackAllOpenTransactions();
            }
            // Streaming branch: bypass JSON-encoding, let the callback
            // write directly to php://output.
            if ($response instanceof StreamedResponse) {
                header('HTTP/1.0 ' . $response->getStatus() . ' ' . Util::httpCodeText($response->getStatus()));
                header('Content-Type: ' . $response->contentType);
                ($response->callback)();
                return;
            }
            $data = $response->getData();
            $status = $response->getStatus();
            header("HTTP/1.0 $status " . Util::httpCodeText($status));

            if ($status == 302) {
                return;
            }
            // Ensure no Content-Type (or body) is sent for 204/303
            if ($status == 204) {
                header_remove('Content-Type');
                header_remove('Content-Length');
                return;
            }

            if ($data !== null) {
                if (getType($data) == "string") {
                    header('Content-type: text/plain; charset=utf-8');
                    echo $data;
                    return;
                }
                header('Content-type: application/json; charset=utf-8');
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
            }
        }
    }

    /**
     * Match a request URI against a route signature.
     *
     * Segment types: {name} required parameter, [name] optional parameter,
     * (name) action, anything else a literal. The request may stop short of
     * the route as long as every remaining route segment is optional or a
     * literal label immediately followed by an optional segment, e.g.
     * srs/[srs]/ts/[timeSlice] matches both .../srs/4326/ts/12.00.00 and
     * an URI ending right before /srs.
     *
     * @param string $uri route signature
     * @param string $requestUri request URI without query string
     * @return array{literals: array<string>, params: array<string,string>, action: string}|null null on miss
     */
    public static function matchSignature(string $uri, string $requestUri): ?array
    {
        $uri = trim($uri, "/");
        $requestUri = trim($requestUri, "/");
        $e = [];
        $r = [];
        $action = "index";

        $routeSignature = explode("/", $uri);
        $requestSignature = explode("/", $requestUri);
        $sizeOfRouteSignature = sizeof($routeSignature);

        if (sizeof($requestSignature) > $sizeOfRouteSignature) {
            return null;
        }
        for ($i = 0; $i < $sizeOfRouteSignature; $i++) {
            if ($routeSignature[$i][0] == '{' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == '}') {
                if (isset($requestSignature[$i])) {
                    $r[trim($routeSignature[$i], "{}")] = trim($requestSignature[$i], "{}");
                } else {
                    return null;
                }
            } else if ($routeSignature[$i][0] == '[' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == ']') {
                if (isset($requestSignature[$i])) {
                    $r[trim($routeSignature[$i], "[]")] = trim($requestSignature[$i], "[]");
                }
            } else if ($routeSignature[$i][0] == '(' && $routeSignature[$i][strlen($routeSignature[$i]) - 1] == ')') {
                if (isset($requestSignature[$i])) {
                    $action = trim($requestSignature[$i], "()");
                }
            } else if (isset($requestSignature[$i]) && $requestSignature[$i] == $routeSignature[$i]) {
                $e[] = $requestSignature[$i];
            } else if (!isset($requestSignature[$i]) && self::restIsOptional($routeSignature, $i)) {
                break;
            } else {
                return null;
            }
        }
        // Specificity: how many trailing route segments the request did not fill
        // (omitted optionals / optional tail). 0 means an exact structural fit.
        // A more specific (lower) match wins over a parent/child optional-tail match.
        return [
            'literals' => $e,
            'params' => $r,
            'action' => $action,
            'omitted' => $sizeOfRouteSignature - sizeof($requestSignature),
        ];
    }

    /**
     * Orders route candidates most-specific first for a request: the fewest omitted
     * trailing segments wins, ties keep the given (scan) order. Non-matching routes
     * sink to the end (they are no-ops when dispatched). Keys (controller classes)
     * are preserved.
     *
     * @param array<string, mixed> $routes  map of controller-class => route object (with getRoute())
     * @return array<string, mixed>
     */
    public static function orderBySpecificity(array $routes, string $requestUri): array
    {
        $indexed = [];
        $seq = 0;
        foreach ($routes as $class => $route) {
            $match = self::matchSignature($route->getRoute(), $requestUri);
            $indexed[] = [
                'class' => $class,
                'route' => $route,
                'omitted' => $match['omitted'] ?? PHP_INT_MAX,
                'seq' => $seq++,
            ];
        }
        usort($indexed, fn($a, $b) => [$a['omitted'], $a['seq']] <=> [$b['omitted'], $b['seq']]);
        $ordered = [];
        foreach ($indexed as $entry) {
            $ordered[$entry['class']] = $entry['route'];
        }
        return $ordered;
    }

    /**
     * True if every route segment from $from on can be omitted: optional
     * [name]/(name) segments or a literal label right before an optional one.
     */
    private static function restIsOptional(array $routeSignature, int $from): bool
    {
        $size = sizeof($routeSignature);
        for ($i = $from; $i < $size; $i++) {
            $seg = $routeSignature[$i];
            $last = $seg[strlen($seg) - 1];
            if (($seg[0] == '[' && $last == ']') || ($seg[0] == '(' && $last == ')')) {
                continue;
            }
            $next = $routeSignature[$i + 1] ?? null;
            if ($next !== null && $next[0] == '[' && $next[strlen($next) - 1] == ']') {
                continue;
            }
            return false;
        }
        return true;
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