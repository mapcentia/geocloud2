<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\Connection;
use app\inc\Controller;
use app\inc\Input;

class Table extends Controller
{
    private $table;

    function __construct()
    {
        parent::__construct();

        $this->table = new \app\models\Table(Input::getPath()->part(4));
    }

    public function post_records()
    {
        $table = new \app\models\Table(null);
        $name = $table->create($_REQUEST['name'], $_REQUEST['type'], $_REQUEST['srid']);
        // Set layer editable
        $join = new \app\models\Table("settings.geometry_columns_join");
        $data = (array)json_decode(urldecode('{"data":{"editable":true,"_key_":"' . Connection::$param["postgisschema"] . '.' . $name['tableName'] . '.the_geom"}}'));
        $response = $this->auth();
        return (!$response['success']) ? $response : $join->updateRecord($data, "_key_");
    }

    public function delete_records()
    {
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->table->destroy();
    }

    public function get_columns()
    {
        return $this->table->getColumnsForExtGridAndStore(false, Input::get("i") ? true : false);
    }

    public function get_columnswithkey()
    {
        return $this->table->getColumnsForExtGridAndStore(true);
    }

    public function put_columns()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->updateColumn(json_decode(Input::get(null, true))->data, Input::getPath()->part(5));
    }

    public function post_columns()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->addColumn(Input::get()); // Is POSTED by a form
    }

    public function delete_columns()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->deleteColumn(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    public function get_structure()
    {
        $response = $this->auth(Input::getPath()->part(5), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getTableStructure(true);
    }

    public function put_versions()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->addVersioning(Input::getPath()->part(4));
    }

    public function delete_versions()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->removeVersioning(Input::getPath()->part(4));
    }

    public function get_distinct()
    {
        return $this->table->getGroupByAsArray(Input::getPath()->part(5));
    }

    public function put_workflow()
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->addWorkflow(Input::getPath()->part(4));
    }

    public function get_checkcolumn()
    {
        return $this->table->checkcolumn(Input::getPath()->part(5));
    }

    public function get_data()
    {
        $response = $this->auth(Input::getPath()->part(5), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getData(Input::getPath()->part(4),
            Input::get("start") ?: "0",
            Input::get("limit") ?: "100"
        );
    }

    public function put_data()
    {
        $data = (array)json_decode(urldecode(Input::get(null, true)));
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $key = Input::getPath()->part(5);
        $response = $this->auth(Input::getPath()->part(6), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->updateRecord($data, $key);
    }

    public function post_data()
    {
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $response = $this->auth(Input::getPath()->part(5), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->insertRecord();
    }

    public function delete_data()
    {
        $data = (array)json_decode(urldecode(Input::get(null, true)));
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $key = Input::getPath()->part(5);
        $response = $this->auth(Input::getPath()->part(6), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->deleteRecord($data, $key);
    }

    public function get_depend()
    {
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $response = $this->auth(Input::getPath()->part(4), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getDependTree();
    }
}
