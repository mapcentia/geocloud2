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
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Constraints as Assert;


#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Column",
    required: [],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name of the column",
            description: "Name of the column",
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
            description: "If true the column can be set to null",
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
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
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
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/columns/{column}', operationId: 'getColumn', description: "Get column", tags: ['Column'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'column', description: 'Column names', in: 'path', required: false, example: 'my_columns')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Column"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): array
    {
        $r = [];
        $res = self::getColumns($this->table[0]);
        if ($this->column) {
            foreach ($this->column as $col) {
                foreach ($res as $datum) {
                    if ($datum['name'] === $col) {
                        $r[] = $datum;
                    }
                }
            }
        } else {
            $r = $res;
        }
        if (count($r) == 0) {
            throw new GC2Exception("No columns found in table", 404, null, 'NO_COLUMNS');
        } elseif (count($r) == 1) {
            return $r[0];
        } else {
            return ["columns" => $r];
        }
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/columns/', operationId: 'postColumn', description: "Get column", tags: ['Column'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\RequestBody(description: 'New column', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Column"))]
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
        $setDefaultValue = false;
        if (!empty($data->default_value)) {
            $setDefaultValue = true;
        }
        $list = [];
        $this->table[0]->begin();
        if (!isset($data->columns)) {
            $data->columns = [$data];
        }
        foreach ($data->columns as $datum) {
            $list[] = self::addColumn($this->table[0], $datum->name, $datum->type, $setDefaultValue, $datum->default_value, $datum->is_nullable ?? true, $datum->comment);
        }
        $this->table[0]->commit();
        header("Location: /api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/columns/" . implode(',', $list));
        return ["code" => "201"];
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/columns/{column}/', operationId: 'patchColumn', description: "Update column(s)", tags: ['Column'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'column', description: 'Column names', in: 'path', required: true, example: 'my_columns')]
    #[OA\RequestBody(description: 'Column', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Column"))]
    #[OA\Response(response: 204, description: "Column updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);

        $layer = new Layer();
        $geomFields = $layer->getGeometryColumnsFromTable($this->schema[0], $this->unQualifiedName[0]);

        $this->table[0]->begin();
        $r = [];
        $list = [];

        foreach ($this->column as $oldColumnName) {
            foreach ($geomFields as $geomField) {
                $key = $this->qualifiedName[0] . '.' . $geomField;
                $conf = json_decode($layer->getValueFromKey($key, 'fieldconf'));
                $obj = $conf->{$oldColumnName} ?? new stdClass();
                $obj->id = $oldColumnName;
                $obj->column = $data->name ?? $oldColumnName;
                $obj->type = $data->type;
                if (property_exists($data, 'comment')) {
                    $obj->comment = $data->comment;
                }
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

        header("Location: /api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/columns/" . implode(',', $list));
        return ["code" => "303"];
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/columns/{column}', operationId: 'deleteColumn', description: "Get column", tags: ['Column'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'column', description: 'Column names', in: 'path', required: true, example: 'my_columns')]
    #[OA\Response(response: 204, description: 'Column deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
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
     * @throws PhpfastcacheInvalidArgumentException
     */
    public static function getColumns(TableModel $table): array
    {
        $response = [];
        $res = $table->getMetaData($table->table, false, true, null, null, false);
        foreach ($res as $key => $column) {
            $column = ['name' => $key, ...$column];
            $response[] = $column;
        }
        return parent::setPropertiesToPrivate($response);

    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public static function addColumn(TableModel $table, string $column, string $type, bool $setDefaultValue, mixed $defaultValue = null, bool $isNullable = true, ?string $comment = null): string
    {
        $r = $table->addColumn([
            "column" => $column,
            "type" => $type,
            "comment" => $comment,
        ]);
        if (!$isNullable) {
            $table->addNotNullConstraint($r["column"]);
        } else {
            $table->dropNotNullConstraint($r["column"]);
        }
        if ($setDefaultValue && isset($defaultValue)) {
            $table->addDefaultValue($r["column"], $defaultValue);
        }
        return $r["column"];
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
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($column) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("fgfg", 406);
        }
        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $column) {
            $this->postWithResource();
        }

        $this->validateRequest(self::getAssert(), $body, 'columns', Input::getMethod());

        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, null, $column, null, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }

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
            'type' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank(),
            ]),
            'is_nullable' => new Assert\Optional([
                new Assert\Type('boolean'),
            ]),
            'default_value' => new Assert\Optional([]),
        ]);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }
}
