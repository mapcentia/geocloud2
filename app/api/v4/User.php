<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\models\Database;
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
#[AcceptableMethods(['POST', 'PUT', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
class User extends AbstractApi
{
    /**
     * @var UserModel
     */
    private UserModel $user;

    /**
     * User constructor.
     * @throws Exception
     */
    function __construct()
    {

    }

    private static function convertUserObject(array $user): array
    {
        return [
            "name" => $user['screenName'] ?? $user['screenname'] ?? $user['userid'],
            "user_group" => $user["usergroup"],
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
    public function post_index(): array
    {
        if (!$this->jwt["superUser"]) {
            throw new Exception("Sub-users are not allowed to create other sub users");
        }
        $list = [];
        $model = new UserModel();
        $data = json_decode(Input::getBody(), true) ?: [];
        $model->connect();
        $model->begin();
        if (!isset($data['users'])) {
            $data['users'] = [$data];
        }
        foreach ($data['users'] as $user) {
            $user['parentdb'] = $this->jwt['database'];
            // Load pre extensions and run processAddUser
            $this->runExtension('processAddUser', $model);
            try {
                (new Database())->createSchema($user['name']);
            } catch (Exception) {
            }
            $list[] = self::convertUserObject($model->createUser($user)['data'])['name'];
        }
        $model->commit();
        header("Location: /api/v4/users/" . implode(",", $list));
        return ["code" => 201];
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
    public function get_index(): array
    {
        $r = [];
        $requestedUser = Route2::getParam("user");
        if (!$requestedUser) {
            return $this->getAll();
        }
        $users = explode(',', $requestedUser);
        foreach ($users as $user) {
            if (!$this->jwt["superUser"] && $this->jwt["uid"] != $user) {
                throw new Exception("Sub-users are not allowed to get information about other sub users");
            }
            $userModelLocal = new UserModel($user, $this->jwt["database"]);
            $r[] = self::convertUserObject($userModelLocal->getData()["data"]);
        }
        if (count($r) > 1) {
            return ["users" => $r];
        } else {
            return $r[0];
        }
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
    public function put_index(): array
    {
        $requestedUsers = explode(',', Route2::getParam("user"));

        $data = json_decode(Input::getBody(), true) ?: [];
        $currentUserId = $this->jwt["uid"];
        $dataBase = $this->jwt["database"];

        foreach ($requestedUsers as $requestedUserId) {
            if (!$this->jwt["superUser"] && $this->jwt["uid"] != $requestedUserId) {
                throw new Exception("Sub-users are not allowed to update other sub users");
            }
            $data["user"] = $requestedUserId;
            $data["usergroup"] = $data["user_group"];
            if ($currentUserId == $requestedUserId) {
                if (!$this->jwt['superUser']) {
                    $data['parentdb'] = $this->jwt['database'];
                }
                $model = new UserModel();
                $model->connect();
                $model->begin();
                $model->updateUser($data);
                $model->commit();

            } else {
                $model = new UserModel($requestedUserId, $dataBase);
                $model->connect();
                $model->begin();
                $user = $model->getData();
                if ($user["data"]["parentdb"] == $currentUserId) {
                    $data["parentdb"] = $user["data"]["parentdb"];
                    $model->updateUser($data);
                } else {
                    throw new Exception("Requested user is not the subuser of the currently authenticated user");
                }
                $model->commit();
            }
        }
        header("Location: /api/v4/users/" . implode(",", $requestedUsers));
        return ["code" => "303"];
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
    public function delete_index(): array
    {
        if (!$this->jwt["superUser"]) {
            throw new Exception("Sub-users are not allowed to delete sub users");
        }
        $requestedUsers = explode(',', Route2::getParam("user"));
        $model = new UserModel($this->jwt['uid']);

        $model->connect();
        $model->begin();
        foreach ($requestedUsers as $requestedUser) {
            $model->deleteUser($requestedUser);

        }
        $model->commit();
        return ["code" => "204"];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getAll(): array
    {
        $currentUserId = $this->jwt["database"];
        $usersData = (new UserModel())->getSubusers($currentUserId)['data'];
        return ['users' => array_map([$this, 'convertUserObject'], $usersData)];
    }


    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $this->jwt = Jwt::validate()["data"];
        $user = Route2::getParam("user");
        // Put and delete on collection is not allowed
        if (empty($user) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried POST with resource
        if (Input::getMethod() == 'post' && $user) {
            $this->postWithResource();
        }
    }
}