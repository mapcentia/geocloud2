<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4;

use app\inc\Input;
use app\inc\Jwt;
use app\inc\Route;
use app\models\Table as TableModel;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
class Index implements ApiInterface
{
    use ApiTrait;

    /**
     */
    public function __construct()
    {
        $table = Route::getParam("table");
        $jwt = Jwt::validate()["data"];
        $this->check($table, $jwt["uid"], $jwt["superUser"]);
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
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        $type = Route::getParam("type");
        $body = Input::getBody();
        $data = json_decode($body);
        $unique = $data->unique;
        return $this->table->createIndex($column, $type, $unique);
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
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        $type = Route::getParam("type");
        return $this->table->dropIndex($column, $type);
    }
}