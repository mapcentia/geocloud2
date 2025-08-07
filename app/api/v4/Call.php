<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\exceptions\RPCException;
use app\inc\Input;
use app\inc\Jwt;
use app\api\v2\Sql as V2Sql;
use app\models\Setting;
use Exception;
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
    schema: "Call",
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
            description: "A String containing the name of the method to be invoked or created",
            type: "string",
            example: "getDate",
        ),
        new OA\Property(
            property: "params",
            title: "Parameters",
            description: "Parameters for method.",
            type: "array",
            items: new OA\Items(type: "object"),
            example: ["my_date" => "2011 04 01"],
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
class Call extends AbstractApi
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
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     * @throws InvalidArgumentException
     * @throws RPCException
     */
    #[OA\Post(path: '/api/v4/call', operationId: 'postCall', description: "Execute RPC method", tags: ['Methods'])]
    #[OA\RequestBody(description: 'RPC method to execute', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Call"))]
    #[OA\Response(response: 200, description: 'OK', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 400, description: 'Bad request')]
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

        $api = new \app\models\Sql();
        $api->connect();
        $api->begin();

        // Start of RPC response
        if (!array_is_list($decodedBody)) {
            $decodedBody = [$decodedBody];
        }
        $result = [];
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
            try {
                $res = $this->v2->get_index($user, $api);
            } catch (Exception $e) {
                if ($e->getCode() == -32601) {
                    throw new RPCException("Method not found", -32601, null, null, $value['id']);
                }
                if (in_array($e->getCode(), ['HY093', '406'])) {
                    throw new RPCException("Invalid params", -32602, null, $e->getMessage(), $value['id']);
                }
                throw new RPCException("Internal error", -32603, null, $e->getMessage(), $value['id']);
            } finally {
                Input::setParams(null);
                Input::setBody(null);
            }
            unset($res['success']);
            unset($res['forGrid']);
            $jsonrpcResponse = [
                'jsonrpc' => $value['jsonrpc'],
                'result' => $res,
            ];
            if (isset($value['id'])) {
                $jsonrpcResponse['id'] = $value['id'];
                $result[] = $jsonrpcResponse;
            }
        }
        if ($api->db->inTransaction()) {
            $api->commit();
        }
        if (count($result) == 0) {
            return ['code' => '204'];
        }
        if (count($result) == 1) {
            return $result[0];
        }
        return $result;
    }

    /**
     * @throws GC2Exception
     * @throws RPCException
     */
    #[Override]
    public function validate(): void
    {
        $body = Input::getBody();

        if (empty($body) && Input::getMethod() == 'post') {
            throw new GC2Exception("POST without request body is not allowed.", 400);
        }
        $decodedBody = json_decode($body);
        try {
            if (is_array($decodedBody)) {
                foreach ($decodedBody as $value) {
                    $this->validateRequest(self::getAssert($value), json_encode($value), 'methods', Input::getMethod());
                }
            } elseif ($decodedBody !== null) {
                $this->validateRequest(self::getAssert($decodedBody), $body, 'methods', Input::getMethod());
            }
        } catch (GC2Exception $e) {
            throw new RPCException("Invalid Request", -32600, null, $e->getMessage());
        }
    }

    static public function getAssert(): Assert\Collection
    {
        return self::getRpcAssert();
    }

    public function get_index(): array
    {
        // TODO: Implement get_index() method.
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }

    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }
}
