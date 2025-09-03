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
class Scope
{

    public function __construct(private array $scope)
    {
    }

    public function check(array $jwt): void
    {
        if (Input::getMethod() === 'options') {
            return;
        }
        elseif ($jwt["data"]["superUser"]) {
            return;
        } elseif (count($this->scope) === 0 || in_array('subuser', $this->scope)) {
            return;
        }
        throw new GC2Exception(Response::SUPER_USER_ONLY['message']);
    }
}