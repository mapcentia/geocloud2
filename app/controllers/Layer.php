<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Layer extends \app\inc\Controller
{
    private $table;
    private $path;

    function __construct()
    {
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
        return Response::json($this->table->updateRecord((array)json_decode(Input::get()), "_key_"));
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

