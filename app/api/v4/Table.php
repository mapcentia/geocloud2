<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\models\Layer;
use app\models\Table as TableModel;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
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
     * @throws PhpfastcacheInvalidArgumentException
     * @OA\Get(
     *   path="/api/table/v4/table/{table}",
     *   tags={"Table"},
     *   summary="Get description of table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of relation (table, view, etc.) Can be schema qualified",
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
        if (!empty($this->qualifiedName)) {
            $columns =  $this->table->metaData;
            $response = [];
            foreach($columns as $key => $column) {
                $response["columns"][$key] = $column;
            }
            return $response;

        } else {
            return ["test" => "hej"];
        }

    }

    /**
     * @return array
     * @OA\Post(
     *   path="/api/table/v4",
     *   tags={"Table"},
     *   summary="Create a new table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of relation (table, view, etc.) Can be schema qualified",
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
     *     )
     *   )
     * )
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        // Throw exception if tried with table resource
        if (!empty(Route2::getParam("table"))) {
            $this->postWithResource();
        }
        $body = Input::getBody();
        $data = json_decode($body);
        $this->table = new TableModel(null);
        $this->table->postgisschema = $this->schema;
        $r = $this->table->create($data->table, null, null, true);
        $this->check($this->schema, $r["tableName"], $this->jwt["uid"], $this->jwt["superUser"]);
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName");
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     * @OA\Put(
     *   path="/api/table/v4/table/{table}",
     *   tags={"Table"},
     *   summary="Rename a table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="New name of relation (table, view, etc.) Can be schema qualified",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\RequestBody(
     *      description="New name of relation",
     *      @OA\MediaType(
     *        mediaType="application/json",
     *        @OA\Schema(
     *          type="object",
     *          @OA\Property(property="name",type="string", example="new_name")
     *        )
     *      )
     *    ),
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
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function put_index(): array
    {
        //TODO set schema
        $layer = new Layer();
        $body = Input::getBody();
        $data = json_decode($body);
        $arg = new stdClass();
        $arg->name = $data->table;
        $r = $layer->rename($this->qualifiedName, $arg);
        header("Location: /api/v4/schemas/$this->schema/tables/{$r["name"]}");
        return ["code" => "303"];
    }

    /**
     * @return array
     * @OA\Delete(
     *   path="/api/table/v4/table/{table}",
     *   tags={"Table"},
     *   summary="Delete a table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of relation (table, view, etc.), which should be deleted. Can be schema qualified",
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
     *     )
     *   )
     * )
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_index(): array
    {
        $this->table->destroy();
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
        // Put and delete on table collection is not allowed
        if (empty($table) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Validate schema/table if not POST
        if (Input::getMethod() != "post") {
            $this->check($schema, $table, $this->jwt["uid"], $this->jwt["superUser"]);
            $this->table = new TableModel($this->qualifiedName);
        } else {
            $this->schema = $schema;
            $this->doesSchemaExist();
        }
    }
}
