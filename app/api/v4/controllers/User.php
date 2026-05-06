<?php
/**
 * @author     Martin Høgh <shumsan1011@gmail.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\models\Database;
use app\models\Setting;
use app\models\User as UserModel;
use Exception;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "User",
    description: "Sub-users are API users managed under a super-user and can be granted specific database privileges.",
    required: ["name", "email", "password"],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name",
            description: "User name.",
            type: "string",
            example: "joe",
        ),
        new OA\Property(
            property: "email",
            title: "E-mail",
            description: "User email address.",
            type: "string",
            format: "email",
            example: "joe@example.com",
        ),
        new OA\Property(
            property: "password",
            title: "Password",
            description: "User password. Minimum 8 characters with at least one uppercase letter and one number.",
            type: "string",
            format: "password",
            example: "Abc123!",
        ),
        new OA\Property(
            property: "properties",
            title: "Properties",
            description: "Arbitrary custom properties for the user.",
            type: "object",
            example: ["phone" => "555-1234567", "address" => "123 Main St", "city" => "New York"],
        ),
        new OA\Property(
            property: "default_user",
            title: "Is default user",
            description: "Whether this is the default user when no token is provided (public access).",
            type: "boolean",
            example: true,
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['POST', 'PATCH', 'DELETE', 'GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/users/[user]', scope: Scope::SUB_USER_ALLOWED)]
class User extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'users';
    }

    /**
     * @return Response
     * @throws Exception
     */
    #[OA\Get(path: '/api/v4/users/{name}', operationId: 'getUser', description: "Get sub-user(s).", tags: ['Users'])]
    #[OA\Parameter(name: 'name', description: 'User identifier', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: "joe")]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/User"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/User"))])
    )]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $r = [];
        $requestedUser = $this->route->getParam("user");
        if (!$requestedUser) {
            return $this->getResponse($this->getAll()['users']);
        }
        $users = explode(',', $requestedUser);
        foreach ($users as $user) {
            if (!$this->route->jwt["data"]["superUser"] && $this->route->jwt["data"]["uid"] != $user) {
                throw new Exception("Sub-users are not allowed to get information about other sub users");
            }
            $userModelLocal = new UserModel($user, $this->route->jwt["data"]["database"]);
            $r[] = self::convertUserObject($userModelLocal->getData()["data"]);
        }
        return $this->getResponse(data: $r, single: count($r) == 1);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/users', operationId: 'postUser', description: "Create new sub-user(s).", tags: ['Users'])]
    #[OA\RequestBody(description: 'User to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/User"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/User"))])
    )]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        if (!$this->route->jwt["data"]["superUser"]) {
            throw new Exception("Sub-users are not allowed to create other sub users");
        }
        $list = [];
        $model = new UserModel();
        $data = json_decode(Input::getBody(), true) ?: [];
        if (!array_is_list($data)) {
            $data = [$data];
        }
        $database = $this->route->jwt["data"]['database'];
        // Create users
        $model->withTransaction(function () use (&$list, $model, $data, $database) {
            foreach ($data as $user) {
                $user['parentdb'] = $database;
                $user['subuser'] = true;
                $user["usergroup"] = $user["user_group"] ?? null;
                // Load pre extensions and run processAddUser
                $this->runPreExtension('processAddUser', $model);
                $userName = self::convertUserObject($model->createUser($user)['data'])['name'];
                $list[] = $userName;
            }
        });
        // Create schemas for new users
        foreach ($data as $user) {
            try {
                // Create schema with superuser as owner and grant usage to subuser
                $dbObj = new Database(connection: $this->connection);
                $dbObj->createSchema(name: $user['name']);
                $dbObj->grantUsage(schema: $user['name'], user: $this->connection->user);
                // Let subuser set default privileges on schema to superuser
                $dbObj = new Database(connection: new Connection(user: $user['name'], database: $database));
                $dbObj->setDefaultPrivileges(schema: $user['name'], user: $this->connection->user);
            } catch (Exception) {
            }
        }

        foreach ($list as $newUser) {
            (new Setting(connection: $this->connection))->updateApiKeyForUser($newUser, false);
        }
        return $this->postResponse("/api/v4/users/", $list);
    }

    /**
     * @return Response
     * @throws Exception
     */
    #[OA\Patch(path: '/api/v4/users/{name}', operationId: 'patchUser', description: "Update existing sub-user(s).", tags: ['Users'])]
    #[OA\Parameter(name: 'name', description: 'User identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: "joe")]
    #[OA\RequestBody(description: 'User updates.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/User"))]
    #[OA\Response(response: 204, description: "User updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        $requestedUsers = explode(',', $this->route->getParam("user"));

        $data = json_decode(Input::getBody(), true) ?: [];
        $currentUserId = $this->route->jwt["data"]["uid"];
        $dataBase = $this->route->jwt["data"]["database"];

        foreach ($requestedUsers as $requestedUserId) {
            if (!$this->route->jwt["data"]["superUser"] && $this->route->jwt["data"]["uid"] != $requestedUserId) {
                throw new Exception("Sub-users are not allowed to update other sub users");
            }
            $data["user"] = $requestedUserId;
            if (array_key_exists("user_group", $data)) {
                $data["usergroup"] = $data["user_group"];
                unset($data["user_group"]);
            }
            if ($currentUserId == $requestedUserId) {
                if (!$this->route->jwt["data"]['superUser']) {
                    $data['parentdb'] = $this->route->jwt["data"]['database'];
                }
                $model = new UserModel();
                $model->withTransaction(function () use ($model, $data) {
                    $model->updateUser($data);
                });
            } else {
                $model = new UserModel($requestedUserId, $dataBase);
                $model->withTransaction(function () use ($model, &$data, $currentUserId) {
                    $user = $model->getData();
                    if ($user["data"]["parentdb"] == $currentUserId) {
                        $data["parentdb"] = $user["data"]["parentdb"];
                        $model->updateUser($data);
                    } else {
                        throw new Exception("Requested user is not the subuser of the currently authenticated user");
                    }
                });
            }
        }
        return $this->patchResponse('/api/v4/users/', $requestedUsers);
    }

    /**
     * @return Response
     * @throws Exception
     * @throws InvalidArgumentException
     */
    #[OA\Delete(path: '/api/v4/users/{name}', operationId: 'deleteUsers', description: "Delete sub-user(s).", tags: ['Users'])]
    #[OA\Parameter(name: 'name', description: 'User identifier', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: "joe")]
    #[OA\Response(response: 204, description: "User deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): Response
    {
        if (!$this->route->jwt["data"]["superUser"]) {
            throw new Exception("Sub-users are not allowed to delete sub users");
        }
        $requestedUsers = explode(',', $this->route->getParam("user"));
        $model = new UserModel(userId: $this->route->jwt["data"]['uid']);
        $model->withTransaction(function () use ($model, $requestedUsers) {
            foreach ($requestedUsers as $requestedUser) {
                $model->deleteUser($requestedUser);
            }
        });
        return $this->deleteResponse();
    }

    private static function convertUserObject(array $user): array
    {
        return [
            "name" => $user['screenName'] ?? $user['screenname'] ?? $user['userid'],
            "user_group" => $user["usergroup"] ?? null,
            "email" => $user["email"] ?? null,
            "properties" => $user["properties"] ?? null,
            "private_properties" => $user["private_properties"] ?? null,
            "default_user" => $user["default_user"],
        ];
    }

    /**
     * @return array
     * @throws Exception
     */
    public function getAll(): array
    {
        $currentUserId = $this->route->jwt["data"]["database"];
        $usersData = (new UserModel())->getSubusers($currentUserId)['data'];
        return ['users' => array_map([$this, 'convertUserObject'], $usersData)];
    }


    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $user = $this->route->getParam("user");
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
        $this->validateRequest($collection, $body, Input::getMethod());
    }

    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'name' => new Assert\Optional([
                new Assert\Length(min: 2, max: 40),
            ]),
            'email' => new Assert\Optional([
                new Assert\Email(),
            ]),
            'password' => new Assert\Optional([
                //new Assert\PasswordStrength(minScore: 4),
            ]),
            'user_group' => new Assert\Optional([
            ]),
            'properties' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\NotBlank(),
            ]),
            'default_user' => new Assert\Optional([
                new Assert\Type('boolean'),
            ]),
        ]);
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
