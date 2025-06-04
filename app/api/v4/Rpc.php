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
    schema: "Rpc",
    required: ["jsonrpc", "method"],
    properties: [
        new OA\Property(
            property: "jsonrpc",
            title: "JSON RPC version",
            description: "The version number of the JSON-RPC protocol. Must be exactly \"2.0\".",
            type: "string",
            example: "2.0",
        ),
        new OA\Property(
            property: "id",
            title: "Identifier",
            description: "An identifier established by the Client that MUST contain a string if included",
            type: "string",
            example: "1",
        ),
        new OA\Property(
            property: "method",
            title: "Method name",
            description: "A String containing the name of the method to be invoked",
            type: "string",
            example: "getDataById",
        ),
        new OA\Property(
            property: "params",
            title: "Parameters",
            description: "A Structured value that holds the parameter values to be used during the invocation of the method.",
            type: "object",
            example: ["id" => 1],
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Rpc extends AbstractApi
{
    /**
     * @var V2Sql
     */
    private V2Sql $v2;

    public function __construct()
    {
        $this->v2 = new V2Sql();
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/rpc/{id}', operationId: 'getRpc', description: "Get RPC methods", tags: ['Rpc'])]
    #[OA\Parameter(name: 'id', description: 'Identifier of RPC method', in: 'path', required: false, example: 'myMethod')]
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
    #[OA\Post(path: '/api/v4/rpc', operationId: 'postRpc', description: "Execute RPC method", tags: ['Rpc'])]
    #[OA\RequestBody(description: 'RPC method to execute', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Rpc"))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType('application/json'))]
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
        $api = new \app\models\Sql();
        $api->connect();
        $api->begin();
        foreach ($decodedBody as $value) {
            $srs = $value['srs'] ?? 4326;
            $api->setSRS($srs);
            Input::setBody(json_encode($value));
            Input::setParams(
                [
                    "key" => $apiKey,
                    "convert_types" => $value['convert_types'] ?? true,
                    "format" => "json",
                    "srs" => $srs,
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
                    $result[] = $jsonrpcResponse;

                }
            } else {
                $result[] = $res;
            }
        }
        if ($api->db->inTransaction()) {
            $api->commit();
        }

        if (count($result) == 0 && !empty($value['jsonrpc'])) {
            return ['code' => '204'];
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

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Delete(path: '/api/v4/rpc/{id}', operationId: 'deleteSql', description: "Delete RPC method", tags: ['Rpc'])]
    #[OA\Parameter(name: 'id', description: 'Name of method', in: 'path', required: true, example: 'myStatement')]
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
        return self::getRpcAssert();
    }
}
