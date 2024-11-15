<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
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
    required: [],
    properties: [
        new OA\Property(
            property: "id",
            title: "Unique identifier",
            description: "Id of the rule. If omitted the rule will get an id automatically generated.",
            type: "integer",
            example: 1000,
        ),
        new OA\Property(
            property: "priority",
            title: "Priority",
            description: "All rules are checked by priority in descending order. The first that matches will be applied.",
            type: "integer",
            example: 10,
        ),
        new OA\Property(
            property: "username",
            title: "Username",
            description: "Rule match for user name (the user that makes the request)",
            type: "string",
            example: "john"
        ),
        new OA\Property(
            property: "service",
            title: "Service",
            description: "Rule match for service. 'sql', 'ows' or 'wfst'",
            type: "string",
            example: "sql"
        ),
        new OA\Property(
            property: "request",
            title: "Request",
            description: "Rule match for request. 'select', 'insert', 'update' or 'delete'",
            type: "string",
            example: "select"
        ),
        new OA\Property(
            property: "layer",
            title: "Layer",
            description: "Rule match for the requested layer(s)",
            type: "string",
            example: "my_table"
        ),
        new OA\Property(
            property: "iprange",
            title: "Iprange",
            description: "Rule match for the iprange, which the request originates from.",
            type: "string",
            example: "127.0.0.1/32"
        ),
        new OA\Property(
            property: "schema",
            title: "Schema",
            description: "Rule match for the requested schema(s)",
            type: "string",
            example: "my_schema"
        ),
        new OA\Property(
            property: "access",
            title: "Access",
            description: "The access level the rule grants. Can be 'allow', 'limit' or 'deny'",
            type: "string",
            example: "limit"
        ),
        new OA\Property(
            property: "filter",
            title: "Filter",
            description: "A filter for rules with 'limit' access. This is a valid WHERE clause",
            type: "string",
            example: "user='john'"
        ),

    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])]
class Geofence extends AbstractApi
{
    public GeofenceModel $geofence;

    public function __construct()
    {


    }

    /**
     * @return array
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/rules/{id}', operationId: 'getRule', description: "Get rules", tags: ['Rules'])]
    #[OA\Parameter(name: 'id', description: 'Rule identifier', in: 'path', required: false, example: 2)]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Rule"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $geofence = new GeofenceModel(null);
        if (!empty(Route2::getParam("id"))) {
            $ids = explode(',', Route2::getParam("id"));
            foreach ($ids as $id) {
                $r[] = $geofence->get($id)[0];
            }
        } else {
            $r = $geofence->get(null);
        }
        if (count($r) > 1) {
            return ["rules" => $r];
        } else {
            return $r[0];
        }
    }

    /**
     * @return array
     *
     */
    #[OA\Post(path: '/api/v4/rules', operationId: 'postRule', description: "New rules", tags: ['Rules'])]
    #[OA\RequestBody(description: 'New rule', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Rule"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): array
    {
        $list = [];
        $model = new GeofenceModel(null);
        $body = Input::getBody();
        $data = json_decode($body, true);
        if (!isset($data['rules'])) {
            $data['rules'] = [$data];
        }
        $model->connect();
        $model->begin();
        foreach ($data['rules'] as $datum) {
            $list[] = $model->create($datum)['data']['id'];
        }
        $model->commit();
        header("Location: /api/v4/rules/" . implode(",", $list));
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     * @throws GC2Exception
     */

    #[OA\Put(path: '/api/v4/rules/{id}', operationId: 'putRule', description: "New rules", tags: ['Rules'])]
    #[OA\Parameter(name: 'id', description: 'Rule identifier', in: 'path', required: true, example: 2)]
    #[OA\RequestBody(description: 'Update rule', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Rule"))]
    #[OA\Response(response: 204, description: "Rule updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function put_index(): array
    {
        $id = Route2::getParam("id");
        if (empty($id)) {
            throw new GC2Exception("No rule id", 404, null, 'MISSING_ID');
        }
        $ids = explode(',', Route2::getParam("id"));
        $body = Input::getBody();
        $data = json_decode($body, true);
        $model = new GeofenceModel(null);
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                throw new GC2Exception("Id is not a integer", 400, null, 'MISSING_ID');
            }
            $data["id"] = $id;
            $model->update($data);
        }
        $model->commit();
        header("Location: /api/v4/rules/" . implode(",", $ids));
        return ["code" => "303"];
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/rules/{id}', operationId: 'deleteRule', description: "Delete rule", tags: ['Rules'])]
    #[OA\Parameter(name: 'id', description: 'Id of rule', in: 'path', required: true, example: '2')]
    #[OA\Response(response: 204, description: "Rule deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): array
    {
        $ids = explode(',', Route2::getParam("id"));
        $model = new GeofenceModel(null);
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            if (!is_numeric($id)) {
                throw new GC2Exception("Id is not a integer", 400, null, 'MISSING_ID');
            }
            $model->delete((int)$id);
        }
        $model->commit();
        return ["code" => "204"];
    }

    #[Override] public function validate(): void
    {
        $id = Route2::getParam("id");
        $body = Input::getBody();

        // Put and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("PUT and DELETE on a rule collection is not allowed.", 400);
        }
        if (empty($body) && in_array(Input::getMethod(), ['post', 'put'])) {
            throw new GC2Exception("POST and PUT without request body is not allowed.", 400);
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
                new Assert\Choice(['sql', 'ows', 'wfst', '*']),
            ]),
            'request' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Choice(['select', 'insert', 'update', 'delete', '*']),
            ]),
            'layer' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'iprange' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
                new Assert\Cidr(),
            ]),
            'schema' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'access' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\Choice(['allow', 'limit', 'deny']),
            ]),
            'filter' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
        ]);
        if (!empty($body)) {
            $this->validateRequest($collection, $body, 'rules');
        }
    }
}


