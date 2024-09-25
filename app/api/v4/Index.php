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

#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Index extends AbstractApi
{

    /**
     * @return array
     */
    #[OA\Post(path: '/api/v4/schemas/{schema}/tables/{table}/indices', operationId: 'postIndex', tags: ['Indices'])]
    #[OA\Parameter(name: 'schema', description: 'Name of schema', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_schema')]
    #[OA\Parameter(name: 'table', description: 'Name of table', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'my_table')]
    #[OA\Response(response: 201, description: 'Created',
        content: new OA\JsonContent(properties: [new OA\Property(property: 'index', description: 'Name of new index', type: 'string', example: 'my_index')], type: 'object'),
        links: [new OA\Link('', null, null, 'getIndex')])
    ]
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
        header("Location: /api/v4/schemas/$this->schema/tables/{$this->unQualifiedName[0]}/indices/" . implode(',', $list));
        $res["code"] = "201";
        return $res;
    }

    /**
     * @return array
     * @OA\Get(
     *   path="/api/v4/schemas/{schema}/tables/{table}/indices/{index}",
     *   operationId="getIndex",
     *   tags={"Indices"},
     *   summary="Get index/indices",
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
     *     name="index",
     *     example="my_index",
     *     in="path",
     *     required=false,
     *     description="Name of constraint",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=200,
     *     description="Success",
     *   )
     * )
     * @throws GC2Exception
     */
    public function get_index(): array
    {
        $r = [];
        $res = self::getIndices($this->table[0], $this->qualifiedName[0]);
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
        if (count($r) > 1) {
            return ["indices" => $r];
        } else {
            return $r[0];
        }
    }

    public static function getIndices(TableModel $table, string $name): array
    {

        $res = [];
        $res2 = [];
        $split = explode('.', $name);
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

    public static function addIndices(TableModel $table, array $columns, string $method, ?string $name = null): string
    {
        return $table->addIndex($columns, $method, $name);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    /**
     * @return array
     * @OA\Delete (
     *   path="/api/v4/schemas/{schema}/tables/{table}/indices/{index}",
     *   tags={"Indices"},
     *   summary="Get index/indices",
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
     *     name="index",
     *     example="my_index",
     *     in="path",
     *     required=true,
     *     description="Name of constraint",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response=204,
     *     description="No content",
     *   )
     * )
     * @throws GC2Exception
     */
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
        $index = Route2::getParam("index");
        // Put and delete on collection is not allowed
        if (empty($index) && in_array(Input::getMethod(), ['put', 'delete'])) {
            throw new GC2Exception("", 406);
        }
        // Throw exception if tried with table resource
        if (Input::getMethod() == 'post' && $index) {
            $this->postWithResource();
        }
        $this->jwt = Jwt::validate()["data"];
        $this->initiate($schema, $table, null, null, $index, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}
