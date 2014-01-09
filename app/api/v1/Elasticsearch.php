<?php
namespace app\api\v1;

use \app\inc\Input;

//include("../../../models/sql_to_es.php");
class Elasticsearch extends \app\inc\Controller
{
    protected $host;
    function __construct(){
       $this->host = \app\conf\App::$param['esHost'];
    }
    function get_bulk()
    {
        if (!$this->authApiKey(Input::getPath()->part(5), Input::get('key'))) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            echo json_encode($response);
            die();
        }
        /*if (sizeof($parts) < 9 || $parts[8] == "") {
            $response['success'] = false;
            $response['message'] = "The URI must be in this form: /api/v1/elasticsearch/bulk/[user]/[index]/[type]/[id]?q=[SELECT query]";
            echo json_encode($response);
            die();
        }*/
        $api = new \app\models\Sql_to_es();
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        $api->sql(rawurldecode($_GET['q']), Input::getPath()->part(6), Input::getPath()->part(7), Input::getPath()->part(8), Input::getPath()->part(5));
    }

    function get_search()
    {
        $get = Input::get();
        $q = $get['q'];
        $call_back = $get['jsonp_callback'];
        $size = ($get['size']) ? $get['size'] : 10;
        $pretty = (($get['pretty']) || $get['pretty'] == "true") ? $get['pretty'] : "false";
        $arr = array();

        $indices = explode(",", Input::getPath()->part(6));
        foreach ($indices as $v) {
            $arr[] = Input::getPath()->part(5) . "_" . $v;
        }
        $index = implode(",", $arr);
        $ch = curl_init($this->host.":9200/{$index}/".Input::getPath()->part(7)."/_search?pretty={$pretty}&size={$size}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $json = ($call_back) ? $call_back . "(" . $buffer . ")" : $buffer;
        return $json;
    }
    function put_map()
    {
        $put = \app\inc\Input::get();
        if (!$this->authApiKey(Input::getPath()->part(5), $put['key'])) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            echo json_encode($response);
            die();
        }
        $index = Input::getPath()->part(5)."_".Input::getPath()->part(6);
        $ch = curl_init($this->host.":9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $put['map']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        return $buffer;
    }

    function delete_delete()
    {
        $delete = Input::get();
        if (!$this->authApiKey(Input::getPath()->part(5), $delete['key'])) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            echo json_encode($response);
            die();
        }
        $index = Input::getPath()->part(5)."_".Input::getPath()->part(6);
        $ch = curl_init($this->host.":9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        return $buffer;
    }
}