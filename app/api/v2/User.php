<?php
/**
 * @OA\Info(title="Geocloud API", version="0.1")
 */

/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Route;
use \app\inc\Input;
use \app\inc\Controller;
use \app\models\User as UserModel;
use \app\models\Database;
use \app\inc\Session;

/**
 * Class User
 * @package app\api\v2
 */
class User extends Controller
{

    private $user;
    private $db;

    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->user = new UserModel(Session::isAuth() ? Session::getUser() : null);
    }

    /**
     * API section GET router
     */
    function get_index(): array
    {
        if (Session::isAuth()) {
            $action = Route::getParam("action");
            if (empty($action)) {
                return $this->get_default();
            } else if ($action === "subusers") {
                return $this->get_subusers();
            } else {
                return [
                    'success' => false,
                    'code' => 404
                ];
            }
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }

    /**
     * @return array
     * 
     * @OA\Post(
     *   path="/v2/user",
     *   tags={"user"},
     *   summary="Creates user",
     *   @OA\RequestBody(
     *     description="User data",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string"),
     *         @OA\Property(property="email",type="string"),
     *         @OA\Property(property="password",type="string"),
     *         @OA\Property(property="subuser",type="boolean"),
     *         @OA\Property(property="usergroup",type="string"),
     *         @OA\Property(property="zone",type="string")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function post_index(): array
    {
        $data = json_decode(Input::getBody(), true) ? : [];
        if ((empty($data['subuser']) || filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN) === false)
            || Session::isAuth() && filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN)) {
            $data['subuser'] = filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN);

            $response = $this->user->createUser($data);
            if ($data['subuser']) {
                Database::setDb(Session::getUser());
                $database = new Database();
                $database->createSchema($response['data']['screenname']);
            }

            return $response;
        } else {
            return [
                'success' => false,
                'message' => "Sub users should be created only by authenticated clients and 'subuser' parameter set to 'true'",
                'code' => 400
            ];
        }
    }

    /**
     * @return array
     * 
     * @OA\Get(
     *   path="/v2/user/{userId}",
     *   tags={"user"},
     *   summary="Returns extended information about user (meta, schemas, groups). User data is available only for the actual user and his superuser",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function get_default(): array
    {
        $requestedUser = Route::getParam("userId");
        $currentUser = Session::getUser();
        if ($currentUser === $requestedUser) {
            return $this->user->getData();
        } else {
            $userModelLocal = new UserModel($requestedUser);
            $user = $userModelLocal->getData();
            if ($user['data']['parentdb'] === $currentUser) {
                return $user;
            } else {
                if (isset($user['code'])) {
                    return $user;
                } else {
                    return [
                        'success' => false,
                        'message' => 'Requested user is not the subuser of the currently authenticated user',
                        'code' => 403
                    ];
                }

            }
        }
    }

    /**
     * @return array
     * 
     * @OA\Put(
     *   path="/v2/user/{userId}",
     *   tags={"user"},
     *   summary="Updates user information. User can only update himself or its subuser.",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *     description="User data",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="currentPassword",type="string"),
     *         @OA\Property(property="password",type="string"),
     *         @OA\Property(property="email",type="string"),
     *         @OA\Property(property="usergroup",type="string"),
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function put_index(): array
    {
        if (Session::isAuth()) {
            $data = json_decode(Input::getBody(), true) ? : [];

            $requestedUserId = Route::getParam("userId");
            $currentUserId = Session::getUser();

            $data['user'] = $requestedUserId;

            if ($currentUserId === $requestedUserId) {
                if (empty($data['password'])) {
                    return $this->user->updateUser($data);
                } else {
                    if (empty($data['currentPassword'])) {
                        return [
                            'success' => false,
                            'message' => 'Current password has to be provided in order to set the new one',
                            'errorCode' => 'EMPTY_CURRENT_PASSWORD',
                            'code' => 403
                        ];
                    } else if ($this->user->hasPassword($currentUserId, $data['currentPassword'])) {
                        return $this->user->updateUser($data);
                    } else {
                        return [
                            'success' => false,
                            'message' => 'Provided current password is not correct',
                            'errorCode' => 'INVALID_CURRENT_PASSWORD',
                            'code' => 403
                        ];
                    }
                }
            } else {
                $userModelLocal = new UserModel($requestedUserId);
                $user = $userModelLocal->getData();
                if ($user['data']['parentdb'] === $currentUserId) {
                    return $userModelLocal->updateUser($data);
                } else {
                    return [
                        'success' => false,
                        'message' => 'Requested user is not the subuser of the currently authenticated user',
                        'code' => 403
                    ];
                }
            }
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }

    /**
     * @return array
     * 
     * @OA\Delete(
     *   path="/v2/user/{userId}",
     *   tags={"user"},
     *   summary="Deletes user. User can only delete himself or be deleted by its superuser.",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function delete_index(): array
    {
        if (Session::isAuth()) {
            $requestedUserId = Route::getParam("userId");
            $currentUserId = Session::getUser();
            if ($currentUserId === $requestedUserId) {
                return $this->user->deleteUser($currentUserId);
            } else {
                $userModelLocal = new UserModel($requestedUserId);
                $user = $userModelLocal->getData();
                if ($user['data']['parentdb'] === $currentUserId) {
                    return $this->user->deleteUser($requestedUserId);
                } else {
                    return [
                        'success' => false,
                        'message' => 'Requested user is not the subuser of the currently authenticated user',
                        'code' => 403
                    ];
                }
            }
        } else {
            return [
                'success' => false,
                'code' => 401
            ];
        }
    }

    /**
     * @return array
     * 
     * @OA\Get(
     *   path="/v2/user/{userId}/subusers",
     *   tags={"user"},
     *   summary="Returns subusers",
     *   @OA\Parameter(
     *     name="userId",
     *     in="path",
     *     required=true,
     *     description="User identifier",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    function get_subusers(): array
    {
        if (isset($_SESSION['subuser']) && $_SESSION['subuser'] === false) {
            $currentUser = Session::getUser();
            return $this->user->getSubusers($currentUser);
        } else {
            $response['success'] = false;
            $response['message'] = '';
            $response['code'] = 403;
            return $response;
        }
    }
}