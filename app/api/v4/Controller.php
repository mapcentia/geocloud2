<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Response;
use Attribute;

#[Attribute]
class Controller
{

    public function __construct(private readonly string $route, private readonly Scope $scope)
    {
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getScope(): Scope
    {
        return $this->scope;

    }

    public function checkScope(array $jwt): void
    {
        if (Input::getMethod() === 'options') {
            return;
        } elseif ($jwt["data"]["superUser"]) {
            return;
        } elseif ($this->scope === Scope::SUB_USER_ALLOWED) {
            return;
        }
        throw new GC2Exception(Response::SUPER_USER_ONLY['message']);
    }
}