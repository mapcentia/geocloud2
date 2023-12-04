<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\inc\Jwt;
use app\inc\Route;
use app\inc\Input;
use app\models\User as UserModel;
use Exception;


/**
 * Class User
 * @package app\api\v2
 */
class User implements ApiInterface
{
    /**
     * @var UserModel
     */
    private UserModel $user;
    private mixed $jwt;

    /**
     * User constructor.
     * @throws Exception
     */
    function __construct()
    {
        $this->jwt = Jwt::validate()["data"];
        $this->user = new UserModel($this->jwt["uid"], $this->jwt["database"]);
    }

    private static function convertUserObject(array $user): array
    {
        return [
            "userid" => $user["userid"] ?? $user["screenname"],
            "parentdb" => $user["parentdb"],
            "usergroup" => $user["usergroup"],
            "email" => $user["email"],
            "properties" => $user["properties"],
        ];
    }

    /**
     * @return array
     *
     * @OA\Post(
     *   path="/api/v4/user",
     *   tags={"User"},
     *   summary="Creates user",
     *   security={{"bearerAuth":{}}},
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
     *         @OA\Property(property="properties",type="object",example={"org":"Ajax Inc."})
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     * @throws Exception
     */
    function post_index(): array
    {
        if (!$this->jwt["superUser"]) {
            throw new Exception("Sub-users are not allowed to create other sub users");
        }
        $data = json_decode(Input::getBody(), true) ?: [];
        $res = $this->user->createUser($data);
        return [
            "success" => true,
            "message" => "User created",
            "data" => self::convertUserObject($res["data"]),
        ];
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v4/user/{userId}",
     *   tags={"User"},
     *   summary="Returns extended information about user (meta, schemas, groups). User data is available only for the actual user and his superuser",
     *   security={{"bearerAuth":{}}},
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
     * @throws Exception
     */
    function get_index(): array
    {

        if (!$this->jwt["superUser"] && $this->jwt["uid"] != Route::getParam("userId")) {
            throw new Exception("Sub-users are not allowed to get information about other sub users");
        }
        $requestedUser = Route::getParam("userId");
        $userModelLocal = new UserModel($requestedUser, $this->jwt["database"]);
        return [
            "success" => true,
            "data" => self::convertUserObject($userModelLocal->getData()["data"]),
            ];
    }

    /**
     * @return array
     *
     * @OA\Put(
     *   path="/api/v4/user/{userId}",
     *   tags={"User"},
     *   summary="Updates user information. User can only update himself or its subuser.",
     *   security={{"bearerAuth":{}}},
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
     * @throws Exception
     */
    function put_index(): array
    {
        if (!$this->jwt["superUser"] && $this->jwt["uid"] != Route::getParam("userId")) {
            throw new Exception("Sub-users are not allowed to update other sub users");
        }
        $data = json_decode(Input::getBody(), true) ?: [];
        $requestedUserId = Route::getParam("userId");
        $currentUserId = $this->jwt["uid"];
        $dataBase = $this->jwt["database"];
        $data["user"] = $requestedUserId;
        if ($currentUserId == $requestedUserId) {
            $res = $this->user->updateUser($data);
        } else {
            $userModelLocal = new UserModel($requestedUserId, $dataBase);
            $user = $userModelLocal->getData();
            if ($user["data"]["parentdb"] == $currentUserId) {
                $data["parentdb"] = $user["data"]["parentdb"];
                $res = $userModelLocal->updateUser($data);
            } else {
                throw new Exception("Requested user is not the subuser of the currently authenticated user");
            }
        }
        $res["data"] = self::convertUserObject($res["data"]);
        return $res;
    }

    /**
     * @return array
     *
     * @OA\Delete(
     *   path="/api/v4/user/{userId}",
     *   tags={"User"},
     *   summary="Deletes user. User can only delete himself or be deleted by its superuser.",
     *   security={{"bearerAuth":{}}},
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
     * @throws Exception
     */
    function delete_index(): array
    {
        if (!$this->jwt["superUser"] && $this->jwt["uid"] != Route::getParam("userId")) {
            throw new Exception("Sub-users are not allowed to delete other sub users");
        }
        $requestedUser = Route::getParam("userId");
        $res = $this->user->deleteUser($requestedUser);
        if ($res["data"] == 0) {
            throw New Exception("User not found");
        } else {
            return $res;
        }
    }

    /**
     * @return array
     *
     * @OA\Get(
     *   path="/api/v4/user/all",
     *   tags={"User"},
     *   summary="Returns subusers",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     * @throws Exception
     */
    function get_all(): array
    {
        if (!$this->jwt["superUser"]) {
            throw new Exception("Sub-users are not allowed to list all sub users");
        }
        $currentUserId = $this->jwt["uid"];
        return $this->user->getSubusers($currentUserId);
    }
}