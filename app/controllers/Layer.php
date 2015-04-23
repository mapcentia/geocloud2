<?php
namespace app\controllers;

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
        return $this->table->getRecords(true, "*", $whereClause = Connection::$param["postgisschema"]);
    }

    public function get_groups()
    {
        $groups = $this->table->getGroupBy("layergroup");
        if (array_search(array("group" => ""), $groups["data"]) !== false) unset($groups["data"][array_search(array("group" => ""), $groups["data"])]);
        $groups["data"] = array_values($groups["data"]);
        array_unshift($groups["data"], array("group" => ""));
        return $groups;
    }

    public function put_records()
    {
        $this->table = new \app\models\table("settings.geometry_columns_join");
        $data = (array)json_decode(urldecode(Input::get(null, true)));
        if (isset($data["data"]->editable)) {
            $data["data"]->editable = ($data["data"]->editable) ?: "0";
        }
        if (isset($data["data"]->skipconflict)) {
            $data["data"]->skipconflict = ($data["data"]->skipconflict) ?: "0";
        }
        $response = $this->auth($data["data"]->_key_);
        return (!$response['success']) ? $response : $this->table->updateRecord($data, "_key_");
    }

    public function delete_records()
    {
        $input = json_decode(Input::get());
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->table->delete($input->data);
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
        $response = $this->auth(Input::getPath()->part(4), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getCartoMobileSettings(Input::getPath()->part(4));
    }

    public function put_cartomobile()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->updateCartoMobileSettings(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    public function getValueFromKey($_key_, $column)
    {
        return $this->table->getValueFromKey($_key_, $column);
    }

    public function put_name()
    {
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->table->rename(Input::getPath()->part(4),json_decode(Input::get())->data);
    }

    public function put_schema()
    {
        $input = json_decode(Input::get());
        $response = $this->auth(null, array(), true); // Never sub-user
        return (!$response['success']) ? $response : $this->table->setSchema($input->data->tables, $input->data->schema);
    }

    public function get_privileges()
    {
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->table->getPrivileges(Input::getPath()->part(4));
    }

    public function put_privileges()
    {
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->table->updatePrivileges(json_decode(Input::get())->data);
    }
}