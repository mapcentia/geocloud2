<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\api\v2\Sql as V2Sql;
use app\inc\Route2;
use app\models\Preparedstatement as PreparedstatementModel;
use app\models\Setting;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Sql",
    required: [],
    properties: [
        new OA\Property(
            property: "q",
            title: "Query",
            description: "SQL statement. SELECT, INSERT, UPDATE or DELETE",
            type: "string",
            example: "insert into my_table (id,range,point) values(:id, range(:geom)) returning id,geom",
        ),
        new OA\Property(
            property: "params",
            title: "Parameters",
            description: "Parameters for prepared statements.",
            type: "array",
            items: new OA\Items(type: "object"),
            example: ["id" => 1, "name" => "John"],
        ),
        new OA\Property(
            property: "type_hints",
            title: "Type hints",
            description: "For JSON represented parameters which are not of JSON type.",
            type: "object",
            example: ["range" => "numrange", "center" => "point"],
        ),
        new OA\Property(
            property: "id",
            title: "Store the statement on the server under this identifier",
            description: "Store the statement on the server, which can be re-run with changed parameters.",
            type: "string",
            example: "my_statement",
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Sql extends AbstractApi
{
    /**
     * @var V2Sql
     */
    private V2Sql $v2;

    public function __construct()
    {
        $this->v2 = new V2Sql();
    }

    #[OA\Get(path: '/api/v4/sql/{id}', operationId: 'getSql', description: "Get stored SQL statements", tags: ['Sql'])]
    #[OA\Parameter(name: 'id', description: 'Identifier of stored statement', in: 'path', required: false, example: 'my_statement')]
    #[OA\Response(response: 200, description: 'Ok')]
    #[OA\Response(response: 400, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $name = Route2::getParam('id');;
        $pres = new PreparedstatementModel();
        if (!empty($name)) {
            $q = $pres->getByName($name)['data'];
            return [
                'q' => $q['statement'],
                'uuid' => $q['uuid'],
                'store' => $name,
            ];
        } else {
            $q = $pres->getAll($name)['data'];
            $statements = [];
            foreach ($q as $statement) {
                $statements[] = [
                    'q' => $statement['statement'],
                    'uuid' => $statement['uuid'],
                    'id' => $statement['id'],
                ];
            }
            return [
                'statements' => $statements,
            ];
        }
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/sql', operationId: 'postSql', description: "Run SQL statements", tags: ['Sql'])]
    #[OA\RequestBody(description: 'Sql statement to run', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Sql"))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 201, description: 'Insert/update a prepared statement')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): array
    {
        $jwtData = Jwt::validate()["data"];
        $isSuperUser = $jwtData["superUser"];
        $uid = $jwtData["uid"];
        $user = [
            "user" => $isSuperUser ? $uid : "$uid@{$jwtData["database"]}"
        ];
        $settingsData = (new Setting())->get()["data"];
        $apiKey = $isSuperUser ? $settingsData->api_key : $settingsData->api_key_subuser->$uid;
        $decodedBody = json_decode(Input::getBody(), true);

        if (!array_is_list($decodedBody)) {
            $decodedBody = [$decodedBody];
        }
        $result = [];
        $api = new \app\models\Sql("3857");
        $api->connect();
        $api->begin();
        foreach ($decodedBody as $value) {
            Input::setBody(json_encode($value));
            Input::setParams(
                [
                    "key" => $apiKey,
                    "srs" => "4326",
                    "convert_types" => $value['convert_types'] ?? true,
                    "format" => "json",
                ]
            );


            $res = $this->v2->get_index($user, $api);

            unset($res['success']);
            // unset($res['forStore']);
            unset($res['forGrid']);


            if (!empty($value['jsonrpc'])) {
                $jsonrpcResponse = [
                    'jsonrpc' => $value['jsonrpc'],
                    'result' => $res,
                ];
                if (isset($value['id'])) {
                    $jsonrpcResponse['id'] = $value['id'];
                }
                $result[] = $jsonrpcResponse;
            } else {
                $result[] = $res;
            }
        }
        if ($api->db->inTransaction()) {
            $api->commit();
        }
        if (count($result) == 1) {
            return $result[0];
        }
        return $result;
    }

    #[Override]
    public function patch_index(): array
    {
        // TODO: Implement put_index() method.
    }

    #[OA\Delete(path: '/api/v4/sql/{id}', operationId: 'deleteSql', description: "Delete stored statement", tags: ['Sql'])]
    #[OA\Parameter(name: 'id', description: 'Id of statement', in: 'path', required: true, example: 'my_statement')]
    #[OA\Response(response: 204, description: "Statement deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): array
    {
        $name = Route2::getParam('id');;
        $pres = new PreparedstatementModel();
        $pres->deletePreparedStatement($name);
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
            throw new GC2Exception("PATCH and DELETE on a sql' collection is not allowed.", 400);
        }
        if (empty($body) && in_array(Input::getMethod(), ['post', 'patch'])) {
            throw new GC2Exception("POST and PATCH without request body is not allowed.", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }
        $decodedBody = json_decode($body);

        if (is_array($decodedBody)) {
            foreach ($decodedBody as $value) {
                $this->validateRequest(self::getAssert($value), json_encode($value), 'sql', Input::getMethod());
            }
        } else {
            $this->validateRequest(self::getAssert($decodedBody), $body, 'sql', Input::getMethod());
        }
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    static public function getAssert($decodedBody): Assert\Collection
    {
        if (!isset($decodedBody->jsonrpc)) {
            return new Assert\Collection([
                'q' => new Assert\Required(
                    new Assert\NotBlank(),
                ),
                'method' => new Assert\Optional(
                    new Assert\NotBlank(),
                ),
                'params' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\Count(['min' => 1]),
                ]),
                'type_hints' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\Count(['min' => 1]),
                ]),
                'type_formats' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\Count(['min' => 1]),
                ]),
                'output_format' => new Assert\Optional(
                    new Assert\NotBlank(),
                ),
                'format' => new Assert\Optional(
                    new Assert\NotBlank(),
                ),
                'convert_types' => new Assert\Optional([
                    new Assert\Type('boolean'),
                ]),
                'base64' => new Assert\Optional([
                    new Assert\Type('boolean'),
                ]),
                'lifetime' => new Assert\Optional([
                    new Assert\Type('integer'),
                ]),
                'srs' => new Assert\Optional(
                    new Assert\Type('integer'),
                ),
                'geo_format' => new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank(),
                    new Assert\Choice(['wkt', 'geojson']),

                ]),
            ]);
        } else {
            return new Assert\Collection([
                'jsonrpc' => new Assert\Required([
                    new Assert\Type('string'),
                    new Assert\Choice(['2.0']),
                ]),
                'method' => new Assert\Required(([
                    new Assert\NotBlank(),
                    new Assert\Type('string'),
                ])),
                'params' => new Assert\Optional([
                    new Assert\Type('array'),
                    new Assert\Count(['min' => 1]),
                ]),
                'id' => new Assert\Optional([
                    new Assert\NotBlank(),
                ]),
            ]);
        }

    }
}
