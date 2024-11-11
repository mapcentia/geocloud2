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


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'PUT', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
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
     * @OA\Get(
     *   path="/api/table/v4/table/{table}",
     *   tags={"Schema"},
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
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index(): array
    {
        $schemas = $this->schemaObj->listAllSchemas()["data"];
        $response = [];
        if ($this->jwt['superUser'] && empty($this->schema)) {
            foreach ($schemas as $schema) {
                $response["schemas"][] = [
                    "name" => $schema["name"],
                    "tables" => Table::getTables($schema["name"]),
                ];
            }
        } else {
            $response = [
                "name" => $this->schema,
                "tables" => Table::getTables($this->schema),
            ];
        }
        return $response;
    }

    /**
     * @return array
     * @OA\Post(
     *   path="/api/table/v4",
     *   tags={"Schema"},
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
     * @throws InvalidArgumentException
     */
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
        $this->table[0]->postgisschema = $data->name;
        $this->table[0]->begin();
        $r = $this->schemaObj->createSchema($data->name, $this->table[0]);
        // Add tables
        if (!empty($data->tables)) {
            foreach ($data->tables as $table) {
                Table::addTable($this->table[0], $table, $this);
            }
        }
        $this->table[0]->commit();
        header("Location: /api/v4/schemas/{$r["schema"]}");
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     * @OA\Put(
     *   path="/api/table/v4/table/{table}",
     *   tags={"Schema"},
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
     * @throws GC2Exception
     */
    public function put_index(): array
    {
        if (!$this->jwt['superUser']) {
            throw new GC2Exception("", 403);
        }
        $body = Input::getBody();
        $data = json_decode($body);
        $r = $this->schemaObj->renameSchema($this->schema, $data->schema);
        header("Location: /api/v4/schemas/{$r['data']['name']}");
        $res["code"] = "303";
        return $res;
    }

    /**
     * @return array
     * @OA\Delete(
     *   path="/api/table/v4/table/{table}",
     *   tags={"Schema"},
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
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        if (!$this->jwt['superUser']) {
            throw new GC2Exception("", 403);
        }
        $r = $this->schemaObj->deleteSchema($this->schema);
        $res["code"] = "204";
        return $res;
    }

    /**
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function validate(): void
    {
        $schema = Route2::getParam("schema");
        $this->jwt = Jwt::validate()["data"];
        // Put and delete on table collection is not allowed
        if (empty($schema) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        $this->initiate($schema, null, null, null, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
        $this->schemaObj = new Database();
    }
}
