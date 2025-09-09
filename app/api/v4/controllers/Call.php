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
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\PostResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\exceptions\RPCException;
use app\inc\Connection;
use app\inc\Input;
use app\api\v2\Sql as V2Sql;
use app\inc\Route2;
use app\models\Preparedstatement as PreparedstatementModel;
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
#[Controller(route: 'api/v4/call', scope: Scope::SUB_USER_ALLOWED)]
class Call extends AbstractApi
{

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'call';
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     * @throws InvalidArgumentException
     * @throws RPCException
     * @throws Exception
     */
    #[OA\Post(path: '/api/v4/call', operationId: 'postCall', description: "Execute RPC method", tags: ['Methods'])]
    #[OA\RequestBody(description: 'RPC method to execute', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Call"))]
    #[OA\Response(response: 200, description: 'OK', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $decodedBody = json_decode(Input::getBody(), true);
        if (!array_is_list($decodedBody)) {
            $decodedBody = [$decodedBody];
        }
        $pres = new PreparedstatementModel(connection: $this->connection);;
        $result = [];
        foreach ($decodedBody as $query) {
            try {
                $preStm = $pres->getByName($query['method']);
            } catch (Exception) {
                throw new RPCException("Method not found", -32601, null);
            }
            $query['q'] = $preStm['data']['statement'];
            $query['type_hints'] = json_decode($preStm['data']['type_hints'], true);
            $query['type_formats'] = json_decode($preStm['data']['type_formats'], true);
            $query['output_format'] = $preStm['data']['output_format'];
            $query['srs'] = $preStm['data']['srs'];
            $query['params'] = $query['params'] ?? null;
            try {
                $res = (new Sql($this->route, connection: $this->connection))->runStatement($query, $this->route->jwt["data"]["uid"], $this->route->jwt["data"]["superUser"]);
            } catch (Exception $e) {
                if (in_array($e->getCode(), ['HY093', '406'])) {
                    throw new RPCException("Invalid params", -32602, null, $e->getMessage(), $query['id']);
                }
                throw new RPCException("Internal error", -32603, null, $e->getMessage(), $query['id']);
            }
            $jsonRpcResponse = [
                'jsonrpc' => $query['jsonrpc'],
                'result' => $res,
            ];
            if (isset($query['id'])) {
                $jsonRpcResponse['id'] = $query['id'];
                $result[] = $jsonRpcResponse;
            }
        }
        if (count($result) == 0) {
            return new NoContentResponse();
        }
        if (count($result) == 1) {
            return new PostResponse(data: $result[0]);
        }
        return new PostResponse(data: $result);
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
                    $this->validateRequest(self::getAssert(), json_encode($value), Input::getMethod());
                }
            } elseif ($decodedBody !== null) {
                $this->validateRequest(self::getAssert(), $body, Input::getMethod());
            }
        } catch (GC2Exception $e) {
            throw new RPCException("Invalid Request", -32600, null, $e->getMessage());
        }
    }

    static public function getAssert(): Assert\Collection
    {
        return self::getRpcAssert();
    }

    public function get_index(): Response
    {
        // TODO: Implement get_index() method.
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function patch_index(): Response
    {
        // TODO: Implement patch_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }
}
