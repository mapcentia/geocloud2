<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route;
use app\models\Table as TableModel;
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use stdClass;


/**
 * Class Sql
 * @package app\api\v4
 */
class Column implements ApiInterface
{
    use ApiTrait;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $table = Route::getParam("table");
        $jwt = Jwt::validate()["data"];
        $this->check($table, $jwt["uid"], $jwt["superUser"]);
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @OA\Get(
     *   path="/api/v4/table/{table}/{column})",
     *   tags={"Column"},
     *   summary="Get description of column",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     example="public.my_table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *  @OA\Parameter(
     *     name="column",
     *     example="my_column",
     *     in="path",
     *     required=true,
     *     description="Name of column",
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
     *       @OA\Property(property="columns", type="object",
     *         @OA\Property(property="num", type="integer", example=1),
     *         @OA\Property(property="full_type", type="string", example="character varying(255)"),
     *         )
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
        $column = Route::getParam("column");
        $response["success"] = true;
        if (!isset($this->table->metaData[$column])) {
            throw new GC2Exception("Column not found", 404, null, "COLUMN_NOT_FOUND");
        }
        $response["column"] = $this->table->metaData[$column];
        return $response;
    }


    /**
     * @return array
     * @OA\Post(
     *   path="/api/v4/column/{table}",
     *   tags={"Column"},
     *   summary="Add a column to a table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string",
     *       example="public.my_table"
     *     )
     *   ),
     *  @OA\RequestBody(
     *      description="Type of column",
     *      @OA\MediaType(
     *        mediaType="application/json",
     *        @OA\Schema(
     *          type="object",
     *          @OA\Property(property="type",type="string", example="varchar(255)"),
     *          @OA\Property(property="name",type="string", example="my_column"),
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
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    public function post_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $this->doesTableExist();
        $body = Input::getBody();
        $data = json_decode($body);
        return $this->table->addColumn([
            "column" => $data->name,
            "type" => $data->type,
        ]);
    }

    /**
     * @return array
     * @OA\Put(
     *   path="/api/v4/column/{table}/{column}",
     *   tags={"Column"},
     *   summary="Alter column",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     example="public.my_table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *  @OA\Parameter(
     *     name="column",
     *     example="my_column",
     *     in="path",
     *     required=true,
     *     description="Name of column",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *  @OA\RequestBody(
     *      description="Type of column",
     *      @OA\MediaType(
     *        mediaType="application/json",
     *        @OA\Schema(
     *          type="object",
     *          @OA\Property(property="type",type="string", example="varchar(255)"),
     *          @OA\Property(property="name",type="string", example="my_column"),
     *          @OA\Property(property="is_nullable",type="boolean", example=true)
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
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    public function put_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $this->doesTableExist();
        $column = Route::getParam("column");
        $body = Input::getBody();
        $data = json_decode($body);
        $obj = new stdClass();
        $obj->id = $column;
        $obj->column = $data->name;
        $obj->type = $data->type;
        if (!empty($data->is_nullable)) {
            $obj->is_nullable = $data->is_nullable;
        }
        return $this->table->updateColumn($obj, $column);
    }

    /**
     * @return array
     * @OA\Delete (
     *   path="/api/v4/column/{table}/{column}",
     *   tags={"Column"},
     *   summary="Drop a column",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="table",
     *     example="public.my_table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *  @OA\Parameter(
     *     name="column",
     *     example="my_column",
     *     in="path",
     *     required=true,
     *     description="Name of column",
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
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $this->doesTableExist();
        $column = Route::getParam("column");
        return $this->table->deleteColumn([$column], "");
    }

}
