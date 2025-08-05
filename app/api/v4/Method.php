<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Preparedstatement as PreparedstatementModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Method
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Method",
    required: [],
    properties: [
        new OA\Property(
            property: "method",
            title: "Method name",
            description: "A String containing the name of the method to be created",
            type: "string",
            example: "getDate",
        ),
        new OA\Property(
            property: "q",
            title: "Query",
            description: "SQL statement. SELECT, INSERT, UPDATE or DELETE",
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
            description: "The spatial reference system to use for PostGIS geometry columns. EPSG code",
            type: "integer",
            default: 4326,
            example: 25832,
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
class Method extends AbstractApi
{
    public function __construct()
    {
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
    public function get_index(): array
    {
        $id = Route2::getParam('id');;
        $pres = new PreparedstatementModel();
        $q = $pres->getAll()['data'];
        $methods = [];
        foreach ($q as $s) {
            $methods[] = [
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
            $methods = array_values(array_filter($methods, function ($m) use ($names) {
                return in_array($m['method'], $names);
            }));
            if (count($methods) !== count($names)) {
                throw new GC2Exception("Not found", 404);
            }
        }
        if (count($methods) > 1) {
            return [
                'methods' => $methods,
            ];
        } else {
            return $methods[0];
        }
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/methods', operationId: 'postRpc', description: "Create RPC method", tags: ['Methods'])]
    #[OA\RequestBody(description: 'RPC method to create', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Method"))]
    #[OA\Response(response: 201, description: 'Created', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): array
    {
        $jwtData = Jwt::validate()["data"];
        $uid = $jwtData["uid"];
        $decodedBody = json_decode(Input::getBody(), true);

        if (!empty($decodedBody['methods'])) {
            $methods = $decodedBody['methods'];
        } else {
            $methods = [$decodedBody];
        }
        $pres = new PreparedstatementModel();
        $pres->connect();
        $pres->begin();
        foreach ($methods as $m) {
            $q = $m['q'];
            $method = $m['method'];
            $typeHints = $m['type_hints'];
            $typeFormats = $m['type_formats'];
            $outputFormat = $m['output_format'] ?? 'json';
            $srs = $m['srs'] ?? 4326;
            $pres->createPreparedStatement($method, $q, $typeHints, $typeFormats, $outputFormat, $srs, $uid);
        }
        $pres->commit();
        return ['code' => '201'];
    }

    /**
     * @throws GC2Exception
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
    public function patch_index(): array
    {
        $id = Route2::getParam('id');
        $jwtData = Jwt::validate()["data"];
        $uid = $jwtData["uid"];
        $isSuperUser = $jwtData["superUser"];
        $model = new PreparedstatementModel();
        $ids = explode(',', $id);
        $body = json_decode(Input::getBody(), true);

        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            $model->updatePreparedStatement($id, $body['method'], $body['q'], $body['type_hints'], $body['type_formats'], $body['output_format'], $body['srs'], $uid, $isSuperUser);
        }
        $model->commit();
        header("Location: /api/v4/rpc/" . implode(",", $ids));
        return ["code" => "303"];
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
    public function delete_index(): array
    {
        $id = Route2::getParam('id');
        $jwtData = Jwt::validate()["data"];
        $uid = $jwtData["uid"];
        $isSuperUser = $jwtData["superUser"];
        $model = new PreparedstatementModel();
        $ids = explode(',', $id);
        $model->connect();
        $model->begin();
        foreach ($ids as $id) {
            $model->deletePreparedStatement($id, $uid, $isSuperUser);
        }
        $model->commit();
        return ["code" => "204"];
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $id = Route2::getParam("id");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a RPC collection is not allowed.", 400);
        }
        if (empty($body) && in_array(Input::getMethod(), ['post', 'patch'])) {
            throw new GC2Exception("POST and PATCH without request body is not allowed.", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }
        $decodedBody = json_decode($body);

        try {
            if (is_array($decodedBody)) {
                foreach ($decodedBody as $value) {
                    $this->validateRequest(self::getAssert(), json_encode($value), 'methods', Input::getMethod());
                }
            } elseif ($decodedBody !== null) {
                $this->validateRequest(self::getAssert(), $body, 'methods', Input::getMethod());
            }
        } catch (GC2Exception $e) {
            throw new GC2Exception("Invalid Request", -32600, null, $e->getMessage());
        }
    }

    public function put_index(): array
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
