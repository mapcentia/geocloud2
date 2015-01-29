<?php
namespace app\api\v1;

use \app\inc\Input;

class Elasticsearch extends \app\inc\Controller
{
    protected $host;

    function __construct()
    {
        $this->host = \app\conf\App::$param['esHost'] ?: "http://127.0.0.1";
    }

    function get_bulk()
    {
        ini_set('max_execution_time', 300);
        if (!$this->authApiKey(Input::getPath()->part(5), Input::get('key'))) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            $response['code'] = 403;
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

    function post_bulk()
    {
        return $this->get_bulk();
    }

    function get_search()
    {
        $get = Input::get();
        if (!$this->authApiKey(Input::getPath()->part(5), $get['key']) && \app\conf\App::$param["useKeyForSearch"] == true) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            $response['code'] = 403;
            return $response;
        }
        $q = urldecode($get['q']);
        $size = ($get['size']) ?: 10;
        $pretty = (($get['pretty']) || $get['pretty'] == "true") ? $get['pretty'] : "false";
        $arr = array();

        $indices = explode(",", Input::getPath()->part(6));
        foreach ($indices as $v) {
            $arr[] = Input::getPath()->part(5) . "_" . $v;
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
        error_log($searchUrl);
        return $response;
    }

    function post_search()
    {
        return $this->get_search();
    }

    function put_map()
    {
        $put = Input::get();
        if (!$this->authApiKey(Input::getPath()->part(5), $put['key'])) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            $response['code'] = 403;
            return $response;
        }
        $index = Input::getPath()->part(5) . "_" . Input::getPath()->part(6);
        $es = new \app\models\Elasticsearch();
        return $es->map($index, $put["map"]);
    }

    function delete_delete()
    {
        $delete = Input::get();
        if (!$this->authApiKey(Input::getPath()->part(5), $delete['key'])) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            $response['code'] = 403;
            return $response;
        }
        $index = Input::getPath()->part(5) . "_" . Input::getPath()->part(6);
        $ch = curl_init($this->host . ":9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    public function get_map()
    {
        $table = Input::getPath()->part(6);
        $es = new \app\models\Elasticsearch();
        return $es->createMapFromTable($table);
    }
    public function get_river(){
        $table = Input::getPath()->part(6);
        $split = explode(".",$table);
        $schema = $split[0];
        $index = Input::getPath()->part(5) . "_" . $schema;
        $es = new \app\models\Elasticsearch();
        $map = $es->createMapFromTable($table);
        $res = $es->map($index, json_encode($map));
        $obj = json_decode($res["json"], true);
        if (isset($obj["error"]) && $obj["error"] != false) {
            $response['success'] = false;
            $response['message'] = $obj["error"];
            $response['code'] = $obj["status"];
            return $response;
        }

        return $res;
    }
}