<?php
namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;

class Table extends \app\inc\Controller
{
    private $table;

    function __construct()
    {
        $this->table = new \app\models\Table(Input::getPath()->part(4));
    }

    public function post_records()
    {
        $table = new \app\models\Table(null);
        $name = $table->create($_REQUEST['name'], $_REQUEST['type'], $_REQUEST['srid']);
        // Set layer editable
        $join = new \app\models\Table("settings.geometry_columns_join");
        $data = (array)json_decode(urldecode('{"data":{"editable":true,"_key_":"' . \app\conf\Connection::$param["postgisschema"] . '.' . $name['tableName'] . '.the_geom"}}'));
        $response = $this->auth();
        return (!$response['success']) ? $response : $join->updateRecord($data, "_key_");
    }

    public function delete_records()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response :$this->table->destroy();
    }

    public function get_columns()
    {
        return $this->table->getColumnsForExtGridAndStore();
    }

    public function get_columnswithkey()
    {
        return $this->table->getColumnsForExtGridAndStore(true);
    }

    public function put_columns()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response : $this->table->updateColumn(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    public function post_columns()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response :$this->table->addColumn(Input::get()); // Is POSTED by a form
    }

    public function delete_columns()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response :$this->table->deleteColumn(json_decode(Input::get())->data);
    }

    public function get_structure()
    {
        return Response::json($this->table->getTableStructure());
    }
    public function put_versions()
    {
        $response = $this->auth();
        return (!$response['success']) ? $response :$this->table->addVersioning(Input::getPath()->part(4));
    }
}
