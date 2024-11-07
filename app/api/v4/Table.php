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


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'PUT', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
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
     * @OA\Post(
     *   path="/api/v4/schemas/{schema}/tables",
     *   tags={"Table"},
     *   summary="Create a new table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *   )
     * )
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $this->table[0] = new TableModel(null);
        $this->table[0]->postgisschema = $this->schema;
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
        header("Location: /api/v4/schemas/$this->schema/tables/" . implode(",", $list));
        $res["code"] = "201";
        return $res;
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public static function addTable(TableModel $table, stdClass $data, $caller): array
    {
        // Load pre extensions and run processAddTable
        $caller->runExtension('processAddTable', $table);

        $r = $table->create($data->table, null, null, true);
        // Add columns
        if (!empty($data->columns)) {
            foreach ($data->columns as $column) {
                Column::addColumn($table, $column->column, $column->type, true, $column->default_value, $column->is_nullable ?? true);
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
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @OA\Get(
     *   path="/api/v4/schemas/{schema}/tables/{table}",
     *   tags={"Table"},
     *   summary="Get description of table(s)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=false,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", description="Success message"),
     *       @OA\Property(property="success", type="boolean", example=true),
     *       @OA\Property(property="columns", type="object", additionalProperties={
     *         "type": "object",
     *         "properties": {
     *           "num": { "type":"number", "example":1},
     *           "full_type": {"type": "string", "example":"varchar(255)"},
     *           "is_nullable": {"type": "boolean", "example": true},
     *           "character_maximum_length": {"type": "number", "example": 255},
     *           "numeric_precision": {"type": "number", "example": 255},
     *           "numeric_scale": {"type": "number", "example": 255},
     *           "max_bytes": {"type": "number", "example": 255},
     *           "reference": {"type": "string", "example": "my.table.field"},
     *           "restriction": {"type": "object", "example": ""},
     *           "geom_type": {"type": "string", "nullable": true, "example": "Point"},
     *           "srid": {"type": "string", "nullable": true, "example": "4326"},
     *           "is_primary": {"type": "boolean", "example": false},
     *           "is_unique": {"type": "boolean", "example": false},
     *           "index_method": {"type": "string", "nullable": true, "example"="btree"},
     *         }
     *       })
     *       )
     *     )
     *   )
     * )
     */
    public function get_index(): array
    {
        $r = [];
        if (!empty($this->qualifiedName)) {
            for ($i = 0; sizeof($this->qualifiedName) > $i; $i++) {
                $r[] = self::getTable($this->table[$i], $this->qualifiedName[$i]);
            }
        } else {
            $r = self::getTables($this->schema);
        }
        if (count($r) > 1) {
            return ["tables" => $r];
        } else {
            return $r[0];
        }
    }

    /**
     * @param TableModel $table
     * @param string $name
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getTable(TableModel $table, string $name): array
    {
        $columns = Column::getColumns($table, $name);
        $constraints = Constraint::getConstraints($table, $name);
        $indices = Index::getIndices($table, $name);
        $response["table"] = $name;
        $response["columns"] = $columns;
        $response["indices"] = $indices;
        $response["constraints"] = $constraints;
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
            $tables[] = self::getTable(new TableModel($name), $schema . "." . $name);
        }
        return $tables;
    }

    /**
     * @return array
     * @OA\Put(
     *   path="/api/v4/schemas/{schema}/tables/{table}",
     *   tags={"Table"},
     *   summary="Rename a table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *     description="New name of relation",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string", example="new_name")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Successful operation",
     *     @OA\JsonContent(
     *       type="object",
     *       @OA\Property(property="message", type="string", description="Success message"),
     *       @OA\Property(property="success", type="boolean", example=true),
     *     )
     *   )
     * )
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public function put_index(): array
    {
        $layer = new Layer();
        $layer->begin();
        $body = Input::getBody();
        $data = json_decode($body);
        $r = [];
        for ($i = 0; sizeof($this->unQualifiedName) > $i; $i++) {
            if (isset($data->table) && $data->table != $this->unQualifiedName[$i]) {
                $arg = new stdClass();
                $arg->name = $data->table;
                $r[] = $layer->rename($this->qualifiedName[$i], $arg)['name'];
            }
            if (isset($data->schema) && $data->schema != $this->schema) {
                if (!$this->jwt['superUser']) {
                    throw new GC2Exception('Only super user can move tables between schemas');
                }
                $layer->setSchema([(isset($r[$i]) ? ($this->schema . '.' . $r[$i]) : $this->qualifiedName[$i])], $data->schema);
            }
        }
        $this->schema = $data->schema;
        $layer->commit();
        header("Location: /api/v4/schemas/$this->schema/tables/" . (count($r) > 0 ? implode(',', $r) : implode(',', $this->unQualifiedName)));
        return ["code" => "303"];
    }

    /**
     * @return array
     * @OA\Delete(
     *   path="/api/v4/schemas/{schema}/tables/{table}",
     *   tags={"Table"},
     *   summary="Delete a table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     example="my_schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=204,
     *     description="No content",
     *   )
     * )
     */
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
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $this->jwt = Jwt::validate()["data"];
        // Put and delete on collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("Put and delete on a table collection is not allowed", 400);
        }
        if (!empty($table) && count(explode(',', $table)) > 1 && in_array(Input::getMethod(), ['put', 'delete'])) {
            // throw new GC2Exception("Put and delete on multiple tables is not allowed", 406);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && $table) {
            $this->postWithResource();
        }
        $this->initiate($schema, $table, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
