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
use app\inc\Connection;
use app\inc\GraphQl as _GraphQl;
use app\inc\Input;
use app\inc\Route2;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Parser as GraphQLParser;
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
 * 1) tables(schema: "public", namesOnly: true)
 *    query { tables(schema: "public", namesOnly: true) }
 *
 * 2) table(schema: "public", name: "my_table")
 *    query { table(schema: "public", name: "my_table") }
 *
 * 3) rows(schema: "public", table: "my_table", limit: 100, offset: 0, where: {"status": "active"})
 *    - where supports simple equality filters only (column = value)
 *
 * 4) Dynamic table field selection (list) and single by-id query:
 *    - query { user(schema: "public") { id } } // list rows, only id column
 *    - query { user(schema: "my", where: {"status":"active"}, limit: 10) { id name } }
 *    - query { userById(5) { id name } }       // selects row from public.user with primary key = 5
 *    - query { userById(i) { id name } }       // same, using variables: { "variables": { "i": 5 } }
 */
#[AcceptableMethods(['POST', 'OPTIONS'])]
#[Controller(route: 'api/v4/graphql', scope: Scope::SUB_USER_ALLOWED)]
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
     */
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): ApiResponse
    {
        $graphQl = new _GraphQl(connection: $this->connection);

        $body = Input::getBody();
        $payload = json_decode($body ?? 'null', true);
        if (!is_array($payload)) {
            throw new GC2Exception('Invalid JSON body', 400);
        }
        $query = $payload['query'] ?? null;
        $variables = $payload['variables'] ?? [];
        $operationName = $payload['operationName'] ?? null;
        if (!is_string($query) || $query === '') {
            throw new GC2Exception('Missing GraphQL query', 400);
        }
        try {
            $doc = GraphQLParser::parse($query);
        } catch (\Throwable $e) {
            throw new GC2Exception('GraphQL parse error: ' . $e->getMessage(), 400);
        }

        $operation = null;
        foreach ($doc->definitions as $def) {
            if ($def instanceof OperationDefinitionNode) {
                if ($operationName === null || ($def->name?->value === $operationName)) {
                    $operation = $def;
                    if ($operationName !== null) {
                        break;
                    }
                }
            }
        }
        if (!$operation) {
            throw new GC2Exception('No operation found in document', 400);
        }

        // Execute the query using webonyx/graphql-php
        $result = $graphQl->executeQuery($query, is_array($variables) ? $variables : [], is_string($operationName) ? $operationName : null);
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
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        // GraphQL endpoint does not take route parameters; basic body validation only
        $collection = $this->getAssert();
        $this->validateRequest($collection, Input::getBody(), Input::getMethod(), allowPatchOnCollection: false);
    }

    private function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'fields' => [
                'query' => new Assert\Optional(new Assert\Type('string')),
                'variables' => new Assert\Optional(new Assert\Type('array')),
                'operationName' => new Assert\Optional(new Assert\Type('string')),
            ],
            'allowExtraFields' => true,
            'allowMissingFields' => true,
        ]);
    }
}
