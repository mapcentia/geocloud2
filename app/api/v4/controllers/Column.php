<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\NoContentResponse;
use app\api\v4\Responses\PatchResponse;
use app\api\v4\Responses\PostResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
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
    description: "Each column has a data type. The data type constrains the set of possible values that can be assigned to a column and assigns semantics to the data stored in the column so that it can be used for computations. For instance, a column declared to be of a numerical type will not accept arbitrary text strings, and the data stored in such a column can be used for mathematical computations. By contrast, a column declared to be of a character string type will accept almost any kind of data but it does not lend itself to mathematical calculations, although other operations such as string concatenation are available.",
    required: [],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name",
            description: "Name of the column.",
            type: "string",
            example: "my-column",
        ),
        new OA\Property(
            property: "type",
            title: "Type",
            description: "The type of the column, like varchar, integer, boolean etc.",
            type: "string",
            example: "int",
        ),
        new OA\Property(
            property: "is_nullable",
            title: "Is nullable",
            description: "If true the column can be set to null.",
            type: "boolean",
            default: "true",
            example: "false"
        ),
        new OA\Property(
            property: "default_value",
            title: "Default value",
            description: "The column is set to the default value if no value is given.",
            type: "string",
            example: "my-value"
        ),
        new OA\Property(
            property: "identity_generation",
            title: "Identity generation of the column",
            description: "An identity column is a special column that is generated automatically from an implicit sequence. It can be used to generate key values.",
            type: "string",
            enum: ["always", "by default"],
            example: "always"
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/tables/{table}/columns/[column]', scope: Scope::SUB_USER_ALLOWED)]
class Column extends AbstractApi
{

    /**
     * @throws Exception
     */
    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->resource = 'columns';
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/columns/{column}', operationId: 'getColumn', description: "Get column(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'column', description: 'Column names', in: 'path', required: false, example: 'my_columns')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Column"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): GetResponse
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
        return $this->getResponse($r);
    }

    /**
     * @return array
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/columns/', operationId: 'postColumn', description: "Create new column(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\RequestBody(description: 'New column', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Column"))]
    #[OA\Response(response: 201, description: 'Created')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): PostResponse
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $list = [];
        $this->table[0]->connect();
        $this->table[0]->begin();
        if (!isset($data->columns)) {
            $columns = [$data];
        } else {
            $columns = $data->columns;
        }
        foreach ($columns as $datum) {
            $list[] = self::addColumn(
                table: $this->table[0],
                column: $datum->name,
                type: $datum->type,
                defaultValue: $datum->default_value,
                isNullable: $datum->is_nullable,
                identity: $datum->identity_generation,
                comment: $datum->comment);
        }
        $this->table[0]->commit();
        $baseUri = "/api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/columns/";
        return $this->postResponse($baseUri, $list);
    }

    /**
     * @return PatchResponse
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[OA\Patch(path: '/api/v4/schemas/{schema}/tables/{table}/columns/{column}/', operationId: 'patchColumn', description: "Update column(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'column', description: 'Column names', in: 'path', required: true, example: 'my_columns')]
    #[OA\RequestBody(description: 'Column', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Column"))]
    #[OA\Response(response: 204, description: "Column updated")]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableContentTypes(['application/json'])]
    #[Override]
    public function patch_index(): PatchResponse
    {
        $body = Input::getBody();
        $data = json_decode($body);

        $layer = new Layer(connection: $this->connection);
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
        $baseUri="/api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/columns/";
        return $this->patchResponse($baseUri, $list);
    }

    /**
     * @return NoContentResponse
     * @throws GC2Exception
     * @throws InvalidArgumentException
     * @throws PhpfastcacheInvalidArgumentException
     */
    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/columns/{column}', operationId: 'deleteColumn', description: "Delete column(s)", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema name', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table name', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'column', description: 'Column names', in: 'path', required: true, example: 'my_columns')]
    #[OA\Response(response: 204, description: 'Column deleted')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): NoContentResponse
    {
        $this->table[0] = new TableModel($this->qualifiedName[0], connection: $this->connection);
        $this->table[0]->begin();
        foreach ($this->column as $column) {
            $this->table[0]->deleteColumn([$column], "");
        }
        $this->table[0]->commit();
        return $this->deleteResponse();
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    public static function getColumns(TableModel $table): array
    {
        $response = [];
        $res = $table->getMetaData($table->table, false, true, null, null, false);
        foreach ($res as $key => $column) {
            $column['type'] = $column['full_type'];
            unset($column['full_type']);
            $column = ['name' => $key, ...$column];
            $response[] = $column;
        }
        return parent::setPropertiesToPrivate($response);

    }

    /**
     * @param TableModel $table
     * @param string $column
     * @param string $type
     * @param mixed|null $defaultValue
     * @param bool|null $isNullable
     * @param string|null $identity
     * @param string|null $comment
     * @return string
     * @throws GC2Exception
     * @throws InvalidArgumentException
     */
    public static function addColumn(TableModel $table, string $column, string $type, mixed $defaultValue = null, ?bool $isNullable = null, ?string $identity = null, ?string $comment = null): string
    {
        if (!empty($identity) && ($isNullable === true || $defaultValue !== null)) {
            throw new GC2Exception("Identity columns can not be nullable or have default values", 400);
        }
        $r = $table->addColumn([
            "column" => $column,
            "type" => $type,
            "identity_generation" => $identity,
            "comment" => $comment,
        ]);
        if ($isNullable === false) {
            $table->addNotNullConstraint($r["column"]);
        }
        if ($isNullable === true){
            $table->dropNotNullConstraint($r["column"]);
        }
        if (isset($defaultValue)) {
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
        $table = $this->route->getParam("table");
        $schema = $this->route->getParam("schema");
        $column = $this->route->getParam("column");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($column) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a column collection is not allowed.", 406);
        }

        // Throw exception if tried with resource id
        if (Input::getMethod() == 'post' && $column) {
            $this->postWithResource();
        }
        $this->validateRequest(self::getAssert(), $body, Input::getMethod());

        $this->initiate(schema: $schema, relation: $table, key: $column, column: $column);
    }

    static public function getAssert(): Assert\Collection
    {
        $collection = new Assert\Collection([]);
        if (Input::getMethod() == 'post') {
            $collection->fields['name'] = new Assert\Required([
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ]
            );
            $collection->fields['type'] = new Assert\Required([
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ]
            );
            $collection->fields['identity_generation'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\Choice(choices: ['always', 'by default']),
                ]
            );
        } else {
            $collection->fields['name'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ]
            );
            $collection->fields['type'] = new Assert\Optional([
                    new Assert\Type('string'),
                    new Assert\NotBlank()
                ]
            );
        }
        $collection->fields['comment'] = new Assert\Optional(
            new Assert\Type('string'),
        );
        $collection->fields['is_nullable'] = new Assert\Optional(
            new Assert\Type('boolean'),
        );
        $collection->fields['default_value'] = new Assert\Optional();
        return $collection;
    }

    public function put_index(): Response
    {
        // TODO: Implement put_index() method.
    }
}
