<?php
namespace app\controllers;

use app\inc\Response;

class Database extends \app\inc\Controller
{
    private $db;
    private $request;

    function __construct()
    {
        $this->request = \app\inc\Input::getPath();
        $this->db = new \app\models\database();
    }

    public function get_schemas()
    {
        return Response::json($this->db->listAllSchemas($_POST['schema']));
    }

    public function post_schemas()
    {
        return Response::json($this->db->createSchema($_POST['schema']));
    }

    public function get_exits()
    {
        return Response::json(doesDbExist($this->request[5]));
    }
}


/*switch ($request[4]) {
    case "addschema":
        $response = $db->createSchema($_POST['schema']);
        break;
    case "getschemas":
        $response = $db->listAllSchemas($_POST['schema']);
        break;
    case "doesdbexist":
        $response = $db->doesDbExist($request[5]);
        break;
}*/
