<?php
/**
 * @author     Aleksandr Shumilov <shumsan1011@gmail.com>
 * @copyright  2013-2020 MapCentia ApS
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
    /**
     * @var UserModel
     */
    private $user;

    /**
     * User constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->user = new UserModel(Session::isAuth() ? Session::getUser() : null, Session::getDatabase() ? Session::getDatabase() : null);
    }

    /**
     * @return array<mixed>
     */
    function get_index(): array
    {
        $action = Route::getParam("action");
        if (Session::isAuth()) {
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
     * @return array<mixed>
     *
     * @OA\Post(
     *   path="/api/v2/user",
     *   tags={"User"},
     *   summary="Creates user",
     *   security={{"cookieAuth":{}}},
     *   @OA\RequestBody(
     *     description="User data",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         required={"name","email","password"},
     *         @OA\Property(property="name",type="string",example="user"),
     *         @OA\Property(property="email",type="string",example="user@example.com"),
     *         @OA\Property(property="password",type="string",example="1234Luggage"),
     *         @OA\Property(property="subuser",type="boolean",example=true),
     *         @OA\Property(property="usergroup",type="string",example="My group"),
     *         @OA\Property(property="parentdb",type="string",example="mydatabase"),
     *         @OA\Property(property="properties",type="object",example={"org":"Ajax Inc."})
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
        $data = json_decode(Input::getBody(), true) ?: [];
        if ((empty($data['subuser']) || filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN) === false)
            || Session::isAuth() && filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN)) {
            $data['subuser'] = filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN) || Session::isAuth();
            if (!empty($data['parentdb'])) {
                return [
                    'success' => false,
                    'message' => "'parentdb' can't be set while client is authenticated or 'subuser' is set to false",
                    'code' => 400
                ];
            }
            $response = $this->user->createUser($data);
            if (Session::isAuth()) {
                Database::setDb(Session::getUser());
                $database = new Database();
                $database->createSchema($response['data']['screenname']);
            }
            return $response;
        } elseif (!empty($data['parentdb']) && filter_var($data['subuser'], FILTER_VALIDATE_BOOLEAN) === true && !Session::isAuth()) {
            if (empty(\app\conf\App::$param["allowUnauthenticatedClientsToCreateSubUsers"])) {
                return [
                    'success' => false,
                    'message' => "Unauthenticated clients are not allowed to create sub users.",
                    'code' => 400
                ];
            }

            $response = $this->user->createUser($data);
            return $response;
        } else {
            return [
                'success' => false,
                'message' => "Sub users should be created only by authenticated clients and 'subuser' parameter set to 'true'. Or by unauthenticated clients and 'parentdb' set to an existing super user'",
                'code' => 400
            ];
        }
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v2/user/{userId}",
     *   tags={"User"},
     *   summary="Returns extended information about user (meta, schemas, groups). User data is available only for the actual user and his superuser",
     *   security={{"cookieAuth":{}}},
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
        if (Session::getUser() === $requestedUser) {
            return $this->user->getData();
        } else {
            $userModelLocal = new UserModel($requestedUser, Session::getDatabase());
            $user = $userModelLocal->getData();
            if ($user['data']['parentdb'] === Session::getUser()) {
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
     * @return array<mixed>
     *
     * @OA\Put(
     *   path="/api/v2/user/{userId}",
     *   tags={"User"},
     *   summary="Updates user information. User can only update himself or its subuser.",
     *   security={{"cookieAuth":{}}},
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
     *         @OA\Property(property="currentPassword",type="string",example="1234Luggage"),
     *         @OA\Property(property="password",type="string",example="1234Luggage"),
     *         @OA\Property(property="email",type="string",example="user@example.com"),
     *         @OA\Property(property="usergroup",type="string",example="My group"),
     *         @OA\Property(property="properties",type="object",example={"org":"Ajax Inc."})
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
            $data = json_decode(Input::getBody(), true) ?: [];

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
                        $userModelLocal = new UserModel($requestedUserId, Session::getDatabase());
                        $user = $userModelLocal->getData();
                        $data["parentdb"] = $user['data']['parentdb'];
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
                $userModelLocal = new UserModel($requestedUserId, Session::getDatabase());
                $user = $userModelLocal->getData();
                if ($user['data']['parentdb'] === $currentUserId) {
                    $data["parentdb"] = $user['data']['parentdb'];
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
     * @return array<mixed>
     *
     * @OA\Delete(
     *   path="/api/v2/user/{userId}",
     *   tags={"User"},
     *   summary="Deletes user. User can only delete himself or be deleted by its superuser.",
     *   security={{"cookieAuth":{}}},
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
                $userModelLocal = new UserModel($requestedUserId, Session::getDatabase());
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
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v2/user/{userId}/subusers",
     *   tags={"User"},
     *   summary="Returns subusers",
     *   security={{"cookieAuth":{}}},
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
        if (isset($_SESSION["subuser"]) && $_SESSION["subuser"] === false) {
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