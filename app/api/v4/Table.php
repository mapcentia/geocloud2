<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Model;
use app\models\Layer;
use app\models\Table as TableModel;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use stdClass;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Table",
    required: ["name"],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name of the table",
            description: "Name of the table",
            type: "string",
            example: "my-column",
        ),
        new OA\Property(
            property: "columns",
            title: "Columns",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Column"),
        ),
        new OA\Property(
            property: "indices",
            title: "Indices",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Index"),
        ),
        new OA\Property(
            property: "constraints",
            title: "Constraints",
            type: "array",
            items: new OA\Items(ref: "#/components/schemas/Constraint"),
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
class Table extends AbstractApi
{
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
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}', operationId: 'getTable', description: "Get table", tags: ['Table'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: false, example: 'my_table')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Table"),
        links: [
            new OA\Link(
                link: "getColumn",
                operationId: "getColumn",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table " => '$request.path.table',
                ],
                description: "Link to columns."
            ),
            new OA\Link(
                link: "getConstraint",
                operationId: "getConstraint",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table " => '$request.path.table',
                ],
                description: "Link to constraints."
            ),
            new OA\Link(
                link: "getIndex",
                operationId: "getIndex",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table " => '$request.path.table',
                ],
                description: "Link to indices."
            ),
            new OA\Link(
                link: "getPrivileges",
                operationId: "getPrivileges",
                parameters: [
                    "schema" => '$request.path.schema',
                    "table " => '$request.path.table',
                ],
                description: "Link to privileges."
            )
        ])]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $r = [];
        if (!empty($this->qualifiedName)) {
            for ($i = 0; sizeof($this->qualifiedName) > $i; $i++) {
                $r[] = self::getTable($this->table[$i]);
            }
        } else {
            $r = self::getTables($this->schema[0]);
        }
        if (count($r) == 0) {
            throw new GC2Exception("No tables found in schema", 404, null, 'NO_TABLES');
        } elseif (count($r) == 1) {
            return $r[0];
        } else {
            return ["tables" => $r];
        }
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables', operationId: 'postTable', description: "Create table", tags: ['Table'])]
    #[OA\Parameter(name: 'name', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\RequestBody(description: 'New table', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Table"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $this->table[0] = new TableModel(null);
        $this->table[0]->postgisschema = $this->schema[0];
        $this->table[0]->begin();
        $list = [];

        if (isset($data->tables)) {
            foreach ($data->tables as $datum) {
                $r = self::addTable($this->table[0], (object)$datum, $this);
                $list[] = $r['tableName'];
            }
        } else {
            $r = self::addTable($this->table[0], (object)$data, $this);
            $list[] = $r['tableName'];
        }
        $this->table[0]->commit();
        header("Location: /api/v4/schemas/{$this->schema[0]}/tables/" . implode(",", $list));
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}', operationId: 'patchTable', description: "Update table", tags: ['Table'])]
    #[OA\Parameter(name: 'name', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\RequestBody(description: 'Update table', required: true, content: new OA\JsonContent(
        allOf: [
            new OA\Schema(
                properties: [
                    new OA\Property(property: "name", description: "New name of table", type: "string", example: "my_table_with_new_name"),
                    new OA\Property(property: "schema", description: "Move table to schema", type: "string", example: "my_other_schema"),
                ]
            )
        ]
    ))]
    #[OA\Response(response: 204, description: 'Table updated')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): array
    {
        $layer = new Layer();
        $layer->begin();
        $body = Input::getBody();
        $data = json_decode($body);
        $r = [];
        for ($i = 0; sizeof($this->unQualifiedName) > $i; $i++) {
            if (isset($data->name) && $data->name != $this->unQualifiedName[$i]) {
                $arg = new stdClass();
                $arg->name = $data->name;
                $r[] = $layer->rename($this->qualifiedName[$i], $arg)['name'];
            }
            if (isset($data->schema) && $data->schema != $this->schema[0]) {
                if (!$this->jwt['superUser']) {
                    throw new GC2Exception('Only super user can move tables between schemas');
                }
                $layer->setSchema([(isset($r[$i]) ? ($this->schema[0] . '.' . $r[$i]) : $this->qualifiedName[$i])], $data->schema);
            }
            // Set comment
            if (property_exists($data, 'comment')) {
                $layer->table = $this->qualifiedName[$i];
                $layer->setTableComment($data->comment);
            }
        }
        $schema = $data->schema ?? $this->schema[0];
        $layer->commit();
        header("Location: /api/v4/schemas/{$schema}/tables/" . (count($r) > 0 ? implode(',', $r) : implode(',', $this->unQualifiedName)));
        return ["code" => "303"];
    }


    /**
     * @return array
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}', operationId: 'deleteTable', description: "Delete table", tags: ['Table'])]
    #[OA\Parameter(name: 'name', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Response(response: 204, description: 'Table deleted')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[Override]
    public function delete_index(): array
    {
        $this->table[0]->begin();
        foreach ($this->table as $t) {
            $t->destroy();
        }
        $this->table[0]->commit();
        return ["code" => "204"];
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public static function addTable(TableModel $table, stdClass $data, AbstractApi $caller): array
    {
        // Load pre extensions and run processAddTable
        $caller->runExtension('processAddTable', $table);

        $r = $table->create($data->name, null, null, true, $data->comment);
        // Add columns
        if (!empty($data->columns)) {
            foreach ($data->columns as $column) {
                Column::addColumn($table, $column->name, $column->type, true, $column->default_value, $column->is_nullable ?? true);
            }
        }
        // Add indices
        if (!empty($data->indices)) {
            foreach ($data->indices as $index) {
                Index::addIndices($table, $index->columns, $index->method, $index->name);
            }
        }
        // Add constraints
        if (!empty($data->constraints)) {
            foreach ($data->constraints as $constraint) {
                Constraint::addConstraint($table, $constraint->constraint, $constraint->columns, $constraint->check, $constraint->name, $constraint->referenced_table, $constraint->referenced_columns);
            }
        }
        return $r;
    }

    /**
     * @param TableModel $table
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getTable(TableModel $table): array
    {
        $columns = Column::getColumns($table);
        $constraints = Constraint::getConstraints($table);
        $indices = Index::getIndices($table);
        $comment = $table->getComment();
        $response['name'] = $table->tableWithOutSchema;
        $response['columns'] = $columns;
        $response['indices'] = $indices;
        $response['constraints'] = $constraints;
        $response['comment'] = $comment;
        $response['links'] = [
            'columns' => "/api/v4/schemas/{$table->schema}/tables/$table->tableWithOutSchema/columns",
            'indices' => "/api/v4/schemas/{$table->schema}/tables/$table->tableWithOutSchema/indices",
            'constraints' => "/api/v4/schemas/{$table->schema}/tables/$table->tableWithOutSchema/constraints",
            'privileges' => "/api/v4/schemas/{$table->schema}/tables/$table->tableWithOutSchema/privileges",
        ];
        return $response;
    }

    /**
     * @param string $schema
     * @return array[]
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getTables(string $schema): array
    {
        $tables = [];
        foreach ((new Model())->getTablesFromSchema($schema) as $name) {
            $tableName = $schema . "." . $name;
            $tables[] = Input::get('namesOnly') !== null ? ['name' => $tableName] : self::getTable(new TableModel($tableName, false, true, false));
        }
        return $tables;
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $body = Input::getBody();
        // Patch and delete on collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("Patch and delete on a table collection is not allowed", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && $table) {
            $this->postWithResource();
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, 'tables', Input::getMethod());

        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }

    /**
     * @return Assert\Collection
     */
    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'name' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'comment' => new Assert\Optional([
                new Assert\Type('string'),
            ]),
            'schema' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'columns' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\NotBlank(),
                    Column::getAssert(),
                ]),
            ]),
            'indices' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\NotBlank(),
                    Index::getAssert(),
                ]),
            ]),
            'constraints' => new Assert\Optional([
                new Assert\Type('array'),
                new Assert\Count(['min' => 1]),
                new Assert\All([
                    new Assert\NotBlank(),
                    Constraint::getAssert(),
                ]),
            ]),
        ]);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }
}
