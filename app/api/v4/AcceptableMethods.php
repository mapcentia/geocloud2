<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\inc\Response;
use app\inc\Util;
use Attribute;
use ReflectionClass;

#[Attribute]
class AcceptableMethods
{
    public array $methodsAllowed;

    public function __construct(array $methods)
    {
        $this->methodsAllowed = $methods;
    }

    public function getAllowedMethods(): array
    {
        return $this->methodsAllowed;
    }

    public function throwException(): never
    {
        $response["success"] = false;
        $response["message"] = "Method not accepted";
        $response["code"] = 406;
        $response["errorCode"] = "METHOD_NOT_ACCEPTED";
        echo Response::toJson($response);
        exit();
    }

    public function options(): never
    {
        header("HTTP/1.0  204");
        exit();
    }

    public function setHeaders(): void
    {
        header_remove("Access-Control-Allow-Methods");
        header("Access-Control-Allow-Methods: " . implode(", ", $this->getAllowedMethods()));
    }
}