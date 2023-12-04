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
class Column implements ApiInterface
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

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_index(): array
    {
        $response = [];
        $this->table = new TableModel($this->qualifiedName);
        $response["success"] = true;
        $response["data"]["columns"] = $this->table->metaData;
        $response["message"] = "";
        return $response;
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        $body = Input::getBody();
        $data = json_decode($body);
        return $this->table->addColumn([
            "column" => $column,
            "type" => $data->type,
        ]);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        $body = Input::getBody();
        $data = json_decode($body);
        return $this->table->updateColumn($data, $column);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        return $this->table->deleteColumn([$column], "");
    }
}
