<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableMethods;
use app\api\v4\Route;
use app\api\v4\Scope;
use app\inc\Connection;
use app\inc\Model;
use app\inc\Route2;
use Exception;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;

#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Stats",
    required: [],
    properties: [
        new OA\Property(
            property: "tables",
            title: "Tables.",
            description: "List of stats for all tables.",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/TableStats"),
        ),
        new OA\Property(
            property: "total_size",
            title: "Total size",
            description: "The total size of all tables in human-readable format.",
            type: "integer",
            example: 722018304,
        ),
        new OA\Property(
            property: "total_size_bytes",
            title: "Total size",
            description: "The total size of all tables in bytes.",
            type: "string",
            example: "689 MB",
        ),
        new OA\Property(
            property: "number_of_tables",
            title: "Number of tables",
            description: "The total of table in the database",
            type: "integer",
            example: 21,
        ),
        new OA\Property(
            property: "cost",
            title: "Cost of queries",
            description: "The cost of queries.",
            type: "number",
            example: 25686.6,
        ),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "TableStats",
    required: [],
    properties: [
        new OA\Property(
            property: "table_name",
            title: "Table name",
            description: "The name of the table.",
            type: "string",
            example: "my_table",
        ),
        new OA\Property(
            property: "schema_name",
            title: "Schema name",
            description: "The name of the schema.",
            type: "string",
            example: "my_schema",
        ),
        new OA\Property(
            property: "total_size",
            title: "Total size",
            description: "The total size of the table including indices in human-readable format.",
            type: "string",
            example: "2728 kB",
        ),
        new OA\Property(
            property: "total_size_bytes",
            title: "Total size in bytes",
            description: "The total size of the table including indices in bytes.",
            type: "integer",
            example: 2793472,
        ),
        new OA\Property(
            property: "table_size",
            title: "Table size",
            description: "The size of the table in human-readable format.",
            type: "string",
            example: "2168 kB",
        ),
        new OA\Property(
            property: "table_size_bytes",
            title: "Table size in bytes",
            description: "The size of the table in bytes.",
            type: "integer",
            example: 2220032,
        ),
        new OA\Property(
            property: "indices_size",
            title: "Indices size",
            description: "The size of the table's indices in human-readable format",
            type: "string",
            example: "520 kB",
        ),
        new OA\Property(
            property: "indices_size_bytes",
            title: "Indices size",
            description: "The size of the table's indices in bytes.",
            type: "integer",
            example: 532480,
        ),
        new OA\Property(
            property: "row_count",
            title: "Row count",
            description: "The number of rows in the table.",
            type: "integer",
            example: 6338,
        ),

    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Route('api/v4/stats')]
#[Scope(['admin'])]
class Stat extends AbstractApi
{
    public function __construct(private readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
    }

    #[OA\Get(path: '/api/v4/stats', operationId: 'getStats', description: "Get statistics", tags: ['Stats'])]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Stats"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        return (new Model(connection: $this->connection))->getStats();
    }

    public function post_index(): array
    {

    }

    public function put_index(): array
    {

    }

    public function delete_index(): array
    {

    }

    public function validate(): void
    {

    }

    public function patch_index(): array
    {
        // TODO: Implement patch_index() method.
    }
}
