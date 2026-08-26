<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\opensearch\Client;
use app\opensearch\OpenSearchException;
use OpenApi\Attributes as OA;

#[AcceptableMethods(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/search', scope: Scope::SUB_USER_ALLOWED)]
class Search extends AbstractApi
{
    private string $schemaName;
    private string $tableName;

    public function __construct(public Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'search';
    }

    private function initParams(): void
    {
        $this->schemaName = (string)$this->route->getParam('schema');
        $this->tableName = (string)$this->route->getParam('table');
    }

    private function indexName(): string
    {
        return $this->route->jwt['data']['database'] . "_" . $this->schemaName . "_" . $this->tableName;
    }

    private function assertOwner(): void
    {
        if (empty($this->route->jwt['data']['superUser'])) {
            throw new GC2Exception("Only the database owner can build or drop a search index", 403, null, "NOT_OWNER");
        }
    }

    private function mapOpenSearchException(OpenSearchException $e, string $code): GC2Exception
    {
        $reason = $e->getBody()['error']['reason'] ?? $e->getMessage();
        return new GC2Exception($reason, $e->getStatus() ?: 400, $e, $code);
    }

    /**
     * @throws GC2Exception
     */
    public function validate(): void
    {
        $this->initParams();
        // Read auth + schema/relation existence (sub-user schema access enforced here).
        $this->initiate(schema: $this->schemaName, relation: $this->tableName);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'search', description: "Search the table's OpenSearch index. Query string or body is passed to OpenSearch _search unaltered.", tags: ['Search'])]
    #[OA\Response(response: 200, description: 'OpenSearch response')]
    public function get_index(): Response
    {
        $query = Input::getBody() ?: (Input::getQueryString() ?: '');
        return $this->runSearch($query, (bool)Input::getBody());
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'searchPost', description: "Search with an OpenSearch query DSL body.", tags: ['Search'])]
    #[AcceptableContentTypes(['application/json'])]
    #[OA\Response(response: 200, description: 'OpenSearch response')]
    public function post_index(): Response
    {
        return $this->runSearch((string)Input::getBody(), true);
    }

    /**
     * @throws GC2Exception
     */
    private function runSearch(string $query, bool $isBody): Response
    {
        $client = new Client();
        try {
            $result = $client->search($this->indexName(), $query, $isBody);
        } catch (OpenSearchException $e) {
            if ($e->getStatus() === 404) {
                throw new GC2Exception("No search index for this relation. Build it first with PUT.", 404, $e, "INDEX_NOT_FOUND");
            }
            throw $this->mapOpenSearchException($e, "SEARCH_ERROR");
        }
        return $this->getResponse($result);
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Not implemented", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function delete_index(): Response
    {
        throw new GC2Exception("Not implemented", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}
