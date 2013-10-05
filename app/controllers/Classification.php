<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Classification extends \app\inc\Controller
{
    private $class;

    function __construct()
    {
        $this->class = new \app\models\Classification(Input::getPath()->part(4));
    }

    public function get_index()
    {
        $id = Input::getPath()->part(5);
        return ($id) ? Response::json($this->class->get($id)) : Response::json($this->class->getAll());
    }

    public function post_index()
    {
        return Response::json($this->class->insert());
    }

    public function put_index()
    {
        return Response::json($this->class->update(Input::getPath()->part(5), json_decode(Input::get())->data));
    }

    public function delete_index()
    {
        return Response::json($this->class->destroy(json_decode(Input::get())->data));
    }
}

/*
$class = new _class($parts[5]);
//print_r($obj);

switch ($parts[4]){
	case "getall":
		$response = $class -> getAll();
	break;
	case "get":
		$response = $class -> get($parts[6]);
	break;
	case "update":
		$response = $class -> update($parts[6],$obj->data);
		makeMapFile($_SESSION['screen_name']);
	break;
	case "insert":
		$response = $class -> insert();
		makeMapFile($_SESSION['screen_name']);
	break;
	case "destroy":
		$response = $class -> destroy($obj->data);
		makeMapFile($_SESSION['screen_name']);
	break;
}
include_once("../server_footer.inc");
*/