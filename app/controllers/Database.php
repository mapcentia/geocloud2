<?php
namespace app\controllers;

use \app\inc\Input;
use app\inc\Response;

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

        return Response::json($this->db->listAllSchemas());
    }

    public function post_schemas()
    {
        return Response::json($this->db->createSchema($_POST['schema']));
    }

    public function get_exist()
    {
        \Connection::$param['postgisdb'] = "postgres";
        $this->db = new \app\models\Database();
        return Response::json($this->db->doesDbExist(Input::getPath()->part(4)));
    }
}
