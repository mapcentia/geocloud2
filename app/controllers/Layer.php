<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;
use \app\conf\Connection;

class Layer extends \app\inc\Controller
{
    private $table;

    function __construct()
    {
        $this->table = new \app\models\Layer();
    }

    public function get_records()
    {
        return Response::json($this->table->getRecords(true, "*", $whereClause = "f_table_schema='" . Connection::$param["postgisschema"] . "' ORDER BY sort_id"));
    }

    public function get_groups()
    {
        return Response::json($this->response = $this->table->getGroupBy("layergroup"));
    }

    public function put_records()
    {
        $this->table = new \app\models\table("settings.geometry_columns_join");
        return Response::json($this->table->updateRecord((array)json_decode(urldecode(Input::get())), "_key_"));
    }

    public function get_columns()
    {
        $this->response = $this->table->getColumnsForExtGridAndStore();
    }

    public function get_columnswithkey()
    {
        return Response::json($this->table->getColumnsForExtGridAndStore(true));
    }

    public function get_cartomobile()
    {
        return Response::json($this->table->getCartoMobileSettings(Input::getPath()->part(4)));
    }

    public function put_cartomobile()
    {
        return Response::json($this->table->updateCartoMobileSettings(json_decode(Input::get())->data, Input::getPath()->part(5)));
    }

    public function getValueFromKey($_key_, $column)
    {
        return $this->table->getValueFromKey($_key_, $column);
    }
}