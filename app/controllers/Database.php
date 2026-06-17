<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use app\conf\Connection;
use Throwable;

class Database extends Controller
{
    private \app\models\Database $db;

    function __construct()
    {
        parent::__construct();
        $this->db = new \app\models\Database();
    }

    /**
     * @return array
     * @throws Throwable
     */
    public function get_schemas(): array
    {
        return $this->db->listAllSchemas();
    }

    /**
     * @return array
     */
    public function post_schemas(): array
    {
        $response = $this->isSuperUser(); // Never sub-user
        return (!$response['success']) ? $response : $this->db->createSchema(Input::get('schema'));
    }

    /**
     * @return array
     */
    public function put_schema(): array
    {
        $response =$this->isSuperUser(); // Never sub-user
        return (!$response['success']) ? $response : $this->db->renameSchema(Connection::$param['postgisschema'], json_decode(Input::get())->data->name);
    }

    /**
     * @return array
     */
    public function delete_schema(): array
    {
        $response = $this->isSuperUser(); // Never sub-user
        return (!$response['success']) ? $response : $this->db->deleteSchema(Connection::$param['postgisschema']);
    }

    /**
     * @return array
     */
    public function get_exist(): array
    {
        \app\models\Database::setDb("mapcentia");
        $this->db = new \app\models\Database();
        return $this->db->doesDbExist(Input::getPath()->part(4));
    }
}
