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

class Constraint
{
    use ApiTrait;


    /**
     * @throws PhpfastcacheInvalidArgumentException|Exception
     */
    public function __construct()
    {
        $table = Route::getParam("table");
        $jwt = Jwt::validate()["data"];
        $this->check($table, $jwt["uid"], $jwt["superUser"]);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_unique(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        return $this->table->addUniqueConstraint($column);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_unique(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        return $this->table->dropUniqueConstraint($column);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function post_foreign(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        $body = Input::getBody();
        $data = json_decode($body);
        return $this->table->addForeignConstraint($column, $data->referencedTable, $data->referencedColumn);
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_foreign(): array
    {
        $this->table = new TableModel($this->qualifiedName);
        $column = Route::getParam("column");
        return $this->table->dropForeignConstraint($column);
    }
}
