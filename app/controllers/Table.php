<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Table extends \app\inc\Controller
{
    private $table;

    function __construct()
    {
        $this->table = new \app\models\table(Input::getPath()->part(4));
    }

    public function post_records()
    {
        $table = new \app\models\Table(null);
        $response = $table->create($_REQUEST['name'], $_REQUEST['type'], $_REQUEST['srid']);
        return Response::json($response);
    }

    public function delete_records()
    {
        return Response::json($this->table->destroy());
    }

    public function get_columns()
    {
        return Response::json($this->table->getColumnsForExtGridAndStore());
    }

    public function get_columnswithkey()
    {
        return Response::json($this->table->getColumnsForExtGridAndStore(true));
    }

    public function put_columns()
    {
        return Response::json($this->table->updateColumn(json_decode(Input::get()->values())->data, Input::getPath()->part(5)));
    }

    public function post_columns()
    {
        return Response::json($this->table->addColumn(Input::get()->values())); // Is POSTED by a form
    }

    public function delete_columns()
    {
        return Response::json($this->table->deleteColumn(json_decode(Input::get())->data));
    }

    public function get_structure()
    {
        return Response::json($this->table->getTableStructure());
    }
}

/*switch ($this->path[4]) {
    case "getrecords": // only geometrycolumns table

        break;
    case "getgeojson": // only geometrycolumns table
        break;
    case "getallrecords": // All tables
        break;
    case "getgroupby": // All tables
        break;
    case "updaterecord": // All tables

        makeMapFile($_SESSION['screen_name']);
        break;
    case "destroy": // Geometry columns
        makeMapFile($_SESSION['screen_name']);
        break;
    case 'getcolumns': // All tables
        
        break;
    case 'getcolumnswithkey': // All tables
        break;
    case 'getstructure': // All tables
       
        break;
    case 'updatecolumn':
        makeMapFile($_SESSION['screen_name']);
        break;
    case 'createcolumn':
        makeMapFile($_SESSION['screen_name']);
        break;
    case 'destroycolumn':
        makeMapFile($_SESSION['screen_name']);
        break;
    case 'addcolumn':
        makeMapFile($_SESSION['screen_name']);
        break;
}*/
