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
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Database;
use app\inc\Input;
use app\inc\Route2;
use app\models\Table as TableModel;
use Exception;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Schema",
    required: ["name"],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name of the column",
            description: "Name of the column",
            type: "string",
            example: "my-schema",
        ),
        new OA\Property(
            property: "tables",
            title: "Tables",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Table"),
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/[schema]', scope: Scope::SUPER_USER_ONLY)]
class Schema extends AbstractApi
{

    private Database $schemaObj;

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'schemas';
    }

    /**
     * @return Response
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}', operationId: 'getSchema', description: "Get schema", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: false, example: 'my_schema')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Schema"),
        links: [
            new OA\Link(
                link: "getTableLink",
                operationId: "getTable",
                parameters: [
                    "schema" => '$request.path.schema'
                ],
                description: "Link to tables in schema."
            )
        ])]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $schemas = $this->schemaObj->listAllSchemas()["data"];
        $response = [];

        if ($this->route->jwt['data']['superUser'] && empty($this->schema)) {
            foreach ($schemas as $schema) {
                $name = $schema["schema"];
                $links = [
                    'tables' => '/api/v4/schemas/' . $name . '/tables',
                    'sequences' => '/api/v4/schemas/' . $name . '/sequences',
                ];
                $t = [
                    'name' => $name,
                ];
                if (Input::get('namesOnly') === null) {
                    $t['tables'] = Table::getTables($name, $this);
                    $t['sequences'] = Sequence::getSequences($this->table[0], $schema);
                }
                $t['_links'] = $links;
                $response[] = $t;
            }
            return $this->getResponse(data: $response);
        } else {
            $r = [];
            foreach ($this->schema as $schema) {
                $links = [
                    'tables' => '/api/v4/schemas/' . $schema . '/tables',
                    'sequences' => '/api/v4/schemas/' . $schema . '/sequences',
                ];
                $t = [
                    'name' => $schema,
                ];
                if (Input::get('namesOnly') === null) {
                    $t['tables'] = Table::getTables($schema, $this);
                    $t['sequences'] = Sequence::getSequences($this->table[0], $schema);
                }
                $t['_links'] = $links;
                $r[] = $t;
            }
            return $this->getResponse($r);
        }
    }

    /**
     * @return Response
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/schemas', operationId: 'postSchema', description: "Create schema", tags: ['Schema'])]
    #[OA\RequestBody(description: 'New schema', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Schema"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        if (!$this->route->jwt['data']['superUser']) {
            throw new GC2Exception("Only superusers can create schemas", 403);
        }

        $body = Input::getBody();
        $data = json_decode($body);
        $this->table[0] = new TableModel(null, connection: $this->connection);
        $this->table[0]->begin();
        $list = [];

        if (isset($data->schemas)) {
            foreach ($data->schemas as $datum) {
                $this->table[0]->postgisschema = $datum->name;
                $r = $this->schemaObj->createSchema($datum->name, $this->table[0]);
                $list[] = $r['schema'];
                // Add tables
                if (!empty($datum->tables)) {
                    foreach ($datum->tables as $table) {
                        Table::addTable($this->table[0], $table, $this);
                    }
                }
            }
        } else {
            $this->table[0]->postgisschema = $data->name;
            $r = $this->schemaObj->createSchema($data->name, $this->table[0]);
            $list[] = $r['schema'];

            // Add sequences and defer "OWNED BY" to after creation of tables
            if (!empty($data->sequences)) {
                foreach ($data->sequences as $sequence) {
                    Sequence::addSequence($this->table[0], $data->name, (array)$sequence, false);
                }
            }
            // Add tables
            if (!empty($data->tables)) {
                foreach ($data->tables as $table) {
                    Table::addTable($this->table[0], $table, $this);
                }
            }
            // Alter sequences so "OWNED BY" is set after creation of tables
            if (!empty($data->sequences)) {
                foreach ($data->sequences as $sequence) {
                    Sequence::alterSequence($this->table[0], $data->name, (array)$sequence, false);
                }
            }
        }
        $this->table[0]->commit();
        $baseUri = "/api/v4/schemas/";
        return $this->postResponse($baseUri, $list);
    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}', operationId: 'patchSchema', description: "Rename schema", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: false, example: 'my_schema')]
    #[OA\RequestBody(description: 'Update schema', required: true, content: new OA\JsonContent(
        allOf: [
            new OA\Schema(
                required: ["name"],
                properties: [
                    new OA\Property(property: "name", description: "New name of schema", type: "string", example: "my_schema_with_new_name")
                ]
            )
        ]
    ))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        if (!$this->route->jwt['data']['superUser']) {
            throw new GC2Exception("", 403);
        }
        $body = Input::getBody();
        $data = json_decode($body);
        $r = $this->schemaObj->renameSchema($this->schema[0], $data->name);
        return $this->patchResponse('/api/v4/schemas/', [$r['data']['name']]);
    }

    /**
     * @return Response
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}', operationId: 'deleteSchema', description: "Delete schema", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Response(response: 204, description: 'Schema deleted')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): Response
    {
        if (!$this->route->jwt['data']['superUser']) {
            throw new GC2Exception("", 403);
        }
        $this->schemaObj->connect();
        $this->schemaObj->begin();
        foreach ($this->schema as $schema) {
            $this->schemaObj->deleteSchema($schema, false);
        }
        $this->schemaObj->commit();
        return $this->deleteResponse();
    }

    /**
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $schema = $this->route->getParam("schema");
        $body = Input::getBody();

        // Patch and delete on schema collection is not allowed
        if (empty($schema) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("Patch and delete on schema collection is not allowed", 400);
        }
        if (!empty($schema) && count(explode(',', $schema)) > 1 && Input::getMethod() == 'patch') {
            throw new GC2Exception("Patch with multiple schemas is not allowed", 400);
        }
        // Throw exception if tried with schema resource
        if (Input::getMethod() == 'post' && $schema) {
            $this->postWithResource();
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, Input::getMethod());

        $this->initiate(schema: $schema);
        $this->schemaObj = new Database(connection: $this->connection);
    }

    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'name' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'tables' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\NotBlank(),
                    Table::getAssert(),
                ]),
            ]),
            'sequences' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\NotBlank(),
                    Sequence::getAssert(),
                ]),
            ]),
        ]);
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
