<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Util;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;

/**
 * Class Layer
 * @package app\controllers
 */
class Layer extends Controller
{
    /**
     * @var \app\models\Layer
     */
    private $table;

    /**
     * @var \app\models\Table
     */
    private $geometryJoinTable;

    function __construct()
    {
        parent::__construct();
        $this->table = new \app\models\Layer();
        $this->geometryJoinTable = new \app\models\Table("settings.geometry_columns_join");
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_records(): array
    {
        return $this->table->getRecords(true, Input::getPath()->part(4));
    }

    /**
     * @return array<string>
     */
    public function get_groups(): array
    {
        $groups = $this->table->getGroups("layergroup");
        if (array_search(array("group" => ""), $groups["data"]) !== false) unset($groups["data"][array_search(array("group" => ""), $groups["data"])]);
        $groups["data"] = array_values($groups["data"]);
        array_unshift($groups["data"], array("group" => ""));
        return $groups;
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException
     */
    public function put_records(): array
    {
        $data = json_decode(urldecode(Input::get(null, true)), true);
        if (!is_array($data["data"][0])) {
            $data["data"] = [0 => $data["data"]];
        }
        foreach ($data["data"] as $datum) {
            $response = $this->auth($datum["_key_"]);
            if ($response['success']) {
                continue;
            } else {
                return $response;
            }
        }
        return $this->geometryJoinTable->updateRecord($data['data'], "_key_", false, !empty(Input::getPath()->part(5)));
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_records(): array
    {
        $input = json_decode(Input::get());
        $response = $this->auth(null, array());
        return (!$response['success']) ? $response : $this->table->delete($input->data);
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_columns(): array
    {
        return $this->response = $this->table->getColumnsForExtGridAndStore();
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_columnswithkey(): array
    {
        return $this->table->getColumnsForExtGridAndStore(true);
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_elasticsearch(): array
    {
        $response = $this->auth(Input::getPath()->part(4), array("read" => true, "write" => true, "all" => true));
        return !$response['success'] ? $response : $this->table->getElasticsearchMapping(Input::getPath()->part(4));
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_elasticsearch(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return !$response['success'] ? $response : $this->table->updateElasticsearchMapping(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    /**
     * @param string $_key_
     * @param string $column
     * @return string|null
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function getValueFromKey(string $_key_, string $column): ?string
    {
        return $this->table->getValueFromKey($_key_, $column);
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_name(): array
    {
        $response = $this->auth(null, array());
        $this->table->begin();
        $res = !$response['success'] ? $response : $this->table->rename(urldecode(Input::getPath()->part(4)), json_decode(Input::get())->data);
        $this->table->commit();
        return $res;
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function put_schema(): array
    {
        $input = json_decode(Input::get());
        $response = $this->auth(null, array(), true); // Never sub-user
        $this->table->begin();
        $res = !$response['success'] ? $response : $this->table->setSchema($input->data->tables, $input->data->schema);
        $this->table->commit();
        return $res;
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_privileges(): array
    {
        $response = $this->auth(null, array());
        return !$response['success'] ? $response : $this->table->getPrivileges(Input::getPath()->part(4));
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_privileges(): array
    {
        $response = $this->auth(null, array());
        return !$response['success'] ? $response : $this->table->updatePrivileges(json_decode(Input::get())->data);
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function put_copymeta(): array
    {
        $response = $this->auth(Input::getPath()->part(4));
        return !$response['success'] ? $response : $this->table->copyMeta(Input::getPath()->part(4), json_decode(Input::get())->data);
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_roles(): array
    {
        $response = $this->auth(null, array());
        return !$response['success'] ? $response : $this->table->getRoles(Input::getPath()->part(4));
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException
     */
    public function put_roles(): array
    {
        $response = $this->auth(null, array());
        return !$response['success'] ? $response : $this->table->updateRoles(json_decode(Input::get())->data);
    }

    /**
     * @return array<mixed>
     */
    public function get_tags(): array
    {
        return $this->table->getTags();
    }

    public function post_view(): array
    {
        $data = json_decode(Input::get(), true);
        $response = $this->auth(null, array());
        if (!$response['success']) {
            return $response;
        }
        if ($data['mat']) {
            $this->table->createMatView(Util::base64urlDecode($data['q']), $data['name']);
        } else {
            $this->table->createView(Util::base64urlDecode($data['q']), $data['name']);
        }
        return ['success' => true];
    }
}
