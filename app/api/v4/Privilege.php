<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Jwt;
use app\inc\Input;
use app\inc\Route2;
use app\models\User as UserModel;
use Exception;


/**
 * Class User
 * @package app\api\v2
 */
class Privilege extends AbstractApi
{

    #[\Override] public function validate(): void
    {
        // TODO: Implement validate() method.
    }

    #[\Override] public function get_index(): array
    {
        // TODO: Implement get_index() method.
    }

    #[\Override] public function post_index(): array
    {
        // TODO: Implement post_index() method.
    }

    #[\Override] public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    #[\Override] public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }
}
