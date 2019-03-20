<?php
/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Input;
use \app\inc\Controller;
use \app\models\SuperUser;

/**
 * Class User
 * @package app\api\v2
 */
class User extends Controller
{

    private $superUser;

    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->superUser = new SuperUser();
    }

    /**
     * @return array
     */
    function post_index(): array
    {
        $data = json_decode(Input::getBody(), true) ? : [];
        return $this->superUser->createUser($data);
    }

}