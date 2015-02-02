<?php
namespace app\api\v1;

use \app\inc\Input;
use \app\inc\Util;
use \app\conf\App;

class Elasticsearch extends \app\inc\Controller
{
    protected $guest;
    protected $host;

    function __construct()
    {
        $this->clientIp = Util::clientIp();
        $this->host = App::$param['esHost'] ?: "http://127.0.0.1";
    }

    private function checkAuth($db, $key)
    {
        //die($this->clientIp);
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

    public function get_bulk()
    {
        ini_set('max_execution_time', 300);
        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
            return $response;
        }
        if (sizeof(Input::getPath()->parts()) < 9 || Input::getPath()->part(8) == "") {
            $response['success'] = false;
            $response['message'] = "The URI must be in this form: /api/v1/elasticsearch/bulk/[user]/[index]/[type]/[id]?q=[SELECT query]";
            return $response;
        }
        $api = new \app\models\Sql_to_es("4326");
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        return $api->sql(rawurldecode(Input::get('q')), Input::getPath()->part(6), Input::getPath()->part(7), Input::getPath()->part(8), Input::getPath()->part(5));
    }

    public function get_search()
    {
        $get = Input::get();
        if (\app\conf\App::$param["useKeyForSearch"] == true) {
            if ($response = ($this->checkAuth(Input::getPath()->part(5), $get['key']))) {
                return $response;
            }
        }
        $q = urldecode($get['q']);
        $size = ($get['size']) ?: 10;
        $pretty = (($get['pretty']) || $get['pretty'] == "true") ? $get['pretty'] : "false";
        $arr = array();

        $indices = explode(",", Input::getPath()->part(6));
        $db = Input::getPath()->part(5);
        foreach ($indices as $v) {
            $arr[] = $db . "_" . $v;
        }
        $index = implode(",", $arr);
        $searchUrl = $this->host . ":9200/{$index}/" . Input::getPath()->part(7) . "/_search?pretty={$pretty}&size={$size}";
        $ch = curl_init($searchUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        //error_log($searchUrl);
        return $response;
    }

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

    public function post_create()
    {
        $post = Input::get();
        if ($response = $this->checkAuth(Input::getPath()->part(5), $post['key'])) {
            return $response;
        }
        $index = Input::getPath()->part(5) . "_" . Input::getPath()->part(6);
        $es = new \app\models\Elasticsearch();
        return $es->createIndex($index, $post["map"]);
    }

    public function delete_delete()
    {
        $delete = Input::get();
        if ($response = $this->checkAuth(Input::getPath()->part(5), $delete['key'])) {
            return $response;
        }
        $index = Input::getPath()->part(5) . "_" . Input::getPath()->part(6);
        $es = new \app\models\Elasticsearch();
        $res = $es->delete($index, Input::getPath()->part(7), Input::getPath()->part(8));
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

    public function get_map()
    {
        $schema = Input::getPath()->part(6);
        $table = Input::getPath()->part(7);
        $fullTable = $schema . "." . $table;
        $es = new \app\models\Elasticsearch();
        return $es->createMapFromTable($fullTable);
    }

    public function post_river()
    {
        if ($response = $this->checkAuth(Input::getPath()->part(5), Input::get('key'))) {
            return $response;
        }

        $triggerInstalled = false;
        $schema = Input::getPath()->part(6);
        $table = Input::getPath()->part(7);
        $index = $schema;
        $type = $table;
        $db = Input::getPath()->part(5);
        $fullIndex = $db . "_" . $index;
        $fullTable = $schema . "." . $table;
        $insert = Input::get('insert') ?: "t";
        $triggerSchema = Input::get('ts') ?: $schema;
        $triggerTable = Input::get('tt') ?: $table;
        $installTrigger = false;

        $model = new \app\inc\Model();

        $relationCheck = $model->isTableOrView($triggerSchema . "." . $triggerTable);
        if (!$relationCheck["success"]) {
            return $relationCheck;
        }
        else {
            if ($relationCheck["data"] == "table") {
                $installTrigger = true;
            }
        }

        $relationType = $model->isTableOrView($fullTable);
        $priObj = $model->getPrimeryKey($fullTable);
        $priKey = $priObj["attname"];

        $model->close();// Close the PDO connection

        $pl = file_get_contents(\app\conf\App::$param["path"] . "/app/scripts/sql/notify_transaction.sql");
        $pl = sprintf($pl, $priKey, $priKey, $priKey);

        $result = $model->execQuery($pl, "PG");
        if (!$result) {
            $response['success'] = false;
            return $response;
        }

        // Drop the trigger
        $pl = "DROP TRIGGER IF EXISTS _gc2_notify_transaction_trigger ON {$triggerSchema}.{$triggerTable}";
        $result = $model->execQuery($pl, "PG");

        $settings = '{
          "settings": {
            "analysis": {
              "analyzer": {
                "str_search_analyzer": {
                  "type" : "custom",
                  "tokenizer": "whitespace",
                  "filter": [
                    "lowercase"
                  ]
                },
                "str_index_analyzer": {
                  "type" : "custom",
                  "tokenizer": "whitespace",
                  "filter": [
                    "lowercase",
                    "substring"
                  ]
                }
              },
              "filter": {
                "substring": {
                  "type": "edgeNGram",
                  "min_gram": 1,
                  "max_gram": 255
                }
              }
            }
          }
        }';
        $es = new \app\models\Elasticsearch();

        // Delete the type
        $res = $es->delete($fullIndex, $type);
        $obj = json_decode($res["json"], true);
        if (isset($obj["error"]) && $obj["error"] != false) {
            // If type not already exists we just ignore the error.
            // TODO check if type exist before deleting it.
            /*$response['success'] = false;
            $response['message'] = $obj["error"];
            $response['code'] = $obj["status"];
            return $response;*/
        }

        // Create the index with settings
        $res = $es->createIndex($fullIndex, $settings);
        $obj = json_decode($res["json"], true);
        if (isset($obj["error"]) && $obj["error"] != false) {
            // If index already exists we just ignore it.
            // TODO check if index already exists before creating it.
            /*$response['success'] = false;
            $response['message'] = $obj["error"];
            $response['code'] = $obj["status"];
            return $response;*/
        }

        // Mappings from the table
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
        if ($insert == "t") {
            $sql = "SELECT * FROM {$fullTable}";
            $api = new \app\models\Sql_to_es("4326");
            $api->execQuery("set client_encoding='UTF8'", "PDO");
            $res = $api->sql($sql, $index, $type, $priKey, $db);
            if (!$res["success"]) {
                return $res;
            }
            $res["Indexed"] = true;
        } else {
            $res = array("succes" => true, "indexed" => false, "message" => "Indexing skipped");
        }

        // Create the trigger
        if ($relationType["data"] == "table" || ($installTrigger)) {
            $pl = "CREATE TRIGGER _gc2_notify_transaction_trigger AFTER INSERT OR UPDATE OR DELETE ON {$triggerSchema}.{$triggerTable} FOR EACH ROW EXECUTE PROCEDURE _gc2_notify_transaction('{$priKey}', '{$schema}','{$table}')";
            $result = $model->execQuery($pl, "PG");
            if (!$result) {
                $response['success'] = false;
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
        $fullIndex = $db . "_" . $index;

        $sql = "SELECT * FROM {$fullTable} WHERE \"{$priKey}\"=" . $id;
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

    public function get_delete()
    {
        return $this->delete_delete();
    }

    public function post_bulk()
    {
        return $this->get_bulk();
    }

    public function post_search()
    {
        return $this->get_search();
    }
}