<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\conf\Connection;
use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

class Table extends Controller
{
    private \app\models\Table $table;

    /**
     * @throws PhpfastcacheInvalidArgumentException
     * @throws GC2Exception
     */
    function __construct()
    {
        parent::__construct();
        $this->table = new \app\models\Table(Input::getPath()->part(4), false, true, false);
    }

    /**
     * @throws InvalidArgumentException|GC2Exception
     */
    public function post_records(): array
    {
        $table = new \app\models\Table(null);
        $name = $table->create($_REQUEST['name'], $_REQUEST['type'], $_REQUEST['srid']);
        // Set layer editable
        $join = new \app\models\Table("settings.geometry_columns_join");
        $key = Connection::$param["postgisschema"] . '.' . $name['tableName'] . '.the_geom';
        $response = $this->isOwner();
        $data['_key_'] = $key;
        $data['editable'] = true;
        return (!$response['success']) ? $response : $join->updateRecord($data, "_key_");
    }

    /**
     * @throws InvalidArgumentException|GC2Exception
     */
    public function delete_records(): array
    {
        $response = $this->isOwner();
        return (!$response['success']) ? $response : $this->table->destroy();
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_columns(): array
    {
        return $this->table->getColumnsForExtGridAndStore(false, (bool)Input::get("i"));
    }

    /**
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_columnswithkey(): array
    {
        return $this->table->getColumnsForExtGridAndStore(true);
    }

    /**
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function put_columns(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->updateColumn(json_decode(Input::get(null, true))->data, Input::getPath()->part(5));
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException|GC2Exception
     */
    public function post_columns(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->addColumn(Input::get()); // Is POSTED by a form
    }

    /**
     * @throws GC2Exception
     * @throws InvalidArgumentException|GC2Exception
     */
    public function delete_columns(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->deleteColumn(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function get_structure(): array
    {
        $response = $this->auth(Input::getPath()->part(5), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getTableStructure(true);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_versions(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->addVersioning();
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function delete_versions(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return (!$response['success']) ? $response : $this->table->removeVersioning();
    }

    /**
     * @return array
     */
    public function get_distinct(): array
    {
        return $this->table->getGroupByAsArray(Input::getPath()->part(5));
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_workflow(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return (!$response['success']) ? $response : $this->table->addWorkflow();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_checkcolumn(): array
    {
        return $this->table->checkcolumn(Input::getPath()->part(5));
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function get_data(): array
    {
        $response = $this->auth(Input::getPath()->part(5), array("read" => true, "write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getData(
            Input::getPath()->part(4),
            Input::get("start") ?: "0",
            Input::get("limit") ?: "100"
        );
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_data(): array
    {
        $data = json_decode(urldecode(Input::get(null, true)), true);
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $key = Input::getPath()->part(5);
        $response = $this->auth(Input::getPath()->part(6), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->updateRecord($data['data'], $key);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function post_data(): array
    {
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $response = $this->auth(Input::getPath()->part(5), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->insertRecord();
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function delete_data(): array
    {
        $data = (array)json_decode(urldecode(Input::get(null, true)));
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $key = Input::getPath()->part(5);
        $response = $this->auth(Input::getPath()->part(6), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->deleteRecord($data, $key);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function get_depend(): array
    {
        $this->table = new \app\models\table(Input::getPath()->part(4));
        $response = $this->auth(Input::getPath()->part(4), array("write" => true, "all" => true));
        return (!$response['success']) ? $response : $this->table->getDependTree();
    }
}
