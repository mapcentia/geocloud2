<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\models\Database;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Table as TableModel;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use OpenApi\Attributes as OA;
use sad_spirit\pg_builder\converters\TypeNameNodeHandler;
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
class Schema extends AbstractApi
{

    private Database $schemaObj;

    /**
     * @throws Exception
     */
    public function __construct()
    {

    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}', operationId: 'getSchema', description: "Get schema", tags: ['Schema'])]
    #[OA\Parameter(name: 'name', description: 'Schema name', in: 'path', required: false, example: 'my_schema')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Schema"))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $schemas = $this->schemaObj->listAllSchemas()["data"];
        $response = [];
        if ($this->jwt['superUser'] && empty($this->schema)) {
            foreach ($schemas as $schema) {
                $name = $schema["schema"];
                $response["schemas"][] = [
                    "name" => $name,
                    "tables" => Table::getTables($name),
                ];
            }
            return $response;
        } else {
            $r = [];
            foreach ($this->schema as $schema) {
                $r[] = [
                    "name" => $schema,
                    "tables" => Table::getTables($schema),
                ];
            }
            if (count($r) == 0) {
                throw new GC2Exception("No schemas found", 404, null, 'NO_SCHEMAS');
            } elseif (count($r) == 1) {
                return $r[0];
            } else {
                return ["schemas" => $r];
            }
        }
    }

    /**
     * @return array
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
    public function post_index(): array
    {
        if (!$this->jwt['superUser']) {
            throw new GC2Exception("", 403);
        }
        // Throw exception if tried with schema resource
        if (!empty(Route2::getParam("schema"))) {
            $this->postWithResource();
        }
        $body = Input::getBody();
        $data = json_decode($body);
        $this->table[0] = new TableModel(null);
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
            // Add tables
            if (!empty($data->tables)) {
                foreach ($data->tables as $table) {
                    Table::addTable($this->table[0], $table, $this);
                }
            }
        }
        $this->table[0]->commit();
        header("Location: /api/v4/schemas/" . implode(",", $list));
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}', operationId: 'patchSchema', description: "Rename schema", tags: ['Schema'])]
    #[OA\Parameter(name: 'name', description: 'Schema name', in: 'path', required: false, example: 'my_schema')]
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
    public function patch_index(): array
    {
        if (!$this->jwt['superUser']) {
            throw new GC2Exception("", 403);
        }
        $body = Input::getBody();
        $data = json_decode($body);
        $r = $this->schemaObj->renameSchema($this->schema[0], $data->name);
        header("Location: /api/v4/schemas/{$r['data']['name']}");
        $res["code"] = "303";
        return $res;
    }

    /**
     * @return array
     * @throws GC2Exception
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}', operationId: 'deleteSchema', description: "Delete schema", tags: ['Schema'])]
    #[OA\Parameter(name: 'name', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Response(response: 204, description: 'Schema deleted')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): array
    {
        if (!$this->jwt['superUser']) {
            throw new GC2Exception("", 403);
        }
        $this->schemaObj->connect();
        $this->schemaObj->begin();
        foreach ($this->schema as $schema) {
            $this->schemaObj->deleteSchema($schema, false);
        }
        $this->schemaObj->commit();
        $res["code"] = "204";
        return $res;
    }

    /**
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $schema = Route2::getParam("schema");
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
        if (!empty($body)) {
            $this->validateRequest($collection, $body, 'schemas');
        }


        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, null, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
        $this->schemaObj = new Database();
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
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\NotBlank(),
                    Table::getAssert(),
                ]),
            ]),
        ]);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }
}
