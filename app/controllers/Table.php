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
        $join = new \app\models\table("settings.geometry_columns_join");
        $data = (array)json_decode(urldecode('{"data":{"editable":true,"_key_":"' . \app\conf\Connection::$param["postgisschema"] . '.' . $name['tableName'] . '.the_geom"}}'));
        return Response::json($join->updateRecord($data, "_key_"));
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
        return Response::json($this->table->updateColumn(json_decode(Input::get())->data, Input::getPath()->part(5)));
    }

    public function post_columns()
    {
        return Response::json($this->table->addColumn(Input::get())); // Is POSTED by a form
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
