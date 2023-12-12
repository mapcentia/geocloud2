<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\models\Layer;
use app\models\Table as TableModel;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
class Table implements ApiInterface
{
    use ApiTrait;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $table = Route::getParam("table");
        $jwt = Jwt::validate()["data"];
        if ($table) {
            $this->check($table, $jwt["uid"], $jwt["superUser"]);
        }
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @OA\Get(
     *   path="/api/v4/table/{table}",
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
        $response = [];
        $this->table = new TableModel($this->qualifiedName);
        $this->doesTableExist();
        $response["success"] = true;
        $response["columns"] = $this->table->metaData;
        return $response;
    }

    /**
     * @return array
     * @OA\Post(
     *   path="/api/v4/table/{table}",
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
     */
    public function post_index(): array
    {
        $this->table = new TableModel(null);
        $body = Input::getBody();
        $data = json_decode($body);
        $this->table->postgisschema = $data->schema ?? "public";
        $res = $this->table->create($data->name, null, null, true);
        return [
            "success" => true,
            "message" => "Table created",
            "table_name" => $res["tableName"]
        ];

    }

    /**
     * @return array
     * @OA\Put(
     *   path="/api/v4/table/{table}",
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
        $this->table = new TableModel($this->qualifiedName);
        $this->doesTableExist();
        $layer = new Layer();
        $body = Input::getBody();
        $data = json_decode($body);
        return $layer->rename($this->qualifiedName, $data);
    }

    /**
     * @return array
     * @OA\Delete(
     *   path="/api/v4/table/{table}",
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
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function delete_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $this->doesTableExist();
        return $this->table->destroy();
    }
}
