<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use Attribute;

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

    /**
     * @throws GC2Exception
     */
    public function throwException(): never
    {
        throw new GC2Exception("Method not acceptable", 406, null, "NOT_ACCEPTABLE");
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