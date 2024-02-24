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
use app\inc\Route2;
use app\models\Table as TableModel;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
class Index extends AbstractApi
{
    /**
     * @throws GC2Exception|PhpfastcacheInvalidArgumentException
     */
    public function __construct()
    {
    }

    public function get_index(): array
    {
        $indices = self::getIndices($this->table, $this->qualifiedName);
        if (!empty($this->index)) {
            foreach ($indices as $index) {
                if ($index['name'] == $this->index) {
                    return $index;
                }
            }
        }
        return ["indices" => $indices];
    }

    public static function getIndices(TableModel $table, string $name): array {

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
        return  $res2;
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $name = $data->name ?? null;
        $method = $data->method ?? "btree";
        $columns = $data->columns;
        $name = self::addIndices($this->table, $columns, $method, $name);
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/indices/$name");
        $res["code"] = "201";
        return $res;
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
     * @throws PDOException
     */
    public function delete_index(): array
    {
        $this->table->dropIndex($this->index);
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