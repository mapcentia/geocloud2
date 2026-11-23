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
use app\inc\snapshot\SnapshotAuthorizer;
use app\opensearch\Client;
use app\opensearch\OpenSearchException;
use OpenApi\Attributes as OA;

/**
 * v4 Search API: full-text search on a table/view through its OpenSearch index.
 *
 * GET/POST search the index (sub users allowed, subject to the schema/relation read check),
 * PUT (re)builds the index from the relation and DELETE drops it (database owner only).
 * Documents are indexed GeoJSON-like, so attribute fields are addressed as `properties.<column>`;
 * text columns also get a `properties.<column>.keyword` multi-field.
 * The mapping is derived from the column types; per-column overrides (type, analyzer,
 * search_analyzer, fields, ...) are set in the relation's `elasticsearch` metadata via
 * PATCH /api/v4/meta, and custom analyzers are defined in PUT /api/v4/search/settings.
 */
#[OA\Schema(
    schema: "SearchQuery",
    description: "An OpenSearch query DSL body. Passed to OpenSearch _search unaltered, so any _search body is accepted; the common members are listed here.",
    properties: [
        new OA\Property(property: "query", title: "Query", description: "OpenSearch query DSL. Attribute fields are addressed as 'properties.<column>'.", type: "object", example: ["match" => ["properties.name" => "Findme"]]),
        new OA\Property(property: "size", title: "Size", description: "Max number of hits to return.", type: "integer", default: 10, example: 20),
        new OA\Property(property: "from", title: "From", description: "Offset of the first hit (paging).", type: "integer", default: 0, example: 0),
        new OA\Property(property: "sort", title: "Sort", description: "Sort clauses.", type: "array", items: new OA\Items(), example: [["properties.name.keyword" => "asc"]]),
        new OA\Property(property: "_source", title: "Source filter", description: "Restrict the returned _source fields, or false to omit _source.", oneOf: [new OA\Schema(type: "boolean"), new OA\Schema(type: "array", items: new OA\Items(type: "string"))], example: ["properties.name"]),
        new OA\Property(property: "aggs", title: "Aggregations", description: "OpenSearch aggregations.", type: "object"),
        new OA\Property(property: "highlight", title: "Highlight", description: "OpenSearch highlighting.", type: "object"),
    ],
    type: "object",
    additionalProperties: true
)]
#[OA\Schema(
    schema: "SearchResult",
    description: "The OpenSearch _search response, returned unaltered.",
    properties: [
        new OA\Property(property: "took", title: "Took", description: "Search time in milliseconds.", type: "integer", example: 3),
        new OA\Property(property: "timed_out", title: "Timed out", type: "boolean", example: false),
        new OA\Property(property: "_shards", title: "Shards", type: "object"),
        new OA\Property(
            property: "hits",
            title: "Hits",
            properties: [
                new OA\Property(property: "total", type: "object", properties: [
                    new OA\Property(property: "value", type: "integer", example: 1),
                    new OA\Property(property: "relation", type: "string", example: "eq"),
                ]),
                new OA\Property(property: "max_score", type: ["number", "null"], example: 0.2876821),
                new OA\Property(property: "hits", description: "The matching documents. _source holds the GeoJSON-like document with 'properties' and 'geometry'.", type: "array", items: new OA\Items(properties: [
                    new OA\Property(property: "_index", type: "string", example: "mydb_my_schema_my_table"),
                    new OA\Property(property: "_id", type: "string", example: "1"),
                    new OA\Property(property: "_score", type: ["number", "null"], example: 0.2876821),
                    new OA\Property(property: "_source", type: "object"),
                ], type: "object")),
            ],
            type: "object"
        ),
        new OA\Property(property: "aggregations", title: "Aggregations", description: "Present when the query has aggs.", type: "object"),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "SearchIndexBuildResult",
    description: "Result of (re)building a search index.",
    properties: [
        new OA\Property(property: "index", title: "Index", description: "OpenSearch index name: <database>_<schema>_<table>.", type: "string", example: "mydb_my_schema_my_table"),
        new OA\Property(property: "message", title: "Message", description: "Indexing summary.", type: ["string", "null"], example: "Indexed 42 documents"),
        new OA\Property(property: "errors", title: "Errors", description: "True if OpenSearch reported errors for some documents.", type: "boolean", example: false),
        new OA\Property(property: "errors_in", title: "Errors in", description: "Per-document bulk indexing errors.", type: "array", items: new OA\Items(type: "object")),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "SearchIndexDropResult",
    description: "Result of dropping a search index.",
    properties: [
        new OA\Property(property: "index", title: "Index", description: "OpenSearch index name.", type: "string", example: "mydb_my_schema_my_table"),
        new OA\Property(property: "dropped", title: "Dropped", type: "boolean", example: true),
    ],
    type: "object"
)]
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
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'search', description: "Search the relation's OpenSearch index. The query string is passed to OpenSearch _search (URI search) unaltered, so any _search URI parameter works; the common ones are listed. A JSON body, if sent, takes precedence over the query string. Sub users need a read privilege on the relation and are refused if a geofence deny or limit rule matches them, since the index cannot apply row filters.", tags: ['Search'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table or view name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'q', description: "Lucene query string. Attribute fields are addressed as 'properties.<column>'.", in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'properties.name:Findme')]
    #[OA\Parameter(name: 'size', description: 'Max number of hits to return.', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 10), example: 20)]
    #[OA\Parameter(name: 'from', description: 'Offset of the first hit (paging).', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 0), example: 0)]
    #[OA\Parameter(name: 'sort', description: "Comma-separated '<field>:<asc|desc>' pairs.", in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'properties.name.keyword:asc')]
    #[OA\Parameter(name: '_source', description: 'Comma-separated list of _source fields to return, or false to omit _source.', in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'properties.name')]
    #[OA\Parameter(name: 'default_operator', description: "Default operator for 'q'.", in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'OR', enum: ['AND', 'OR']), example: 'AND')]
    #[OA\Parameter(name: 'df', description: "Default field for 'q' when no field prefix is given.", in: 'query', required: false, schema: new OA\Schema(type: 'string'), example: 'properties.name')]
    #[OA\Response(response: 200, description: 'OpenSearch response', content: new OA\JsonContent(ref: "#/components/schemas/SearchResult"))]
    #[OA\Response(response: 400, description: 'Invalid query (SEARCH_ERROR)')]
    #[OA\Response(response: 403, description: 'No read privilege on the relation (INSUFFICIENT_PRIVILEGES), or a geofence deny/limit rule matches the caller (GEOFENCE_RULES_APPLY)')]
    #[OA\Response(response: 404, description: 'Relation not found, or no search index has been built for it (INDEX_NOT_FOUND)')]
    public function get_index(): Response
    {
        $this->assertCanSearch();
        $query = Input::getBody() ?: (Input::getQueryString() ?: '');
        return $this->runSearch($query, (bool)Input::getBody());
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'searchPost', description: "Search the relation's OpenSearch index with a query DSL body. The body is passed to OpenSearch _search unaltered. Sub users need a read privilege on the relation and are refused if a geofence deny or limit rule matches them, since the index cannot apply row filters.", tags: ['Search'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table or view name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\RequestBody(description: 'OpenSearch query DSL.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/SearchQuery"))]
    #[AcceptableContentTypes(['application/json'])]
    #[OA\Response(response: 200, description: 'OpenSearch response', content: new OA\JsonContent(ref: "#/components/schemas/SearchResult"))]
    #[OA\Response(response: 400, description: 'Invalid query (SEARCH_ERROR)')]
    #[OA\Response(response: 403, description: 'No read privilege on the relation (INSUFFICIENT_PRIVILEGES), or a geofence deny/limit rule matches the caller (GEOFENCE_RULES_APPLY)')]
    #[OA\Response(response: 404, description: 'Relation not found, or no search index has been built for it (INDEX_NOT_FOUND)')]
    public function post_index(): Response
    {
        $this->assertCanSearch();
        return $this->runSearch((string)Input::getBody(), true);
    }

    /**
     * Sub users need a read privilege on the relation, and are refused when a
     * geofence deny/limit rule matches them: the index holds every row and
     * OpenSearch cannot apply the rule's row filter (same policy as snapshots).
     *
     * @throws GC2Exception 403 INSUFFICIENT_PRIVILEGES or 403 GEOFENCE_RULES_APPLY
     */
    private function assertCanSearch(): void
    {
        new SnapshotAuthorizer($this->connection)
            ->assertCanRead($this->route->jwt['data'], $this->schemaName, $this->tableName, 'the search index');
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

    /**
     * @throws GC2Exception
     */
    #[OA\Put(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'buildSearchIndex', description: "(Re)build the OpenSearch index from the table/view: drops any existing index, creates it with the database's analysis settings (see /api/v4/search/settings) and a mapping derived from the columns, with per-column overrides (type, analyzer, search_analyzer, ...) taken from the relation's 'elasticsearch' metadata (PATCH /api/v4/meta), bulk indexes all rows and refreshes the index so it is searchable immediately. The relation must have a primary key. No request body. Database owner only.", tags: ['Search'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table or view name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Response(response: 200, description: 'Index built', content: new OA\JsonContent(ref: "#/components/schemas/SearchIndexBuildResult"))]
    #[OA\Response(response: 400, description: 'The relation has no primary key, or OpenSearch rejected the index (INDEX_BUILD_ERROR)')]
    #[OA\Response(response: 403, description: 'Caller is not the database owner (NOT_OWNER)')]
    #[OA\Response(response: 404, description: 'Relation not found')]
    public function put_index(): Response
    {
        $this->assertOwner();
        $db = $this->route->jwt['data']['database'];
        $index = $this->indexName();
        $fullTable = "$this->schemaName.$this->tableName";

        // Elasticsearch::createMapFromTable() and Sql_to_es both fall back to the process-global
        // DB context (app\conf\Connection::$param['postgisdb']) instead of the request-scoped
        // Connection AbstractApi threads everywhere else, because neither takes a Connection
        // param. Point that global at the JWT database before using them — each request runs in
        // its own php-fpm process here, so this doesn't leak across requests.
        \app\models\Database::setDb($db);

        // Compose settings (default + optional per-db analysis) and mapping from the table.
        $analysis = (new \app\models\Setting($this->connection))->getSearchAnalysis();
        $body = \app\opensearch\SettingsComposer::compose($analysis);
        $body['mappings'] = (new \app\models\Elasticsearch())->createMapFromTable($fullTable, modern: true);

        $client = new Client();
        try {
            if ($client->indexExists($index)) {
                $client->deleteIndex($index);
            }
            $client->createIndex($index, $body);
        } catch (OpenSearchException $e) {
            throw $this->mapOpenSearchException($e, "INDEX_BUILD_ERROR");
        }

        // Bulk index rows through the reused Sql_to_es indexer.
        $priObj = $this->table[0]->getPrimeryKey($fullTable);
        $priKey = $priObj['attname'] ?? null;
        if (!$priKey) {
            throw new GC2Exception("The relation has no primary key", 400, null, "INDEX_BUILD_ERROR");
        }
        $api = new \app\models\Sql_to_es("4326");
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        $res = $api->runSql("SELECT * FROM \"$this->schemaName\".\"$this->tableName\"", $this->schemaName, $this->tableName, $priKey, $db);
        if (empty($res['success'])) {
            throw new GC2Exception($res['message'] ?? "Bulk indexing failed", 400, null, "INDEX_BUILD_ERROR");
        }

        // Make freshly indexed documents immediately searchable for callers that build then search.
        $client->refresh($index);

        return $this->getResponse([
            'index' => $index,
            'message' => $res['message'] ?? null,
            'errors' => $res['errors'] ?? false,
            'errors_in' => $res['errors_in'] ?? [],
        ]);
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/search', operationId: 'dropSearchIndex', description: "Drop the relation's OpenSearch index. The table itself is not touched. Database owner only.", tags: ['Search'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table or view name', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Response(response: 200, description: 'Index dropped', content: new OA\JsonContent(ref: "#/components/schemas/SearchIndexDropResult"))]
    #[OA\Response(response: 403, description: 'Caller is not the database owner (NOT_OWNER)')]
    #[OA\Response(response: 404, description: 'Relation or index not found (INDEX_DROP_ERROR)')]
    public function delete_index(): Response
    {
        $this->assertOwner();
        $client = new Client();
        try {
            $client->deleteIndex($this->indexName());
        } catch (OpenSearchException $e) {
            throw $this->mapOpenSearchException($e, "INDEX_DROP_ERROR");
        }
        return $this->getResponse(['index' => $this->indexName(), 'dropped' => true]);
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}
