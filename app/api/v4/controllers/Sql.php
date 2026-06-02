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
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Statement;
use app\inc\Util;
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
    schema: "SqlRequest",
    description: "Execute SQL statements. Supports SELECT, INSERT, UPDATE, DELETE, and MERGE (no DDL or transaction control).",
    required: ["q"],
    properties: [
        new OA\Property(
            property: "id",
            title: "Id",
            description: "Unique identifier for the request. The server will return the same identifier in the response. 
            Useful for tracking requests in a websocket context. If omitted the server will generate a random UUID in the response.",
            type: "string",
            example: "my_id",
        ),
        new OA\Property(
            property: "q",
            title: "Query",
            description: "SQL statement to run. Allowed: SELECT, INSERT, UPDATE, DELETE, MERGE. Not allowed: DDL, transaction control.",
            type: "string",
            example: "SELECT :my_date::date as my_date",
        ),
        new OA\Property(
            property: "params",
            title: "Parameters",
            description: "Parameters for the statement. For SELECT, only one parameter set is allowed.",
            type: "array",
            items: new OA\Items(type: "object"),
            example: [["my_date" => "2011 04 01"], ["my_string" => "hello world"]],
        ),
        new OA\Property(
            property: "type_hints",
            title: "Type hints",
            description: "Type hints for JSON-encoded parameters that are not JSON/JSONB in the database.",
            type: "object",
            example: ["my_date" => "date"],
        ),
        new OA\Property(
            property: "type_formats",
            title: "Type formats",
            description: "Formatting rules for typed parameters, e.g. date formats.",
            type: "object",
            example: ["my_date" => "Y m d"],
        ),
        new OA\Property(
            property: "output_format",
            title: "Output format",
            description: "Output format. Supported: json, geojson, csv, ccsv, ndjson, excel, or ogr/<format> (e.g. ogr/ESRI Shape). ccsv and ndjson are streamed.",
            type: "string",
            default: "json",
            example: "csv",
        ),
        new OA\Property(
            property: "srs",
            title: "Spatial reference system",
            description: "EPSG code for the spatial reference system used for PostGIS geometry output.",
            type: "integer",
            default: 4326,
            example: 25832,
        ),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "SqlResponse",
    description: "Response from executing SQL statements where output format is set to 'json'. Contains the result of the query and metadata.",
    required: [],
    properties: [
        new OA\Property(
            property: "id",
            title: "Id",
            description: "Unique identifier for the request, which is the same as the one in the request payload.
            Useful for tracking requests in a websocket context.",
            type: "string",
            example: "my_id",
        ),
        new OA\Property(
            property: "statement",
            title: "Statement",
            description: "The actual SQL statement that was executed after access rules was applied.",
            type: "string",
            example: "SELECT :my_date::date as my_date'",
        ),
        new OA\Property(
            property: "affected_rows",
            title: "Affected rows",
            description: "If the query was an INSERT, UPDATE, or DELETE, the number of rows affected.",
            type: "integer",
            example: 1,
        ),
        new OA\Property(
            property: "data",
            title: "Data",
            description: "Rows returned by the query.",
            type: "array",
            items: new OA\Items(type: "object"),
            example: [["my_date" => "2011-04-01", "my_string" => "hello world"]],
        ),
        new OA\Property(
            property: "schema",
            title: "Schema",
            description: "Column names and types of the query result.",
            type: "object",
            example: ["my_date" => ["type" => "date", "array" => false], "my_string" => ["type" => "string", "array" => false]],
            additionalProperties: new OA\AdditionalProperties(ref: "#/components/schemas/SqlSchema"),
        ),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "SqlSchema",
    description: "",
    required: [],
    properties: [
        new OA\Property(
            property: "type",
            title: "Type",
            description: "Type of the column.",
            type: "string",
            example: "date",
        ),
        new OA\Property(
            property: "array",
            title: "Is array",
            description: "Is the column an array?",
            type: "boolean",
            example: false,
        ),
    ],
    type: "object"
)]
#[AcceptableMethods(['POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/sql', scope: Scope::SUB_USER_ALLOWED)]
class Sql extends AbstractApi
{
    private \app\models\Sql $sqlApi;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct(connection: $connection);
        $this->resource = 'sql';
        $this->sqlApi = new \app\models\Sql(connection: $this->connection);
    }

    public function get_index(): Response
    {
        // TODO: Implement get_index() method.
    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/sql', operationId: 'postSql', description: "Execute SQL statements.", tags: ['Sql'])]
    #[OA\RequestBody(description: 'SQL statement(s) to execute.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/SqlRequest"))]
    #[OA\Response(response: 200, description: 'Ok', content: [new OA\MediaType('application/json'), new OA\MediaType('application/gpx'), new OA\MediaType('application/octet-stream')])]
    #[OA\Response(response: 500, description: 'Internal error. Most likely an SQL error.', content: new OA\JsonContent(ref: "#/components/schemas/SqlResponse"))]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', 'application/gpx', 'application/octet-stream', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $isSuperUser = $this->route->jwt["data"]["superUser"];
        $user = $this->route->jwt["data"]["uid"];
        $userGroup = $this->route->jwt["data"]["userGroup"];
        $decodedBody = json_decode(Input::getBody(), true);
        if (!array_is_list($decodedBody)) {
            $decodedBody = [$decodedBody];
        }
        $result = [];
        // Execute SQL statements
        $this->sqlApi->withTransaction(function () use (&$result, $decodedBody, $user, $isSuperUser, $userGroup) {
            foreach ($decodedBody as $query) {
                $srs = $query['srs'] ?? 4326;
                $this->sqlApi->setSRS($srs);
                $query['srs'] = $srs;
                // In REST context the id is set
                if (!isset($query['id'])) {
                    $query['id'] = Util::guid();
                }
                $res = $this->runStatement($query, $user, $isSuperUser, $userGroup);
                if ($res !== null) {
                    $result[] = $res;
                }
            }
        });
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
     * @throws GC2Exception
     */
    private function runStatement(array $query, string $user, bool $isSuperUser, ?string $userGroup): ?array
    {
        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $query['convert_types'] = $value['convert_types'] ?? true;
        $query['format'] = $query['output_format'] ?? 'json';
        $result = $statement->run(user: $user, api: $this->sqlApi, query: $query, subuser: !$isSuperUser, userGroup: $userGroup);
        if (!empty($result)) {
            unset($result['success']);
            unset($result['forGrid']);
        }
        return $result;
    }

    /**
     * Cleans up the given response array by removing unnecessary keys from each element.
     *
     * @param array $response The response array to be cleaned up.
     * Each element is expected to be an associative array containing keys such as 'filters', '_auth_check', '_request', and '_peak_memory_usage'.
     *
     * @return array The cleaned-up response array with specified keys removed from each element.
     */
    static private function cleanUpResponse(array $response): array
    {
        foreach ($response as $key => $res) {
            unset($response[$key]['filters']);
            unset($response[$key]['_auth_check']);
            unset($response[$key]['_request']);
            unset($response[$key]['_peak_memory_usage']);
            if (!empty($response[$key]['returning'])) {
                $response[$key]['data'] = $response[$key]['returning']['data'];
                $response[$key]['schema'] = $response[$key]['returning']['schema'];
            }
            unset($response[$key]['returning']);

        }
        return $response;
    }

    #[Override]
    public function patch_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): Response
    {
        // TODO: Implement delete_index() method.
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $body = Input::getBody();
        if (empty($body) && in_array(Input::getMethod(), ['post', 'patch'])) {
            throw new GC2Exception("POST without request body is not allowed.", 400);
        }
        $decodedBody = json_decode($body);
        if (is_array($decodedBody)) {
            foreach ($decodedBody as $value) {
                $this->validateRequest(self::getAssert(), json_encode($value), Input::getMethod());
            }
        } else {
            $this->validateRequest(self::getAssert(), $body, Input::getMethod());
        }
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }

    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'q' => new Assert\Required(
                new Assert\NotBlank(),
            ),
            'params' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
            ]),
            'type_hints' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
            ]),
            'type_formats' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
            ]),
            'output_format' => new Assert\Optional(
                new Assert\NotBlank(),
            ),
            'format' => new Assert\Optional(
                new Assert\NotBlank(),
            ),
            'convert_types' => new Assert\Optional([
                new Assert\Type('boolean'),
            ]),
            'base64' => new Assert\Optional([
                new Assert\Type('boolean'),
            ]),
            'lifetime' => new Assert\Optional([
                new Assert\Type('integer'),
            ]),
            'srs' => new Assert\Optional(
                new Assert\Type('integer'),
            ),
            'geo_format' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
                new Assert\Choice(choices: ['wkt', 'geojson']),
            ]),
            'id' => new Assert\Optional([
                new Assert\NotBlank(),
            ]),
        ]);
    }
}
