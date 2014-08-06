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
        return $this->table->getRecords(true, "*", $whereClause =  Connection::$param["postgisschema"]);
    }

    public function get_groups()
    {
        return $this->response = $this->table->getGroupBy("layergroup");
    }

    public function put_records()
    {
        $this->table = new \app\models\table("settings.geometry_columns_join");
        $data = (array)json_decode(urldecode(Input::get()));
        $data["data"]->editable = ($data["data"]->editable) ? : "0";
        return $this->table->updateRecord($data, "_key_");
    }
    public function delete_records(){
        $input = json_decode(Input::get());
        print_r($input);
        return $this->table->delete($input->data);
    }

    public function get_columns()
    {
        $this->response = $this->table->getColumnsForExtGridAndStore();
    }

    public function get_columnswithkey()
    {
        return $this->table->getColumnsForExtGridAndStore(true);
    }

    public function get_cartomobile()
    {
        return $this->table->getCartoMobileSettings(Input::getPath()->part(4));
    }

    public function put_cartomobile()
    {
        return $this->table->updateCartoMobileSettings(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    public function getValueFromKey($_key_, $column)
    {
        return $this->table->getValueFromKey($_key_, $column);
    }
    public function put_schema(){
        $input = json_decode(Input::get());
        return $this->table->setSchema($input->data->tables,$input->data->schema);
    }
}