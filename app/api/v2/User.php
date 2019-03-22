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
use \app\inc\Session;
use \app\models\User as UserModel;

/**
 * Class User
 * @package app\api\v2
 */
class User extends Controller
{

    private $user;

    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->user = new UserModel();
    }

    /**
     * @return array
     */
    function post_index(): array
    {
        $data = json_decode(Input::getBody(), true) ? : [];
        if ((empty($data['subUser']) || filter_var($data['subUser'], FILTER_VALIDATE_BOOLEAN) === false)
            || is_null(Session::isAuth()) === false && filter_var($data['subUser'], FILTER_VALIDATE_BOOLEAN)) {
            $data['subuser'] = filter_var($data['subUser'], FILTER_VALIDATE_BOOLEAN);
            return $this->user->createUser($data);
        } else {
            return [
                'success' => false,
                'message' => "Sub users should be created only by authenticated clients and 'subuser' parameter set to 'true'",
                'code' => 400
            ];
        }
    }
}