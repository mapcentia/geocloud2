<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
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
use app\models\Preparedstatement as PreparedstatementModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Override;


/**
 * Class Method
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Method",
    description: "nstead of sending raw SQL every time you want to run a statement, you can wrap your SQL statements inside JSON-RPC methods. This means you define a named method which holds your SQL query along with optional instructions on how to interpret and format the data types.",
    required: ["method", "q"],
    properties: [
        new OA\Property(
            property: "method",
            title: "Method",
            description: "A String containing the name of the method to be created.",
            type: "string",
            example: "getDate",
        ),
        new OA\Property(
            property: "q",
            title: "Query",
            description: "SQL statement. SELECT, INSERT, UPDATE or DELETE.",
            type: "string",
            example: "SELECT :my_date::date as my_date",
        ),
        new OA\Property(
            property: "type_hints",
            title: "Type hints",
            description: "For JSON represented parameters which are not of JSON type.",
            type: "object",
            example: ["my_date" => "date"],
        ),
        new OA\Property(
            property: "type_formats",
            title: "Type formats",
            description: "For JSON represented parameters which are not of JSON type.",
            type: "object",
            example: ["my_date" => "Y m d"],
        ),
        new OA\Property(
            property: "output_format",
            title: "Output format",
            description: "The wanted output format.",
            type: "string",
            default: "json",
            example: "csv",
        ),
        new OA\Property(
            property: "srs",
            title: "Spatial reference system",
            description: "The spatial reference system to use for PostGIS geometry columns. EPSG code.",
            type: "integer",
            default: 4326,
            example: 25832,
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[Controller(route: 'api/v4/methods/[id]', scope: Scope::SUB_USER_ALLOWED)]
class Method extends AbstractApi
{
    private PreparedstatementModel $pres;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->pres = new PreparedstatementModel($connection);
        $this->resource = 'methods';
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/methods/{method}', operationId: 'getRpc', description: "Get RPC methods", tags: ['Methods'])]
    #[OA\Parameter(name: 'method', description: 'Identifier of RPC method', in: 'path', required: false, example: 'myMethod')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Method"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $id = $this->route->getParam('id');;
        $q = $this->pres->getAll()['data'];
        $r = [];
        foreach ($q as $s) {
            $r[] = [
                'q' => $s['statement'],
                'method' => $s['name'],
                'type_hints' => json_decode($s['type_hints']),
                'type_formats' => json_decode($s['type_formats']),
                'output_format' => $s['output_format'],
                'srs' => $s['srs'],
            ];
        }
        if (!empty($id)) {
            $names = explode(',', $id);
            $r = array_values(array_filter($r, function ($m) use ($names) {
                return in_array($m['method'], $names);
            }));
            if (count($r) !== count($names)) {
                throw new GC2Exception("Not found", 404);
            }
        }
        return $this->getResponse($r);

    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/methods', operationId: 'postRpc', description: "Create RPC method", tags: ['Methods'])]
    #[OA\RequestBody(description: 'RPC method to create', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Method"))]
    #[OA\Response(response: 201, description: 'Created', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $list = [];
        $uid = $this->route->jwt["data"]["uid"];
        $decodedBody = json_decode(Input::getBody(), true);

        if (!empty($decodedBody['methods'])) {
            $methods = $decodedBody['methods'];
        } else {
            $methods = [$decodedBody];
        }
        $this->pres->begin();
        foreach ($methods as $m) {
            $q = $m['q'];
            $method = $m['method'];
            $typeHints = $m['type_hints'];
            $typeFormats = $m['type_formats'];
            $outputFormat = $m['output_format'] ?? 'json';
            $srs = $m['srs'] ?? 4326;
            $list[] = $this->pres->createPreparedStatement($method, $q, $typeHints, $typeFormats, $outputFormat, $srs, $uid);
        }
        $this->pres->commit();
        return $this->postResponse("/api/v4/methods/", $list);
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/methods/{method}', operationId: 'patchRpc', description: "Update RPC method", tags: ['Methods'])]
    #[OA\Parameter(name: 'method', description: 'Method name', in: 'path', required: true, example: '66f5005bd44c6')]
    #[OA\RequestBody(description: 'RPC method to execute', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Method"))]
    #[OA\Response(response: 204, description: 'Method updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        $id = $this->route->getParam('id');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $ids = explode(',', $id);
        $body = json_decode(Input::getBody(), true);

        $this->pres->connect();
        $this->pres->begin();
        $names = [];
        foreach ($ids as $id) {
            $names[] = $this->pres->updatePreparedStatement($id, $body['method'] ?? null, $body['q'] ?? null, $body['type_hints'] ?? null, $body['type_formats'] ?? null, $body['output_format'] ?? null, $body['srs'] ?? null, $uid, $isSuperUser);
        }
        $this->pres->commit();
        return $this->patchResponse('/api/v4/methods/', array_map(fn($c) => $c['name'], $names));
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Delete(path: '/api/v4/methods/{id}', operationId: 'deleteSql', description: "Delete RPC method", tags: ['Methods'])]
    #[OA\Parameter(name: 'id', description: 'Name of method', in: 'path', required: true, example: 'myStatement')]
    #[OA\Response(response: 204, description: "Method deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        $id = $this->route->getParam('id');
        $uid = $this->route->jwt["data"]["uid"];
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $ids = explode(',', $id);
        $this->pres->begin();
        foreach ($ids as $id) {
            $this->pres->deletePreparedStatement($id, $uid, $isSuperUser);
        }
        $this->pres->commit();
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $id = $this->route->getParam("id");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a Method collection is not allowed.", 400);
        }

        // Throw exception if tried with method resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    static public function getAssert(): Assert\Collection
    {
        $asserts = Sql::getAssert();
        unset($asserts->fields['params']);
        if (Input::getMethod() == 'patch') {
            $asserts->fields['q'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]
            );
            $asserts->fields['method'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                ]
            );
        } else {
            $asserts->fields['method'] = new Assert\Required(
                new Assert\Type('string')
            );
        }
        return $asserts;
    }
}
