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
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\exceptions\RPCException;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Rpc;
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
    description: "JSON-RPC 2.0 request payload. Used to call a named RPC method with optional parameters.",
    required: ["jsonrpc", "method"],
    properties: [
        new OA\Property(
            property: "jsonrpc",
            title: "JSON RPC version",
            description: "The version number of the JSON-RPC protocol. Must be exactly \"2.0\".",
            type: "string",
            enum: ["2.0"],
            example: "2.0",
        ),
        new OA\Property(
            property: "id",
            title: "Identifier",
            description: "Client-supplied request id. If present, must be a string.",
            type: "string",
            example: "1",
        ),
        new OA\Property(
            property: "method",
            title: "Method name",
            description: "Name of the RPC method to call.",
            type: "string",
            example: "getDate",
        ),
        new OA\Property(
            property: "params",
            title: "Parameters",
            description: "Parameters for the method. For SELECT methods, only one parameter set is allowed.",
            type: "array",
            items: new OA\Items(type: "object"),
            example: [["my_date" => "2011 04 01"], ["my_string" => "hello world"]],
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/call/(action)', scope: Scope::SUB_USER_ALLOWED)]
class Call extends AbstractApi
{

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'call';
    }

    /**
     * @return Response
     * @throws RPCException
     */
    #[OA\Post(path: '/api/v4/call', operationId: 'postCall',
        description: "Call a JSON-RPC method. The method must exist (see /api/v4/methods).",
        tags: ['Methods'])]
    #[OA\RequestBody(description: 'RPC request payload.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Call"))]
    #[OA\Response(response: 200, description: 'Ok', content: [new OA\MediaType('application/json'), new OA\MediaType('application/gpx'), new OA\MediaType('application/octet-stream')])]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        // Set user and user group
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $user = $this->route->jwt["data"]["uid"];
        $userGroup = $this->route->jwt["data"]["userGroup"];
        $decodedBody = json_decode(Input::getBody(), true);
        // If the request body is not an array, wrap it in an array
        if (!array_is_list($decodedBody)) {
            $decodedBody = [$decodedBody];
        }
        // Execute RPC methods
        $rpc = new Rpc($this->connection);
        $api = new \app\models\Sql(connection: $this->connection);
        $result = [];
        $api->begin();
        foreach ($decodedBody as $query) {
            $query['convert_types'] = true;
            $res = $rpc->run(user: $user, api:  $api, query: $query, subuser: !$isSuperUser, userGroup:  $userGroup);
            if ($res !== null) {
                $result[] = $res;
            }
        }
        // Check if dry-run is requested
        if (Input::getDryRun() || $this->route->action == 'dry') {
            $api->rollback();
            $api->begin();
            // In dry-run we interface with
            $pres = new PreparedstatementModel(connection: $this->connection);
            foreach ($result as $res) {
                $pres->updateRequest(name: $res['method'], request: $res['result']['_request']);
                $pres->updateOutputSchema(name: $res['method'], outputSchema: $this->extractSchema($res['result']));
                foreach ($res['params'] as $param => $value) {
                    $type = $res['type_hints'][$param] ?? \app\models\Sql::phpTypeToPgType(gettype($value)) ?? "json";
                    $inputSchema[$param] = ['type' => str_replace('[]', '', $type), 'array' => str_ends_with($type, '[]')];
                }
                if (!empty($inputSchema)) {
                    $pres->updateInputSchema(name: $res['method'], inputSchema: $inputSchema);
                }
            }
        }
        $api->commit();
        // Return response
        if (count($result) == 0) {
            return new NoContentResponse();
        }
        // Cleanup response
        $result = self::cleanUpResponse($result);
        if (count($result) == 1) {
            return new GetResponse(data: $result[0]);
        }
        return new GetResponse(data: $result);
    }

    /**
     * @return Response
     * @throws RPCException
     */
    #[OA\Post(path: '/api/v4/call/dry', operationId: 'postCallDry', description:
        "Dry-run an RPC call to infer and store input/output types. Dry-runs do not modify the database. 
        Run this before requesting TypeScript types from /api/v4/interfaces.",
        tags: ['Methods'])]
    #[OA\RequestBody(description: 'RPC method call', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Call"))]
    #[OA\Response(response: 200, description: 'Ok', content: [new OA\MediaType('application/json'), new OA\MediaType('application/gpx'), new OA\MediaType('application/octet-stream')])]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json', 'application/json-rpc'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_dry(): Response
    {
        return $this->post_index();
    }

    /**
     * Cleans up the given response array by removing specific keys from its structure.
     *
     * @param array $response The response array to be cleaned.
     *
     * @return array The cleaned response array with unnecessary keys removed.
     */
    static private function cleanUpResponse(array $response): array
    {
        foreach ($response as $key => $res) {
            unset($response[$key]['result']['success']);
            unset($response[$key]['result']['id']);
            unset($response[$key]['result']['_auth_check']);
            unset($response[$key]['result']['_request']);
            unset($response[$key]['result']['_peak_memory_usage']);
            unset($response[$key]['result']['filters']);
            unset($response[$key]['method']);
            unset($response[$key]['type_hints']);
            unset($response[$key]['params']);
        }
        return $response;
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
            throw new RPCException("Invalid Request", -32600, null, $e->getMessage(), $decodedBody->id ?? null);
        }
    }

    private function extractSchema(array $res): mixed
    {
        return $res['returning']['schema'] ?? $res['schema'] ?? null;
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
    public function options_dry(): void
    {
    }
}
