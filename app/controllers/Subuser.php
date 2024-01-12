<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Session;
use app\inc\Route;
use app\models\User;

/**
 * Class Subuser
 * @package app\controllers
 */
class Subuser extends Controller
{
    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     */
    function get_index(): array
    {
        $user = new User(Session::getUser());
        return $user->getData();
    }

    /**
     * @return array
     */
    function post_index(): array
    {
        $user = new User(Session::getUser());
        if (!Session::isAuth()) {
            $response['success'] = false;
            $response['message'] = "User unauthorized";
            $response['code'] = 401;
            return $response;
        }
        return $user->createUser(json_decode(Input::getBody(), true) ?: []);
    }

    /**
     * @return array
     */
    function put_index(): array
    {
        $user = new User(Session::getUser());
        if (!Session::isAuth()) {
            $response['success'] = false;
            $response['message'] = "User unauthorized";
            $response['code'] = 401;
            return $response;
        }
        return $user->updateUser(json_decode(Input::getBody(), true) ?: []);
    }

    /**
     * @return array
     */
    function delete_index(): array
    {
        $user = new User(Session::getUser());
        if (!Session::isAuth()) {
            $response['success'] = false;
            $response['message'] = "User unauthorized";
            $response['code'] = 401;
            return $response;
        }
        return $user->deleteUser(Route::getParam("user"));
    }
}