<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route2;
use app\models\Table as TableModel;
use Exception;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use stdClass;


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'PUT', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Column extends AbstractApi
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
     *   path="/api/v4/schemas/{schema}/tables/{table}/columns",
     *   tags={"Column"},
     *   summary="Add a column to a table",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="schema",
     *     in="path",
     *     required=true,
     *     description="Name of schema",
     *     @OA\Schema(
     *       type="string",
     *       example="my_schema"
     *     )
     *   ),
     *   @OA\Parameter(
     *     name="table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string",
     *       example="my_table"
     *     )
     *   ),
     *   @OA\Response(
     *     response=201,
     *     description="Created",
     *   )
     * )
     * @throws GC2Exception
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $setDefaultValue = false;
        if (!empty($data->default_value)) {
            $setDefaultValue = true;
        }
        $newColumnName = self::addColumn($this->table, $data->column, $data->type, $setDefaultValue, $data->default_value, $data->is_nullable ?? true);
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/columns/$newColumnName");
        return ["code" => "201"];
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @OA\Get(
     *   path="/api/v4/schemas/{schema}/tables/{table}/columns/{column})",
     *   tags={"Column"},
     *   summary="Get description of column(s)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *      name="schema",
     *      example="my_schema",
     *      in="path",
     *      required=true,
     *      description="Name of schema",
     *      @OA\Schema(
     *        type="string"
     *      )
     *    ),
     *   @OA\Parameter(
     *     name="table",
     *     example="my_table",
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
     *     required=false,
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
    #[Override] public function get_index(): array
    {
        $res = self::getColumns($this->table, $this->qualifiedName);
        if ($this->column) {
            foreach ($res as $i) {
                if ($i['column'] == $this->column) {
                    return $i;
                }
            }
        } else {
            return ["columns" => $res];
        }
        return [];
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getColumns(TableModel $table, string $name): array
    {
        $response = [];
        $res = $table->getMetaData($name);
        foreach ($res as $key => $column) {
            $column['column'] = $key;
            $response[] = $column;
        }
        return $response;

    }


    /**
     * @throws GC2Exception
     */
    public static function addColumn(TableModel $table, string $column, string $type, bool $setDefaultValue, mixed $defaultValue = null, bool $isNullable = true): string
    {
        $r = $table->addColumn([
            "column" => $column,
            "type" => $type,
        ]);
        if (!$isNullable) {
            $table->addNotNullConstraint($column);
        } else {
            $table->dropNotNullConstraint($column);
        }
        if ($setDefaultValue && isset($defaultValue)) {
            $table->addDefaultValue($column, $defaultValue);
        }

        return $r["column"];
    }

    /**
     * @return array
     * @OA\Put(
     *   path="/api/v4/schemas/{schema}/tables/{table}/columns/{column}",
     *   tags={"Column"},
     *   summary="Rename column and set nullable ",
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
     *     example="my_table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
     *       name="column",
     *       in="path",
     *       required=true,
     *       description="Name of column",
     *       @OA\Schema(
     *         type="string",
     *         example="my_column"
     *       )
     *     ),
     *   @OA\RequestBody(
     *     description="Type of column",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="type",type="string", example="varchar(255)"),
     *         @OA\Property(property="name",type="string", example="my_column")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response=303,
     *     description="See other",
     *   )
     * )
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    public function put_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $obj = new stdClass();
        $obj->id = $this->column;
        $obj->column = $data->column ?? $this->column;
        $obj->type = $data->type;
        $r = $this->table->updateColumn($obj, $this->column, true);
        $newName = $r["name"];
        if (isset($data->is_nullable)) {
            if (!$data->is_nullable) {
                $this->table->addNotNullConstraint($newName);
            } else {
                $this->table->dropNotNullConstraint($newName);
            }
        }
        if (property_exists($data, "default_value")) {
            if ($data->default_value === null) {
                $this->table->dropDefaultValue($this->column);
            } else  {
                $this->table->addDefaultValue($this->column, $data->default_value);
            }
        }
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/columns/$newName");
        return ["code" => "303"];
    }

    /**
     * @return array
     * @OA\Delete (
     *   path="/api/v4/schemas/{schema}/tables/{table}/columns/{column}",
     *   tags={"Column"},
     *   summary="Drop a column",
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
     *     example="my_table",
     *     in="path",
     *     required=true,
     *     description="Name of table",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Parameter(
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
     *     response=204,
     *     description="No content",
     *   )
     * )
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route2::getParam("column");
        $this->table->deleteColumn([$column], "");
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
        $column = Route2::getParam("column");
        // Put and delete on collection is not allowed
        if (empty($column) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $column) {
            $this->postWithResource();
        }
        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, null, $column, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
