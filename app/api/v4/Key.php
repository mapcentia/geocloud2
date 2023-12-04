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
use Exception;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Sql
 * @package app\api\v4
 */
class Key implements ApiInterface {

    use ApiTrait;

    /**
     * @throws Exception
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

    public function post_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = explode(",", Route::getParam("column"));
        $trimmed = array_map('trim', $column);
        return $this->table->addPrimaryKey($trimmed);
    }

    public function put_index(): array
    {
        // TODO: Implement put_index() method.
    }

    public function delete_index(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        return $this->table->dropPrimaryKey();
    }
}
