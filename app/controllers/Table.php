<?php
namespace app\inc\controllers;


class Table extends \app\inc\Controller
{
    private $table;
    private $obj;
    private $request;
    public $response;
    

    function __construct()
    {
        $this->table = new \app\models\table($this->request[5]);
        $this->request = \app\inc\Input::getPath();
        if ($HTTP_RAW_POST_DATA) {
            $this->obj = json_decode($HTTP_RAW_POST_DATA);
        }
    }

    public function get_records()
    {
        $this->response = $this->table->getRecords(true, "*", $whereClause = "f_table_schema='" . \Connection::$param["postgisschema"] . "'");
    }
    public function get_groups()
    {
        $this->response = $this->table->getGroupBy($this->request[6]);
    }
    public function put_records()
    {
        $this->response  = $this->table->updateRecord($this->obj->data, $this->request[6]);
    }
    public function delete_records()
    {
        $this->response  = $this->table->destroy();
    }
    public function get_columns()
    {
        $this->response = $this->table->getColumnsForExtGridAndStore();
    }
    public function get_columnswithkey()
    {
        $this->response = $this->table->getColumnsForExtGridAndStore(true);   
    }
    public function put_columns()
    {
        $this->response = $this->table->updateColumn($this->obj->data, $this->request[6]);
    }
    public function post_columns()
    {
        $this->response = $this->table->addColumn($_POST); // Is POSTED by a form
    }
    public function delete_columns()
    {
        $this->response = $this->table->deleteColumn($this->obj->data);
    }
    public function addcolumn() // Is this needed??
    {
        $this->response = $this->table->addColumn($this->obj->data);

    }
    public function get_structure()
    {
        $this->response = $this->table->getTableStructure();
    }
}

switch ($this->request[4]) {
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
}
