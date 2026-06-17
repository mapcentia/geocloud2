<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use app\inc\Util;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Psr\Cache\InvalidArgumentException;
use Throwable;

class Layer extends Controller
{
    private \app\models\Layer $layer;
    private \app\models\Table $geometryJoinTable;
    private readonly ?string $rel;

    function __construct()
    {
        parent::__construct();
        $this->rel = Input::getPath()->part(4);
        $this->layer = new \app\models\Layer();
        $this->geometryJoinTable = new \app\models\Table("settings.geometry_columns_join");
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|Throwable
     */
    public function get_records(): array
    {
        return $this->layer->getRecords(true, $this->rel);
    }

    /**
     * @return array<string>
     */
    public function get_groups(): array
    {
        $groups = $this->layer->getGroups("layergroup");
        if (in_array(array("group" => ""), $groups["data"])) unset($groups["data"][array_search(array("group" => ""), $groups["data"])]);
        $groups["data"] = array_values($groups["data"]);
        array_unshift($groups["data"], array("group" => ""));
        return $groups;
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException|InvalidArgumentException|GC2Exception
     */
    public function put_records(): array
    {
        $data = json_decode(Input::get(null, true), true);
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
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function delete_records(): array
    {
        $data = json_decode(Input::get());
        $response = $this->isOwner();
        return (!$response['success']) ? $response : $this->layer->delete($data->data);
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_columns(): array
    {
        return $this->response = $this->layer->getColumnsForExtGridAndStore();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_columnswithkey(): array
    {
        return $this->layer->getColumnsForExtGridAndStore(true);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function get_elasticsearch(): array
    {
        $response = $this->auth($this->rel, array("read" => true, "write" => true, "all" => true));
        return !$response['success'] ? $response : $this->layer->getElasticsearchMapping($this->rel);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_elasticsearch(): array
    {
        $response = $this->auth(Input::getPath()->part(5));
        return !$response['success'] ? $response : $this->layer->updateElasticsearchMapping(json_decode(Input::get())->data, Input::getPath()->part(5));
    }

    /**
     * @param string $_key_
     * @param string $column
     * @return string|null
     * @throws PDOException
     */
    public function getValueFromKey(string $_key_, string $column): ?string
    {
        return $this->layer->getValueFromKey($_key_, $column);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_name(): array
    {
        $data = json_decode(Input::get(), true);
        $response = $this->auth($data['id'], array());
        $this->layer->begin();
        $res = !$response['success'] ? $response : $this->layer->rename(urldecode($this->rel), $data['data']['name']);
        $this->layer->commit();
        return $res;
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_schema(): array
    {
        $input = json_decode(Input::get());
        $response = $this->isSuperUser(); // Never sub-user
        $this->layer->begin();
        $res = !$response['success'] ? $response : $this->layer->setSchema($input->data->tables, $input->data->schema);
        $this->layer->commit();
        return $res;
    }

    /**
     * @return array
     */
    public function get_privileges(): array
    {
        $response = $this->isOwner();
        return !$response['success'] ? $response : $this->layer->getPrivileges($this->rel);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_privileges(): array
    {
        $data = json_decode(Input::get())->data;
        $response = $this->auth($data->_key_, array());
        return !$response['success'] ? $response : $this->layer->updatePrivileges($data);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_copymeta(): array
    {
        $response = $this->auth($this->rel);
        return !$response['success'] ? $response : $this->layer->copyMeta($this->rel, json_decode(Input::get())->data);
    }

    /**
     * @return array
     * @throws InvalidArgumentException|GC2Exception
     */
    public function get_roles(): array
    {
        $response = $this->auth($this->rel, []);
        return !$response['success'] ? $response : $this->layer->getRoles($this->rel);
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws InvalidArgumentException|GC2Exception
     */
    public function put_roles(): array
    {
        $response = $this->isOwner();
        return !$response['success'] ? $response : $this->layer->updateRoles(json_decode(Input::get())->data);
    }

    /**
     * @return array
     */
    public function get_tags(): array
    {
        return $this->layer->getTags();
    }

    /**
     * @throws InvalidArgumentException|GC2Exception
     */
    public function post_view(): array
    {
        $data = json_decode(Input::get(), true);
        $response = $this->isOwner();
        if (!$response['success']) {
            return $response;
        }
        if ($data['mat']) {
            $this->layer->createMatView(Util::base64urlDecode($data['q']), $data['name']);
        } else {
            $this->layer->createView(Util::base64urlDecode($data['q']), $data['name']);
        }
        return ['success' => true];
    }
}
