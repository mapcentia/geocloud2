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
use app\models\Setting;
use app\models\User as UserModel;
use Exception;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "User",
    required: [],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name",
            description: "Name of user.",
            type: "string",
            example: "joe",
        ),
        new OA\Property(
            property: "email",
            title: "E-mail",
            description: "Users e-mail.",
            type: "string",
            format: "email",
            example: "joe@example.com",
        ),
        new OA\Property(
            property: "password",
            title: "Password",
            description: "Users password. Min. 8 characters and at least one upper case letter and one number.",
            type: "password",
            example: "Abc123!",
        ),
        new OA\Property(
            property: "properties",
            title: "Properties",
            description: "An object, which can contain any properties and values.",
            type: "object",
            example: ["phone" => "555-1234567", "address" => "123 Main St", "city" => "New York"],
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'PATCH', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
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

    /**
     * @return array
     * @throws Exception
     */
    #[OA\Get(path: '/api/v4/user/{name}', operationId: 'getUser', description: "Get rules", tags: ['Users'])]
    #[OA\Parameter(name: 'name', description: 'User identifier', in: 'path', required: false, example: "joe")]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/User"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
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
        if (count($r) == 0) {
            throw new GC2Exception("No users found", 404, null, 'NO_USERS');
        } elseif (count($r) == 1) {
            return $r[0];
        } else {
            return ["users" => $r];
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    #[OA\Post(path: '/api/v4/users', operationId: 'postUser', description: "New user", tags: ['Users'])]
    #[OA\RequestBody(description: 'New user', required: true, content: new OA\JsonContent(ref: "#/components/schemas/User"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): array
    {
        if (!$this->jwt["superUser"]) {
            throw new Exception("Sub-users are not allowed to create other sub users");
        }
        $list = [];
        $model = new UserModel();
        $data = json_decode(Input::getBody(), true) ?: [];
        $data["usergroup"] = $data["user_group"] ?? null;
        $model->connect();
        $model->begin();
        if (!isset($data['users'])) {
            $data['users'] = [$data];
        }
        foreach ($data['users'] as $user) {
            $user['parentdb'] = $this->jwt['database'];
            // Load pre extensions and run processAddUser
            $this->runPreExtension('processAddUser', $model);
            try {
                (new Database())->createSchema($user['name']);
            } catch (Exception) {
            }
            $userName = self::convertUserObject($model->createUser($user)['data'])['name'];
            $list[] = $userName;
            (new Setting())->updateApiKeyForUser($userName, false);
        }
        $model->commit();
        header("Location: /api/v4/users/" . implode(",", $list));
        return ["code" => 201];
    }

    /**
     * @return array
     * @throws Exception
     */
    #[OA\Patch(path: '/api/v4/users/{name}', operationId: 'patchUser', description: "Update user", tags: ['Users'])]
    #[OA\Parameter(name: 'name', description: 'User identifier', in: 'path', required: true, example: "joe")]
    #[OA\RequestBody(description: 'User', required: true, content: new OA\JsonContent(ref: "#/components/schemas/User"))]
    #[OA\Response(response: 204, description: "Rule updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): array
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
     * @throws Exception
     */
    #[OA\Delete(path: '/api/v4/users/{name}', operationId: 'deleteUsers', description: "Delete user", tags: ['Users'])]
    #[OA\Parameter(name: 'name', description: 'User identifier', in: 'path', required: true, example: "joe")]
    #[OA\Response(response: 204, description: "User deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
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

    private static function convertUserObject(array $user): array
    {
        return [
            "name" => $user['screenName'] ?? $user['screenname'] ?? $user['userid'],
            "user_group" => $user["usergroup"] ?? null,
            "email" => $user["email"] ?? null,
            "properties" => $user["properties"] ?? null,
            "password" => $user["password"] ?? null,
        ];
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
        $body = Input::getBody();
        // Patch and delete on collection is not allowed
        if (empty($user) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("Patch and delete on an user collection is not allowed.", 406);
        }
        // Throw exception if tried POST with resource
        if (Input::getMethod() == 'post' && $user) {
            $this->postWithResource();
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, 'users', Input::getMethod());

    }

    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'name' => new Assert\Optional([
                new Assert\Length(['min' => 4])
            ]),
            'email' => new Assert\Optional([
                new Assert\Email(),
            ]),
            'password' => new Assert\Optional([
                new Assert\PasswordStrength(['minScore' => 1]),
            ]),
            'user_group' => new Assert\Optional([
            ]),
            'properties' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\NotBlank(),
            ]),
        ]);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }
}