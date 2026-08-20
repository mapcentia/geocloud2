<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
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
use app\models\Keyvalue as KeyvalueModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;
use Override;


/**
 * v4 Keyvalue API on settings.key_value with an owner/public access model.
 *
 * A super user has full CRUD over every key. A sub user may READ its own keys,
 * any public key, and legacy keys (owner IS NULL, treated as super-owned and
 * public), and may CREATE/UPDATE/DELETE only keys it owns. The owner is always
 * taken from the JWT — never trusted from the request body.
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Keyvalue",
    description: "A key/value entry with an owner and a public flag.",
    required: ["value"],
    properties: [
        new OA\Property(property: "value", title: "Value", description: "Arbitrary JSON value to store.", type: "object", example: ["a" => 1]),
        new OA\Property(property: "public", title: "Public", description: "When true the key is readable by any user in the database.", type: "boolean", default: false, example: false),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[Controller(route: 'api/v4/keyvalue/[key]', scope: Scope::SUB_USER_ALLOWED)]
class Keyvalue extends AbstractApi
{
    private KeyvalueModel $keyValue;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->keyValue = new KeyvalueModel($connection);
        $this->resource = 'keyvalue';
    }

    /**
     * Shapes a DB row for output: value decoded to JSON, public cast to bool,
     * id cast to int.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function present(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'key' => $row['key'],
            'value' => isset($row['value']) ? json_decode($row['value'], true) : null,
            'owner' => $row['owner'],
            'public' => (bool)$row['public'],
        ];
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/keyvalue/{key}', operationId: 'getKeyvalue', description: "Get a key, or list all visible keys.", tags: ['Keyvalue'])]
    #[OA\Parameter(name: 'key', description: 'Key to fetch', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: 'my_key')]
    #[OA\Parameter(name: 'paths', description: "Project only the named JSON sub-trees of the value. Comma-separated paths, each a dot-separated segment sequence, e.g. 'user.name,active'. The result value is keyed by each path string.", in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'user.name,active')]
    #[OA\Response(response: 200, description: 'Ok')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $key = $this->route->getParam('key');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $paths = empty($_GET['paths']) ? null : $this->parsePaths($_GET['paths']);

        $data = $this->keyValue->getForUser($key, $uid, $isSuperUser, $paths);
        if (!empty($key)) {
            if (empty($data)) {
                throw new GC2Exception("Not found", 404, null, "KEY_NOT_FOUND");
            }
            return $this->getResponse([$this->present($data)], single: true);
        }
        return $this->getResponse(array_map(fn($r) => $this->present($r), $data));
    }

    /**
     * Parses and validates the ?paths projection parameter into a list of path
     * strings. Paths are comma-separated; each path is a dot-separated segment
     * sequence. Each path must be non-empty and every segment must be non-empty;
     * the segments themselves are bound as parameters downstream.
     *
     * @return array<string>
     * @throws GC2Exception On an empty path or an empty segment.
     */
    private function parsePaths(string $raw): array
    {
        $paths = explode(',', $raw);
        foreach ($paths as $path) {
            if ($path === '') {
                throw new GC2Exception("A path in 'paths' is empty.", 400, null, "INVALID_PATHS");
            }
            foreach (explode('.', $path) as $segment) {
                if ($segment === '') {
                    throw new GC2Exception("A path segment in 'paths' is empty.", 400, null, "INVALID_PATHS");
                }
            }
        }
        return $paths;
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/keyvalue/{key}', operationId: 'postKeyvalue', description: "Create a key owned by the caller.", tags: ['Keyvalue'])]
    #[OA\Parameter(name: 'key', description: 'Key to create', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_key')]
    #[OA\RequestBody(description: 'Key/value definition.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Keyvalue"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 409, description: 'Key already exists')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $key = $this->route->getParam('key');
        $uid = $this->route->jwt["data"]["uid"];
        $body = json_decode(Input::getBody(), true);

        $this->keyValue->withTransaction(function () use ($key, $uid, $body) {
            $this->keyValue->insertForUser($key, json_encode($body['value']), $uid, !empty($body['public']));
        });
        return $this->postResponse("/api/v4/keyvalue/", [$key]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/keyvalue/{key}', operationId: 'patchKeyvalue', description: "Update a key's value and/or public flag.", tags: ['Keyvalue'])]
    #[OA\Parameter(name: 'key', description: 'Key to update', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_key')]
    #[OA\RequestBody(description: 'Partial key/value update.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Keyvalue"))]
    #[OA\Response(response: 303, description: 'Key updated')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        $key = $this->route->getParam('key');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $body = json_decode(Input::getBody(), true);
        $value = array_key_exists('value', $body) ? json_encode($body['value']) : null;
        $public = array_key_exists('public', $body) ? (bool)$body['public'] : null;

        $this->keyValue->withTransaction(function () use ($key, $value, $public, $uid, $isSuperUser) {
            $this->keyValue->updateForUser($key, $value, $public, $uid, $isSuperUser);
        });
        return $this->patchResponse('/api/v4/keyvalue/', [$key]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/keyvalue/{key}', operationId: 'deleteKeyvalue', description: "Delete a key.", tags: ['Keyvalue'])]
    #[OA\Parameter(name: 'key', description: 'Key to delete', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_key')]
    #[OA\Response(response: 204, description: "Key deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        $key = $this->route->getParam('key');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $this->keyValue->withTransaction(function () use ($key, $uid, $isSuperUser) {
            $this->keyValue->deleteForUser($key, $uid, $isSuperUser);
        });
        return $this->deleteResponse();
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $key = $this->route->getParam("key");
        $body = Input::getBody();

        // A key is always required — the key is the resource identifier.
        if (empty($key)) {
            if (in_array(Input::getMethod(), ['post', 'patch', 'delete'])) {
                throw new GC2Exception("A key is required.", 400, null, "KEY_REQUIRED");
            }
            // GET with no key lists the collection.
            return;
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
    }

    static public function getAssert(): Assert\Collection
    {
        $fields = [
            'public' => new Assert\Optional(new Assert\Type('bool')),
        ];
        if (Input::getMethod() == 'patch') {
            $fields['value'] = new Assert\Optional();
        } else {
            $fields['value'] = new Assert\Required();
        }
        return new Assert\Collection($fields);
    }
}
