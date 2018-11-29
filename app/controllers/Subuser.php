<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Input;
use app\inc\Route;
use app\inc\Session;

class Subuser extends \app\inc\Controller
{
    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return mixed
     */
    function get_index()
    {
        $user = new \app\models\User(Session::getUser());
        return $user->getData();
    }

    function post_index()
    {
        $user = new \app\models\User(Session::getUser());
        if (!Session::isAuth()) {
            $response['success'] = false;
            $response['message'] = "User unauthorized";
            $response['code'] = 401;
            return $response;
        }
        return $user->createUser(json_decode(Input::getBody(), true) ?: []);
    }

    function put_index()
    {
        $db = Route::getParam("user");
        $user = new \app\models\User($db);
        if (!Session::isAuth()) {
            $response['success'] = false;
            $response['message'] = "User unauthorized";
            $response['code'] = 401;
            return $response;
        }
        return $user->createUser(json_decode(Input::getBody(), true) ?: []);
    }
}