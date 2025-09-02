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
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Validator\Constraints as Assert;

#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "Index",
    required: ["columns"],
    properties: [
        new OA\Property(
            property: "name",
            title: "Name of the index.",
            type: "string",
            example: "my-btree",
        ),
        new OA\Property(
            property: "columns",
            title: "Columns which should be indexed?.",
            description: "An index can comprise more columns.",
            type: "array",
            items: new OA\Items(type: "string"),
            example: ["field1"]
        ),
        new OA\Property(
            property: "method",
            title: "The index method.",
            type: "string",
            default: "btree",
            example: "btree"
        ),
    ],
    type: "object"
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Index extends AbstractApi
{
    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/schemas/{schema}/tables/{table}/indices/{index}', operationId: 'getIndex', description: "Get index", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Schema', in: 'path', required: true, example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Table', in: 'path', required: true, example: 'my_table')]
    #[OA\Parameter(name: 'index', description: 'Index', in: 'path', required: false, example: 'my_index')]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(
        allOf: [
            new OA\Schema(
                properties: [
                    new OA\Property(property: "unique", description: "If the index has a unique constraint", type: "boolean", example: true)
                ]
            ),
            new OA\Schema(ref: "#/components/schemas/Index")
        ]
    ))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    public function get_index(): array
    {
        $r = [];
        $res = self::getIndices($this->table[0]);
        if (!empty($this->index)) {
            foreach ($this->index as $index) {
                foreach ($res as $i) {
                    if ($i['name'] == $index) {
                        $r[] = $i;
                    }
                }
            }
        } else {
            $r = $res;
        }
        if (count($r) == 0) {
            throw new GC2Exception("No indices found for table", 404, null, 'NO_INDICES');
        } elseif (count($r) == 1) {
            return $r[0];
        } else {
            return ["indices" => $r];
        }
    }

    /**
     * @return array
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/indices', operationId: 'postIndex', tags: ['Schema'],)]
    #[OA\Parameter(name: 'schema', description: 'Name of schema', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Name of table', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\RequestBody(description: 'New index', required: true, content: new OA\JsonContent(ref: "#/components/schemas/Index"))]
    #[OA\Response(response: 201, description: 'Created', links: [new OA\Link('', null, null, 'getIndex')])]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[AcceptableContentTypes(['application/json'])]
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $list = [];
        $this->table[0]->begin();
        if (!isset($data->indices)) {
            $data->indices = [$data];
        }
        foreach ($data->indices as $datum) {
            $name = $datum->name ?? null;
            $method = $datum->method ?? "btree";
            $columns = $datum->columns;
            $list[] = self::addIndices($this->table[0], $columns, $method, $name);
        }
        $this->table[0]->commit();
        $baseUri = "/api/v4/schemas/{$this->schema[0]}/tables/{$this->unQualifiedName[0]}/indices/";
        header("Location: $baseUri" . implode(",", $list));
        $res["indices"] = array_map(fn($l) => ['links' => ['self' => $baseUri . $l]], $list);
        if (count($res["indices"]) == 1) {
            $res = $res["indices"][0];
        }
        $res["code"] = "201";
        return $res;
    }


    public static function getIndices(TableModel $table): array
    {

        $res = [];
        $res2 = [];
        $split = explode('.', $table->table);
        $indices = $table->getIndexes($split[0], $split[1])['indices'];
        foreach ($indices as $index) {
            $res[$index['index']]['columns'][] = $index['column_name'];
            $res[$index['index']]['method'] = $index['index_method'];
            $res[$index['index']]['unique'] = $index['is_unique'];
        }
        foreach ($res as $key => $value) {
            $res2[] = [
                "name" => $key,
                "method" => $value['method'],
                "unique" => $value['unique'],
                "columns" => $value['columns'],
            ];
        }
        return $res2;
    }

    /**
     * @throws InvalidArgumentException
     */
    public static function addIndices(TableModel $table, array $columns, string $method, ?string $name = null): string
    {
        return $table->addIndex($columns, $method, $name);
    }

    public function patch_index(): array
    {
        // TODO: Implement put_index() method.
    }

    #[OA\Delete(path: '/api/v4/schemas/{schema}/tables/{table}/indices/{index}', operationId: 'deleteIndex', description: "Delete index", tags: ['Schema'])]
    #[OA\Parameter(name: 'schema', description: 'Name of schema', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Name of table', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Parameter(name: 'index', description: 'Index name(s)', in: 'path', required: true, example: 'my_index')]
    #[OA\Response(response: 204, description: "Index deleted")]
    #[OA\Response(response: 404, description: 'Not found')]
    public function delete_index(): array
    {
        $this->table[0]->begin();
        foreach ($this->index as $index) {
            $this->table[0]->dropIndex($index);
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
        $id = Route2::getParam("index");
        $body = Input::getBody();

        // Patch and delete on collection is not allowed
        if (empty($id) && in_array(Input::getMethod(), ['patch', 'delete'])) {
            throw new GC2Exception("PATCH and DELETE on a indices collection is not allowed.", 400);
        }
        if (empty($body) && in_array(Input::getMethod(), ['post', 'patch'])) {
            throw new GC2Exception("POST and PATCH without request body is not allowed.", 400);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && !empty($id)) {
            $this->postWithResource();
        }
        $collection = self::getAssert();
        $this->validateRequest($collection, $body, 'indices', Input::getMethod());

        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, null, null, $id, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }

    static public function getAssert(): Assert\Collection
    {
        return new Assert\Collection([
            'name' => new Assert\Optional([
                new Assert\NotBlank()
            ]),
            'columns' => new Assert\Required([
                new Assert\Type('array'),
                new Assert\Count(min: 1),
                new Assert\All([
                    new Assert\NotBlank()
                ]),
            ]),
            'method' => new Assert\Optional([
                new Assert\Type('string'),
                new Assert\NotBlank()
            ]),
            'unique' => new Assert\Optional([
                new Assert\Type('boolean'),
            ]),
        ]);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }
}
