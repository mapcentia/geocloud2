<?php
namespace app\controllers;

use \app\inc\Input;
use \app\conf\Connection;

class Database extends \app\inc\Controller
{
    private $db;
    private $request;

    function __construct()
    {
        $this->request = \app\inc\Input::getPath();
        $this->db = new \app\models\Database();
    }

    public function get_schemas()
    {
        return $this->db->listAllSchemas();
    }

    public function post_schemas()
    {
        return $this->db->createSchema(Input::get('schema'));
    }

    public function put_schema()
    {
        return $this->db->renameSchema(Connection::$param['postgisschema'], json_decode(Input::get())->data->name);
    }

    public function delete_schema()
    {
        return $this->db->deleteSchema(Connection::$param['postgisschema']);
    }

    public function get_exist()
    {
        \app\models\Database::setDb("postgres");
        $this->db = new \app\models\Database();
        return $this->db->doesDbExist(Input::getPath()->part(4));
    }
}
