<?php
/**
 * Long description for file
 *
 * Long description for file (if any)...
 *  
 * @category   API
 * @package    app\api\v1
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 * @since      File available since Release 2013.1
 *  
 */

namespace app\api\v1;

use app\inc\Controller;
use app\inc\Input;
use app\inc\Model;
use app\inc\Util;
use app\conf\App;
use app\models\Sql_to_es;

/**
 * Class Elasticsearch
 * @package app\api\v1
 */
class Elasticsearch extends Controller
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var null
     */
    protected $port;

    /**
     * @var string
     */
    protected $clientIp;

    /**
     * @var array
     */
    protected $settings;

    /**
     * Elasticsearch constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->clientIp = Util::clientIp();
        $this->host = App::$param['esHost'] ?: "http://127.0.0.1";
        $split = explode(":", $this->host);
        if (!empty($split[2])) {
            $this->port = $split[2];
        } else {
            $this->port = "9200";
        }
        $defaultSettings = array(
            "settings" => array(
                "number_of_shards" => 5,
                "number_of_replicas" => 0,
                "analysis" => array
                (
                    "analyzer" => array
                    (
                        "str_search_analyzer" => array
                        (
                            "type" => "custom",
                            "tokenizer" => "whitespace",
                            "filter" => array
                            (
                                "0" => "lowercase"
                            )

                        ),
                        "str_index_analyzer" => array
                        (
                            "type" => "custom",
                            "tokenizer" => "whitespace",
                            "filter" => array
                            (
                                "0" => "lowercase",
                                "1" => "substring"
                            )

                        )

                    ),
                    "filter" => array
                    (
                        "substring" => array
                        (
                            "type" => "edgeNGram",
                            "min_gram" => 1,
                            "max_gram" => 255
                        )

                    )

                )

            )
        );
        // Check if there are custom settings
        if (!$this->settings = @file_get_contents(App::$param["path"] . "/app/conf/elasticsearch_settings.json")) {
            $this->settings = json_encode($defaultSettings);
        }
    }

    /**
     * @param string $db
     * @param string $key
     * @return mixed
     */
    private function checkAuth($db, $key)
    {
        $trusted = false;
        foreach (App::$param["trustedAddresses"] as $address) {
            if (Util::ipInRange($this->clientIp, $address)) {
                $trusted = true;
                break;
            }
        }
        if (!$trusted) {
            if (!$this->authApiKey($db, $key)) {
                $response['success'] = false;
                $response['message'] = "Not the right key.";
                $response['code'] = 403;
                return $response;
            }
        }
        return false; //Auth passed
    }

    /**
     * @return array|mixed
     */
    public function get_bulk()
    {
        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
            return $response;
        }
        if (sizeof(Input::getPath()->parts()) < 9 || Input::getPath()->part(8) == "") {
            $response['success'] = false;
            $response['message'] = "The URI must be in this form: /api/v1/elasticsearch/bulk/[user]/[index]/[type]/[id]?q=[SELECT query]";
            return $response;
        }
        $api = new Sql_to_es("4326");
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        return $api->runSql(rawurldecode(Input::get('q')), Input::getPath()->part(6), Input::getPath()->part(7), Input::getPath()->part(8), Input::getPath()->part(5));
    }

    /**
     * @return array|mixed
     */
    public function get_search()
    {
        $response = [];
        $get = Input::get();
        if (App::$param["useKeyForSearch"] == true) {
            if ($response = ($this->checkAuth(Input::getPath()->part(5), $get['key']))) {
                return $response;
            }
        }
        $type = Input::getPath()->part(7);
        if (mb_substr($type, 0, 1, 'utf-8') == "_") {
            $type = "a" . $type;
        }
        $q = isset($get['q']) ? urldecode($get['q']) : "";
        $size = isset($get['size']) ? "&size={$get['size']}" : "";
        $from = isset($get['from']) ? "&from={$get['from']}" : "";
        $pretty = isset($get['pretty']) ? $get['pretty'] : "false";
        $arr = array();

        $indices = explode(",", Input::getPath()->part(6));
        $db = Input::getPath()->part(5);
        foreach ($indices as $v) {
            $arr[] = $db . ($v ? "_" . $v : "") . ($type ? "_" . $type : "_*");
        }
        $index = implode(",", $arr);
        $searchUrl = $this->host . "/{$index}/_search?pretty={$pretty}{$size}{$from}";
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
        ));
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    /**
     * @return array|mixed
     */
    public function put_map()
    {
        $put = Input::get();
        if ($response = $this->checkAuth(Input::getPath()->part(5), $put['key'])) {
            return $response;
        }
        $index = Input::getPath()->part(5) . "_" . Input::getPath()->part(6);
        $es = new \app\models\Elasticsearch();
        return $es->map($index, Input::getPath()->part(7), $put["map"]);
    }

    /**
     * @return array|mixed
     */
    public function post_create()
    {
        $post = Input::get();
        if ($response = $this->checkAuth(Input::getPath()->part(5), $post['key'])) {
            return $response;
        }
        $index = Input::getPath()->part(5) . "_" . Input::getPath()->part(6);
        $es = new \app\models\Elasticsearch();
        return $es->createIndex($index, $post["map"]); // TODO rename "map" to "settings"
    }

    /**
     * @return mixed
     */
    public function delete_delete()
    {
        $type = Input::getPath()->part(7);
        if (mb_substr($type, 0, 1, 'utf-8') == "_") {
            $type = "a" . $type;
        }
        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
            return $response;
        }
        $index = Input::getPath()->part(5) . (Input::getPath()->part(6) ? "_" . Input::getPath()->part(6) : "");
        $es = new \app\models\Elasticsearch();
        $res = $es->delete($index, $type, Input::getPath()->part(8));
        $obj = json_decode($res["json"], true);
        if (isset($obj["error"]) && $obj["error"] != false) {
            $response['success'] = false;
            $response['message'] = $obj["error"];
            $response['code'] = $obj["status"];
            return $response;
        }
        $response['success'] = true;
        $response['message'] = $obj;
        return $response;
    }

    /**
     *
     * @return array|mixed
     */
    public function get_map()
    {
        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
            return $response;
        }
        $schema = Input::getPath()->part(6);
        $table = Input::getPath()->part(7);
        $fullTable = $schema . "." . $table;
        $es = new \app\models\Elasticsearch();
        return $es->createMapFromTable($fullTable);
    }

    /**
     * @return array|mixed
     */
    public function post_river()
    {

        // Check if Es is online
        // =====================
        $url = $this->host . ":{$this->port}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ));
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpcode != "200") {
            $response['success'] = false;
            $response['message'] = "Elasticsearch is not online";
            $response['code'] = $httpcode;
            return $response;
        }

        // Auth
        // ====

        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
            return $response;
        }

        // Set vars
        // ========

        $triggerInstalled = false;
        $schema = Input::getPath()->part(6);
        $table = Input::getPath()->part(7);
        $index = $schema;
        $type = $table;
        $db = Input::getPath()->part(5);
        $fullTable = $schema . "." . $table;
        $fullIndex = $db . "_" . $schema . "_" . $table;
        $insert = Input::get('insert') ?: "t";
        $triggerSchema = Input::get('ts') ?: $schema;
        $triggerTable = Input::get('tt') ?: $table;
        $installTrigger = false;

        if (mb_substr($type, 0, 1, 'utf-8') == "_") {
            $type = "a" . $type;
        }

        $es = new \app\models\Elasticsearch();
        $model = new Model();

        // Check which relation type we are dealing with
        // =============================================

        $relationCheck = $model->isTableOrView($triggerSchema . "." . $triggerTable);
        if (!$relationCheck["success"]) {
            return Array
            (
                "success" => false,
                "message" => "Trigger table doesn't exists",
                "code" => "406"
            );
        } else {
            if ($relationCheck["data"] == "TABLE") {
                $installTrigger = true;
            }
        }
        $relationType = $model->isTableOrView($fullTable);
        $priObj = $model->getPrimeryKey($fullTable);
        $priKey = $priObj["attname"];
        $priKey = Input::get('tp') ?: $priKey;
        $model->close();// Close the PDO connection

        // Create or replace notify function in PG
        // =======================================

        $pl = file_get_contents(App::$param["path"] . "/app/scripts/sql/notify_transaction.sql");
        // TODO check if sprintf is needed
        $pl = sprintf($pl, $priKey, $priKey, $priKey);
        $result = $model->execQuery($pl, "PG");
        if (!$result) {
            $response['success'] = false;
            return $response;
        }

        // Drop the trigger
        // ================

        $pl = "DROP TRIGGER IF EXISTS _gc2_notify_transaction_trigger ON {$triggerSchema}.{$triggerTable}";
        $result = $model->execQuery($pl, "PG");
        if (!$result) {
            $response['success'] = false;
            $response['code'] = "400";
            $response['message'] = "Could not drop trigger";
            return $response;
        }

        // Delete the index if exist
        // =========================

        $url = $this->host . ":{$this->port}/{$fullIndex}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ));
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpcode == "200") {
            $res = $es->delete($fullIndex);
            $obj = json_decode($res["json"], true);
            if (isset($obj["error"]) && $obj["error"] != false) {
                $response['success'] = false;
                $response['message'] = $obj["error"];
                $response['code'] = $obj["status"];
                return $response;
            }
        }

        // Create the index with settings
        // ==============================

        $res = $es->createIndex($fullIndex, $this->settings);
        $obj = json_decode($res["json"], true);
        if (isset($obj["error"]) && $obj["error"] != false) {
            $response['success'] = false;
            $response['message'] = $obj["error"];
            $response['code'] = $obj["status"];
            return $response;
        }

        // Create mapping
        // ==============

        $map = $es->createMapFromTable($fullTable);
        $res = $es->map($fullIndex, $type, json_encode($map));
        $obj = json_decode($res["json"], true);
        if (isset($obj["error"]) && $obj["error"] != false) {
            $response['success'] = false;
            $response['message'] = $obj["error"];
            $response['code'] = $obj["status"];
            return $response;
        }

        // Bulk insert
        // ===========

        if ($insert == "t") {
            $sql = "SELECT * FROM {$fullTable}";
            $api = new Sql_to_es("4326");
            $api->execQuery("set client_encoding='UTF8'", "PDO");
            $res = $api->runSql($sql, $index, $type, $priKey, $db);
            if (!$res["success"]) {
                return $res;
            }
            $res["Indexed"] = true;
        } else {
            $res = array("succes" => true, "indexed" => false, "message" => "Indexing skipped");
        }

        // Create the trigger
        // ==================

        $triggerInstalledIn = null;
        if ($relationType["data"] == "TABLE" || ($installTrigger)) {
            $pl = "CREATE TRIGGER _gc2_notify_transaction_trigger AFTER INSERT OR UPDATE OR DELETE ON {$triggerSchema}.{$triggerTable} FOR EACH ROW EXECUTE PROCEDURE _gc2_notify_transaction('{$priKey}', '{$schema}','{$table}')";
            $result = $model->execQuery($pl, "PG");
            if (!$result) {
                $response['success'] = false;
                $response['code'] = "400";
                $response['message'] = "Could not create trigger";
                return $response;
            }
            $triggerInstalled = true;
            $triggerInstalledIn = "{$triggerSchema}.{$triggerTable}";
        }
        $res["_index"] = $fullIndex;
        $res["_type"] = $type;
        $res["relation"] = $relationType["data"];
        $res["trigger_installed"] = $triggerInstalled;
        $res["trigger_installed_in"] = $triggerInstalledIn;
        return $res;
    }

    /**
     * @return array|mixed
     */
    public function put_upsert()
    {
        $put = Input::get();
        if ($response = $this->checkAuth(Input::getPath()->part(5), $put['key'])) {
            return $response;
        }
        $schema = Input::getPath()->part(6);
        $table = Input::getPath()->part(7);
        $priKey = Input::getPath()->part(8);
        $id = Input::getPath()->part(9);
        $index = $schema;
        $type = $table;
        $db = Input::getPath()->part(5);
        $fullTable = $schema . "." . $table;
        $fullIndex = $db . "_" . $schema . "_" . $table;

        if (mb_substr($type, 0, 1, 'utf-8') == "_") {
            $type = "a" . $type;
        }

        $sql = "SELECT * FROM {$fullTable} WHERE \"{$priKey}\"='{$id}'";
        $api = new Sql_to_es("4326");
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        $res = $api->runSql($sql, $index, $type, $priKey, $db);
        if (!$res["success"]) {
            return $res;
        }
        $res["_index"] = $fullIndex;
        $res["_type"] = $type;
        $res["_id"] = $id;
        return $res;
    }

    // Wrappers for HTTP GET
    public function get_river()
    {
        return $this->post_river();
    }

    public function get_upsert()
    {
        return $this->put_upsert();
    }

    public function get_delete()
    {
        return $this->delete_delete();
    }

    // Wrappers for HTTP POST
    public function post_bulk()
    {
        return $this->get_bulk();
    }

    public function post_search()
    {
        return $this->get_search();
    }
}