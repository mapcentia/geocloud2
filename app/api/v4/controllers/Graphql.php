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
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     * @throws GraphQLException
     */
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
