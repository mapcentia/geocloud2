<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use \app\inc\Input;
use \app\conf\Connection;

class Database extends \app\inc\Controller
{
    private $db;
    private $request;

    function __construct()
    {
        parent::__construct();

        $this->request = \app\inc\Input::getPath();
        $this->db = new \app\models\Database();
    }

    public function get_schemas()
    {
        return $this->db->listAllSchemas();
    }

    public function post_schemas()
    {
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->db->createSchema(Input::get('schema'));
    }

    public function put_schema()
    {
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->db->renameSchema(Connection::$param['postgisschema'], json_decode(Input::get())->data->name);
    }

    public function delete_schema()
    {
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->db->deleteSchema(Connection::$param['postgisschema']);
    }

    public function get_exist()
    {
        \app\models\Database::setDb("mapcentia");
        $this->db = new \app\models\Database();
        return $this->db->doesDbExist(Input::getPath()->part(4));
    }
    public function get_createschema(){
        $response = $this->auth();
        return (!$response['success']) ? $response : $this->db->createSchema(Input::get('schema'));
    }
}
