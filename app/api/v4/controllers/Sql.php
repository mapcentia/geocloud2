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
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Statement;
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
    schema: "Sql",
    required: [],
    properties: [
        new OA\Property(
            property: "q",
            title: "Query",
            description: "SQL statement. SELECT, INSERT, UPDATE or DELETE",
            type: "string",
            example: "SELECT :my_date::date as my_date",
        ),
        new OA\Property(
            property: "params",
            title: "Parameters",
            description: "Parameters for prepared statements.",
            type: "array",
            items: new OA\Items(type: "object"),
            example: ["my_date" => "2011 04 01"],
        ),
        new OA\Property(
            property: "type_hints",
            title: "Type hints",
            description: "For JSON represented parameters which are not of JSON type.",
            type: "object",
            example: ["my_date" => "date"],
        ),
        new OA\Property(
            property: "type_formats",
            title: "Type formats",
            description: "Formats for types (like date formatting)",
            type: "object",
            example: ["my_date" => "Y m d"],
        ),
        new OA\Property(
            property: "output_format",
            title: "Output format",
            description: "The wanted output format.",
            type: "string",
            default: "json",
            example: "csv",
        ),
        new OA\Property(
            property: "srs",
            title: "Spatial reference system",
            description: "The spatial reference system to use for PostGIS geometry columns. EPSG code",
            type: "integer",
            default: 4326,
            example: 25832,
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
        $this->sqlApi = new \app\models\Sql(connection: $connection);
    }

    public function get_index(): Response
    {
        // TODO: Implement get_index() method.
    }

    /**
     * @return Response
     * @throws PhpfastcacheInvalidArgumentException|GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/sql', operationId: 'postSql', description: "Run SQL statements", tags: ['Sql'])]
    #[OA\RequestBody(description: 'Sql statement to run', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Sql"))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 201, description: 'Insert/update a JSON-RPC method')]
    #[OA\Response(response: 500, description: 'Internal error. Most like an SQL error.')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        // If no token is provided and /api/v4/sql/database/{database} is used,
        // then check if the default user is set
        try {
            $isSuperUser = $this->route->jwt["data"]["superUser"];
            $user =  $this->route->jwt["data"]["uid"];
            $userGroup =  $this->route->jwt["data"]["userGroup"];
        } catch (Exception) {
            $database = func_get_arg(0);
            $userObj = new \app\models\User(null, $database);
            $defaultUser = $userObj->getDefaultUser();
            $user = $defaultUser['screenname'];
            $userGroup = $defaultUser['usergroup'];;
            $isSuperUser = false;
        }
        $decodedBody = json_decode(Input::getBody(), true);
        if (!array_is_list($decodedBody)) {
            $decodedBody = [$decodedBody];
        }
        $result = [];
        // Execute SQL statements
        $this->sqlApi->begin();
        foreach ($decodedBody as $query) {
            $srs = $query['srs'] ?? 4326;
            $this->sqlApi->setSRS($srs);
            $query['srs'] = $srs;
            $result[] = $this->runStatement($query, $user, $isSuperUser, $userGroup);
        }
        // Return response
        $this->sqlApi->commit();
        if (count($result) == 1) {
            $result = $result[0];
        }
        return new GetResponse(data: $result);
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function runStatement(array $query, string $user, bool $isSuperUser, ?string $userGroup): array
    {
        $statement = new Statement(connection: $this->connection, convertReturning: true);
        $query['convert_types'] = $value['convert_types'] ?? true;
        $query['format'] = $body['output_format'] ?? 'json';
        $result = $statement->run(user: $user, api: $this->sqlApi, query: $query, subuser:  !$isSuperUser, userGroup: $userGroup);
        if (isset($query['id'])) {
            $result['id'] = $query['id'];
        }
        unset($result['success']);
        unset($result['forGrid']);
        return $result;
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
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/sql/database/{database}', operationId: 'postSqlNoToken', description: "Run SQL statements without token", tags: ['Sql'])]
    #[OA\Parameter(name: 'database', description: 'Database to use', in: 'path', required: false, example: 'mydb')]
    #[OA\RequestBody(description: 'Sql statement to run', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Sql"))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\MediaType('application/json'))]
    #[OA\Response(response: 500, description: 'Internal error. Most like an SQL error.')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function post_database(): Response
    {
        return $this->post_index($this->route->getParam('database'));
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
