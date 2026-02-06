<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\Response as ApiResponse;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\exceptions\GraphQLException;
use app\inc\Connection;
use app\inc\GraphQL as _GraphQl;
use app\inc\Input;
use app\inc\Route2;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Minimal GraphQL endpoint for Postgres.
 *
 * This implementation supports a constrained subset of queries and translates them to
 * existing REST/model functionality. It is intended as a lightweight starting point.
 *
 * Supported root fields (examples):
 * 1) getTables(schema: "public", namesOnly: true)
 *    query { getTables(schema: "public", namesOnly: true) }
 *
 * 2) getTable(schema: "public", name: "my_table")
 *    query { getTable(schema: "public", name: "my_table") }
 *
 * 3) getRows(schema: "public", table: "my_table", limit: 100, offset: 0, where: {"status": "active"})
 *    - where supports simple equality filters only (column = value)
 *
 * 4) Dynamic table field selection (list) and single by-id query:
 *    - query { getUser(schema: "public") { id } } // list rows, only id column
 *    - query { getUser(schema: "my", where: {"status":"active"}, limit: 10) { id name } }
 *    - query { getUser(id: 5) { id name } }       // selects row from public.user with primary key = 5
 *    - query { getUser(id: $i) { id name } }      // same, using variables: { "variables": { "i": 5 } }
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "GraphQL",
    description: "The GraphQL API allows you to query and manipulate your database data using a dynamically generated schema. Each table in your schema is automatically mapped to GraphQL types, queries, and mutations.",
    required: ["query"],
    properties: [
        new OA\Property(
            property: "query",
            title: "Query",
            description: "GraphQL query. Both query and mutation operations are supported. Queries retrieve data, while mutations modify data.",
            type: "string",
            example: "query { ... }",
        ),
        new OA\Property(
            property: "variables",
            title: "Variables",
            description: "Variables for the GraphQL query. Should be a JSON object with variable names as keys and their values as values.",
            type: "object",
            example: ["id" => 1],
        ),
        new OA\Property(
            property: "operationName",
            title: "Operation name",
            description: "Name of the operation to execute. Useful when a query contains multiple operations.",
            type: "string",
            example: "Artists",
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['POST', 'OPTIONS'])]
#[Controller(route: 'api/graphql/schema/{schema}', scope: Scope::SUB_USER_ALLOWED)]
class Graphql extends AbstractApi
{
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct(connection: $connection);
        $this->resource = 'graphql';
    }

    /**
     * GraphQL POST endpoint.
     * Accepts a JSON body: { "query": string, "variables": object|null }
     *
     * @return ApiResponse
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws GraphQLException
     */
    #[OA\Post(path: '/api/graphql/{schema}', operationId: 'postGraphQL', description: "Run GraphQL query/mutation", tags: ['GraphQL'])]
    #[OA\RequestBody(description: 'New rule', required: true, content: new OA\JsonContent(ref: "#/components/schemas/GraphQL"))]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Response(response: 200, description: 'Ok')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): ApiResponse
    {
        // Set user and user group
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $user = $this->route->jwt["data"]["uid"];
        $userGroup = $this->route->jwt["data"]["userGroup"];
        $graphQl = new _GraphQl(connection: $this->connection);
        $body = Input::getBody();
        $payload = json_decode($body ?? 'null', true);
        if (!is_array($payload)) {
            throw new GraphQLException('Invalid JSON body', 400);
        }
        $query = $payload['query'] ?? null;
        $variables = $payload['variables'] ?? [];
        $operationName = $payload['operationName'] ?? null;
        $api = new \app\models\Sql(connection: $this->connection);
        $api->begin();
        // Execute the query using webonyx/graphql-php
        $result = $graphQl->run(user: $user, api: $api, query: $query, schema: $this->schema[0], subuser: !$isSuperUser, userGroup: $userGroup, variables: is_array($variables) ? $variables : [], operationName: is_string($operationName) ? $operationName : null);
        $api->commit();

        return new GetResponse(data: $result);
    }

    #[Override]
    public function get_index(): ApiResponse
    {
        // Not used for GraphQL
        return new NoContentResponse();
    }


    #[Override]
    public function put_index(): ApiResponse
    {
        return new NoContentResponse();
    }

    #[Override]
    public function patch_index(): ApiResponse
    {
        return new NoContentResponse();
    }

    #[Override]
    public function delete_index(): ApiResponse
    {
        return new NoContentResponse();
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GraphQLException
     */
    #[Override]
    public function validate(): void
    {
        $schema = $this->route->getParam("schema");
        $collection = $this->getAssert();
        try {
            $this->validateRequest($collection, Input::getBody(), Input::getMethod());
            $this->initiate(schema: $schema);
        } catch (GC2Exception $e) {
            throw new GraphQLException($e->getMessage());
        }

    }

    private function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'query' => new Assert\Optional(new Assert\Type('string')),
                'variables' => new Assert\Optional(new Assert\Type('array')),
                'operationName' => new Assert\Optional(new Assert\Type('string')),
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => true,
        ]);
    }
}
