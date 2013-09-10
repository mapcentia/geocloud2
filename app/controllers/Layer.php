<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Layer extends \app\inc\Controller
{
    private $table;
    private $obj;
    private $path;
    public  $payload;

    function __construct()
    {
        $this->payload = json_decode(Input::get());
        $this->path = Input::getPath();
        $this->table = new \app\models\table("settings.geometry_columns_view");
    }

    public function get_records()
    {
        return Response::json($this->table->getRecords(true, "*", $whereClause = "f_table_schema='" . \Connection::$param["postgisschema"] . "'"));
    }
    public function get_groups()
    {
        return Response::json($this->response = $this->table->getGroupBy("layergroup"));
    }
    public function put_records()
    {
        $this->table = new \app\models\table("settings.geometry_columns_join");
        return Response::json($this->table->updateRecord((array)$this->payload, "_key_"));
    }
    public function get_columns()
    {
        $this->response = $this->table->getColumnsForExtGridAndStore();
    }
    public function get_columnswithkey()
    {
        return Response::json($this->table->getColumnsForExtGridAndStore(true));
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
