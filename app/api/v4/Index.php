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
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
#[AcceptableMethods(['POST', 'DELETE', 'HEAD', 'OPTIONS'])]
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
        // TODO: Implement get_index() method.
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $unique = $data->unique;
        $indexType = $data->type ?? "btree";
        $this->table->createIndex($this->column, $indexType, $unique);
        header("Location: /api/v4/schemas/$this->schema/tables/$this->unQualifiedName/columns/$this->column/indices/$indexType");
        $res["code"] = "201";
        return $res;
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_index(): array
    {
        $this->table->dropIndex($this->column, $this->index);
        return ["code" => "204"];
    }

    public function validate(): void
    {
        $table = Route2::getParam("table");
        $schema = Route2::getParam("schema");
        $column = Route2::getParam("column");
        $index = Route2::getParam("index");
        $this->jwt = Jwt::validate()["data"];
        $this->check($schema, $table, null, $column, $index, null, $this->jwt["uid"], $this->jwt["superUser"]);
    }
}