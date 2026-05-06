<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
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
use app\inc\Route2;
use app\inc\Input;
use app\models\Geofence as GeofenceModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Geofence
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Rule",
    description: "Access-control rules applied to incoming SQL before execution (allow, deny, or limit via WHERE clause).",
    required: [],
    properties: [
        new OA\Property(
            property: "id",
            title: "Unique identifier",
            description: "Rule id. If omitted, an id is generated.",
            type: "integer",
            example: 1000,
        ),
        new OA\Property(
            property: "priority",
            title: "Priority",
            description: "Rules are evaluated by priority (highest first). First match is applied.",
            type: "integer",
            example: 10,
        ),
        new OA\Property(
            property: "username",
            title: "Username",
            description: "Match by user name (requesting user).",
            type: "string",
            example: "john"
        ),
        new OA\Property(
            property: "service",
            title: "Service",
            description: "Match by service.",
            type: "string",
            enum: ["sql", "ows", "wfst"],
            example: "sql"
        ),
        new OA\Property(
            property: "request",
            title: "Request",
            description: "Match by request type.",
            type: "string",
            enum: ["select", "insert", "update", "delete"],
            example: "select"
        ),
        new OA\Property(
            property: "table",
            title: "Table",
            description: "Match by table name(s).",
            type: "string",
            example: "my_table"
        ),
        new OA\Property(
            property: "iprange",
            title: "Iprange",
            description: "Match by source IP range (CIDR).",
            type: "string",
            example: "127.0.0.1/32"
        ),
        new OA\Property(
            property: "schema",
            title: "Schema",
            description: "Match by schema name(s).",
            type: "string",
            example: "my_schema"
        ),
        new OA\Property(
            property: "access",
            title: "Access",
            description: "Access level the rule grants.",
            type: "string",
            enum: ["allow", "limit", "deny"],
            example: "limit"
        ),
        new OA\Property(
            property: "filter",
            title: "Filter",
            description: "Filter for 'limit' access (SQL WHERE clause).",
            type: "string",
            example: "user='john'"
        ),

    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/rules/[id]', scope: Scope::SUPER_USER_ONLY)]
class Geofence extends AbstractApi
{
    public GeofenceModel $geofence;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->geofence = new GeofenceModel(connection: $connection);
        $this->resource = 'rules';
    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/rules/{id}', operationId: 'getRule', description: "Get rule(s).", tags: ['Rules'])]
    #[OA\Parameter(name: 'id', description: 'Rule identifier', in: 'path', required: false, schema: new OA\Schema(type: 'integer'), example: 2)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Rule"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Rule"))])
    )]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $r = [];
        if (!empty($this->route->getParam("id"))) {
            $ids = explode(',', $this->route->getParam("id"));
            foreach ($ids as $id) {
                $t = $this->geofence->get($id)[0];
                if (empty($t)) {
                    throw new GC2Exception("Not found", 404);
                }
                $r[] = $t;
            }
        } else {
            $r = $this->geofence->get(null);
        }
        // Rename layer to table
        $r = array_map(function ($item) {
            $item['table'] = $item['layer'];
            unset($item['layer']);
            return $item;
        }, $r);
        return $this->getResponse($r);
    }

    /**
     * @return Response
     *
     */
    #[OA\Post(path: '/api/v4/rules', operationId: 'postRule', description: "Create rule(s).", tags: ['Rules'])]
    #[OA\RequestBody(description: 'Rule to create.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Rule"),
        new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/Rule"))])
    )]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $list = [];
        $body = Input::getBody();
        $data = json_decode($body, true);
        if (!array_is_list($data)) {
            $data = [$data];
        }
        $this->geofence->withTransaction(function () use (&$list, $data) {
            foreach ($data as $datum) {
                if (isset($datum['table'])) {
                    $datum['layer'] = $datum['table'];
                    unset($datum['table']);
                }
                $list[] = $this->geofence->create($datum)['data']['id'];
            }
        });
        return $this->postResponse("/api/v4/rules/", $list);
    }

    /**
     * @return Response
     * @throws GC2Exception
     */

    #[OA\Patch(path: '/api/v4/rules/{id}', operationId: 'patchRule', description: "Update existing rule(s).", tags: ['Rules'])]
    #[OA\Parameter(name: 'id', description: 'Rule identifier', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 2)]
    #[OA\RequestBody(description: 'Update rule', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Rule"))]
    #[OA\Response(response: 204, description: "Rule updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): Response
    {
        $ids = explode(',', $this->route->getParam("id"));
        $list = [];
        $body = Input::getBody();
        $data = json_decode($body, true);
        $this->geofence->withTransaction(function () use (&$list, $ids, &$data) {
            foreach ($ids as $id) {
                if (!is_numeric($id)) {
                    throw new GC2Exception("Id is not a integer", 400, null, 'MISSING_ID');
                }
                if (!empty($data['id'])) {
                    $data["newId"] = $data['id'];
                }
                if (isset($data['table'])) {
                    $data['layer'] = $data['table'];
                    unset($data['table']);
                }
                $list[] = $this->geofence->update($id, $data);
            }
        });
        return $this->patchResponse('/api/v4/rules/', $list);
    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/rules/{id}', operationId: 'deleteRule', description: "Delete rule(s).", tags: ['Rules'])]
    #[OA\Parameter(name: 'id', description: 'Id of rule', in: 'path', required: true, schema: new OA\Schema(type: 'integer'), example: 2)]
    #[OA\Response(response: 204, description: "Rule deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): Response
    {
        $ids = explode(',', $this->route->getParam("id"));
        $this->geofence->withTransaction(function () use ($ids) {
            foreach ($ids as $id) {
                if (!is_numeric($id)) {
                    throw new GC2Exception("Id is not a integer", 400, null, 'MISSING_ID');
                }
                $this->geofence->delete((int)$id);
            }
        });
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     */
    #[Override] public function validate(): void
    {
        $id = $this->route->getParam("id");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a rule collection is not allowed.", 400);
        }

        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }

        $collection = new Assert\Collection([
            'id' => new Assert\Optional(
                new Assert\Type('integer'),
            ),
            'priority' => new Assert\Optional(
                new Assert\Type('integer'),
            ),
            'username' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'service' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Choice(choices: ['sql', 'ows', 'wfst', '*']),
            ]),
            'request' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Choice(choices: ['select', 'insert', 'update', 'delete', '*']),
            ]),
            'table' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'iprange' => new Assert\Optional([
                new Assert\AtLeastOneOf(
                    constraints: [
                        new Assert\Cidr(),
                        new Assert\EqualTo('*'),
                    ],
                )
            ]),
            'schema' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'access' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Choice(choices: ['allow', 'limit', 'deny']),
            ]),
            'filter' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);
        $this->validateRequest($collection, $body, Input::getMethod());
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
