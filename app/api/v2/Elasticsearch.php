<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v2;

use \app\inc\Input;
use \app\inc\Util;
use \app\conf\App;
use \GuzzleHttp\Client;

ini_set('max_execution_time', 0);

/**
 * Class Elasticsearch
 * @package app\api\v2
 */
class Elasticsearch extends \app\inc\Controller
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var string
     */
    protected $clientIp;

    /**
     * @var array
     */
    protected $settings;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    /**
     * Elasticsearch constructor.
     */
    function __construct()
    {
        parent::__construct();
        $this->clientIp = Util::clientIp();
        $this->host = App::$param['esHost'] ?: "http://127.0.0.1";
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
        if (!$this->settings = @file_get_contents(\app\conf\App::$param["path"] . "/app/conf/elasticsearch_settings.json")) {
            $this->settings = json_encode($defaultSettings);
        }

        // Init the Guzzle client
        $this->client = new Client([
            'timeout' => 10.0,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * @param string $db
     * @param string $key
     * @return mixed
     */
    private function checkAuth(string $db, string $key)
    {

        if (!$this->authApiKey($db, $key)) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            $response['code'] = 403;
            return $response;
        }

        return false; //Auth passed
    }

    /**
     * @return array
     */
    public function get_search(): array
    {

        // Get the URI params from request
        // /{action}/{user}/[indices]/[type]
        $r = func_get_arg(0);

        $response = [];
        $esResponse = null;
        $db = $r["user"];
        $type = isset($r["type"]) ? $r["type"] : false;
        $hasBody = false;

        // TODO auth using header instead of payload
        /*
        $get = Input::get();

        if (\app\conf\App::$param["useKeyForSearch"] == true) {
            if ($response = ($this->checkAuth($r["user"], $get['key']))) {
                return $response;
            }
        }
        */

        // Leading underscore can not be used i Es
        // So we add a "a"
        if (mb_substr($type, 0, 1, 'utf-8') == "_") {
            $type = "a" . $type;
        }

        // Support for query string search. The string is passed to Es unaltered
        // =====================================================================
        if (Input::getMethod() == "get" && Input::getQueryString()) {

            $q = Input::getQueryString();
            $hasBody = false; // Flag it as string query

        }

        // Support body payload in POST and GET requests.
        // ==============================================
        elseif (Input::getBody()) {

            $q = Input::getBody();
            $hasBody = true; // Flag it for having body

        }

        // Fallback to empty string query
        // ==============================
        else {

            $q = "";
        }

        $arr = [];

        $indices = explode(",", $r["indices"]);

        foreach ($indices as $v) {
            $arr[] = $db . ($v ? "_" . $v : "") . ($type ? "_" . $type : "_*");
        }

        $index = implode(",", $arr);

        $searchUrl = $this->host . ":9200/{$index}/{$type}/_search";

        try {

            if (Input::getMethod() == "post") {

                $esResponse = $this->client->post($searchUrl, ['body' => $q]);
            }

            if (Input::getMethod() == "get") {

                if ($hasBody) {

                    $esResponse = $this->client->get($searchUrl, ['body' => $q]);

                } else {

                    $esResponse = $this->client->get($searchUrl . "?" . $q);

                }
            }

        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = $e->getCode();
            return $response;
        }

        $obj = $esResponse->getBody();

        $response['json'] = $obj;
        return $response;
    }

    /**
     * @return array|mixed
     */
    public function get_map()
    {
        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key') ?: "")) {
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
        $url = $this->host . ":9200";
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
        $model = new \app\inc\Model();

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

        $pl = file_get_contents(\app\conf\App::$param["path"] . "/app/scripts/sql/notify_transaction.sql");
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

        $url = $this->host . ":9200/{$fullIndex}";
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
            $api = new \app\models\Sql_to_es("4326");
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
     * Creates a dedicated Es index with Meta
     * @return array|mixed
     */
    public function get_meta()
    {
        $typeahead = [
            "type" => "text",
            "analyzer" => "auto_complete_analyzer",
            "search_analyzer" => "auto_complete_search_analyzer",
            "fielddata" => true
        ];

        $map = [
            "geometry_columns_view" =>
                [
                    "properties" =>
                        [
                            "properties" =>
                                [
                                    "type" => "object",
                                    "properties" =>
                                        [
                                            "_key_" =>
                                                [
                                                    "type" => "keyword"
                                                ],

                                            "f_table_name" => $typeahead,

                                            "f_table_abstract" => $typeahead,

                                            "f_table_title" => $typeahead,

                                            "created" =>
                                                [
                                                    "type" => "text"
                                                ],

                                            "lastmodified" =>
                                                [
                                                    "type" => "text"
                                                ],

                                            "layergroup" => $typeahead,

                                            "uuid" =>
                                                [
                                                    "type" => "text"
                                                ],

                                            "tags" =>
                                                [
                                                    "type" => "text"
                                                ],

                                            "meta" =>
                                                [
                                                    "type" => "object",
                                                    "properties" => [
                                                        "meta_desc" => [

                                                            "type" => "text",
                                                            "analyzer" => "auto_complete_analyzer",
                                                            "search_analyzer" => "auto_complete_search_analyzer",
                                                            "fielddata" => true


                                                        ],
                                                        "layer_search_include" => [
                                                            "type" => "boolean"
                                                        ]
                                                    ]
                                                ]
                                        ]
                                ]
                        ]
                ]
        ];

        // Check if Es is online
        // =====================
        $url = $this->host . ":9200";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 2);

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

//        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
//            return $response;
//        }

        // Set vars
        // ========
        $triggerInstalled = false;
        $schema = "settings";
        $table = "geometry_columns_view";
        $index = $schema;
        $type = $table;
        $db = Input::getPath()->part(5);
        $fullTable = $schema . "." . $table;
        $fullIndex = $db . "_" . $schema . "_" . $table;
        $triggerSchema = Input::get('ts') ?: $schema;
        $triggerTable = Input::get('tt') ?: $table;
        $installTrigger = false;

        $es = new \app\models\Elasticsearch();
        $model = new \app\inc\Model();

        $priKey = "_key_";

        // Create or replace notify function in PG
        // =======================================
        $pl = file_get_contents(\app\conf\App::$param["path"] . "/app/scripts/sql/notify_transaction.sql");
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
        $url = $this->host . ":9200/{$fullIndex}";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
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
        if (1 == 1) {
            $sql = "SELECT * FROM {$fullTable}";
            $api = new \app\models\Sql_to_es("4326");
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
        if ($installTrigger) {
            $pl = "CREATE TRIGGER _gc2_notify_transaction_trigger AFTER INSERT OR UPDATE OR DELETE ON {$triggerSchema}.{$triggerTable} FOR EACH ROW EXECUTE PROCEDURE _gc2_notify_transaction('{$priKey}', '{$schema}','{$table}')";
            $result = $model->execQuery($pl, "PG");
            if (!$result) {
                $response['success'] = false;
                $response['code'] = "400";
                $response['message'] = "Could not create trigger";
                return $response;
            }
            $triggerInstalled = true;
        }
        $res["_index"] = $fullIndex;
        $res["_type"] = $type;
        $res["trigger_installed"] = $triggerInstalled;
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
        $api = new \app\models\Sql_to_es("4326");
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        $res = $api->sql($sql, $index, $type, $priKey, $db);
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

    public function post_search()
    {
        return $this->get_search(func_get_arg(0));
    }
}
