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
use app\models\Layer;
use app\models\Table as TableModel;
use Exception;
use Override;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use stdClass;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Index",
    required: ["column", "type"],
    properties: [
        new OA\Property(
            property: "column",
            title: "Name of the column",
            type: "string",
            example: "my-column",
        ),
        new OA\Property(
            property: "type",
            title: "Type of the column",
            description: "The type of the column, like varchar, integer, boolean etc.",
            type: "string",
            example: "int",
        ),
        new OA\Property(
            property: "is_nullable",
            title: "Should the column be nullable?",
            description: "If true the value can be set to null",
            type: "boolean",
            default: "true",
            example: "false"
        ),
        new OA\Property(
            property: "default_value",
            title: "Default value of the column",
            description: "The column i set to the default value if no value is given",
            type: "string",
            example: "my-value"
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS'])]
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
     * @throws InvalidArgumentException
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $setDefaultValue = false;
        if (!empty($data->default_value)) {
            $setDefaultValue = true;
        }
        $list = [];
        $this->table[0]->begin();
        if (isset($data->columns)) {
            foreach ($data->columns as $datum) {
                $list[] = self::addColumn($this->table[0], $datum->column, $datum->type, $setDefaultValue, $datum->default_value, $datum->is_nullable ?? true);
            }
        } else {
            $list[] = self::addColumn($this->table[0], $data->column, $data->type, $setDefaultValue, $data->default_value, $data->is_nullable ?? true);
        }
        $this->table[0]->commit();
        header("Location: /api/v4/schemas/$this->schema/tables/{$this->unQualifiedName[0]}/columns/" . implode(',', $list));
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
        $r = [];
        $res = self::getColumns($this->table[0], $this->qualifiedName[0]);
        if ($this->column) {
            foreach ($this->column as $col) {
                foreach ($res as $datum) {
                    if ($datum['column'] === $col) {
                        $r[] = $datum;
                    }
                }
            }
        } else {
            $r = $res;
        }
        if (count($r) > 1) {
            return ["columns" => $r];
        } else {
            return $r[0];
        }
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getColumns(TableModel $table, string $name): array
    {
        $response = [];
        $res = $table->getMetaData($name, false, true, null, null, false);
        foreach ($res as $key => $column) {
            $column['column'] = $key;
            $response[] = $column;
        }
        return $response;

    }


    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
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
     * @throws InvalidArgumentException
     */
    public function put_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);

        $layer = new Layer();
        $geomFields = $layer->getGeometryColumnsFromTable($this->schema, $this->unQualifiedName[0]);

        $this->table[0]->begin();
        $r = [];
        $list = [];

        foreach ($this->column as $oldColumnName) {
            foreach ($geomFields as $geomField) {
                $key = $this->qualifiedName[0] . '.' . $geomField;
                $conf = json_decode($layer->getValueFromKey($key, 'fieldconf'));
                $obj = $conf->{$oldColumnName} ?? new stdClass();
                $obj->id = $oldColumnName;
                $obj->column = $data->column ?? $oldColumnName;
                $obj->type = $data->type;
                $r = $this->table[0]->updateColumn($obj, $key, true);
                $list[] = $r['name'];
            }
            $newName = $r["name"];
            if (property_exists($data, "is_nullable")) {
                if (!$data->is_nullable) {
                    $this->table[0]->addNotNullConstraint($newName);
                } else {
                    $this->table[0]->dropNotNullConstraint($newName);
                }
            }
            if (property_exists($data, "default_value")) {
                if ($data->default_value === null) {
                    $this->table[0]->dropDefaultValue($newName);
                } else {
                    $this->table[0]->addDefaultValue($newName, $data->default_value);
                }
            }
            if (property_exists($data, "type")) {
                $this->table[0]->changeType($newName, $data->type);
            }
        }
        $this->table[0]->commit();

        header("Location: /api/v4/schemas/$this->schema/tables/{$this->unQualifiedName[0]}/columns/" . implode(',', $list));
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
     * @throws InvalidArgumentException
     */
    public function delete_index(): array
    {
        $this->table[0] = new TableModel($this->qualifiedName[0]);
        $this->table[0]->begin();
        foreach ($this->column as $column) {
            $this->table[0]->deleteColumn([$column], "");
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
