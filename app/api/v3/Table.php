<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\api\v2\Sql;
use app\inc\Controller;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Model;
use app\inc\Route;
use app\models\Table as TableModel;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use function Aws\map;


/**
 * Class Sql
 * @package app\api\v3
 */
class Table implements ApiInterface
{
    private TableModel $table;
    private string $qualifiedName;
    private string $unQualifiedName;
    private string $schema;

    /**
     * @param string $layerName
     * @param $userName
     * @param bool $superUser
     * @return bool
     */
    public function check(string $layerName, $userName, bool $superUser): bool
    {
        // Check if layer has schema prefix and add 'public' if no.
        $exploded = TableModel::explodeTableName($layerName);
        if (empty($exploded["schema"])) {
            $this->schema = "public";
        } else {
            $this->schema = $exploded["schema"];
        }
        $this->unQualifiedName = $exploded["table"];
        $this->qualifiedName = $this->schema . "." . $exploded["table"];
        if ($superUser) {
            return true;
        } else {
            if ($userName == $this->schema) {
                return true;
            } else {
                return false;
            }
        }
    }

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

    public function post_index(): array
    {
        // TODO: Implement post_index() method.
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_index(): array
    {
        $id = Route::getParam("id");
        $body = Input::getBody();
        $data = json_decode($body)->data;
        return $this->table->updateColumn($data, $id);
    }

    public function delete_index(): array
    {
        // TODO: Implement delete_index() method.
    }
}