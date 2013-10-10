<?php
namespace app\api\v1;

use \app\inc\Input;

//include("../../../models/sql_to_es.php");
class Elasticsearch extends \app\inc\Controller
{
    function get_bulk()
    {
        $get = \app\inc\Input::getQuery();
        if (!$this->authApiKey($parts[5], $get)) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            echo json_encode($response);
            die();
        }
        if (sizeof($parts) < 9 || $parts[8] == "") {
            $response['success'] = false;
            $response['message'] = "The URI must be in this form: /api/v1/elasticsearch/bulk/[user]/[index]/[type]/[id]?q=[SELECT query]";
            echo json_encode($response);
            die();
        }
        $api = new Sql_to_es();
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        echo $api->sql(rawurldecode($_GET['q']), $parts[6], $parts[7], $parts[8], $parts[5]);
    }

    function get_search()
    {
        $get = Input::get();
        $q = $get['q'];
        $call_back = $get['jsonp_callback'];
        $size = ($get['size']) ? $get['size'] : 10;
        $pretty = (($get['pretty']) || $get['pretty'] == "true") ? $get['pretty'] : "false";

        $indices = explode(",", Input::getPath()->part(6));
        foreach ($indices as $v) {
            $arr[] = Input::getPath()->part(5) . "_" . $v;
        }
        $index = implode(",", $arr);
        $ch = curl_init("http://localhost:9200/{$index}/".Input::getPath()->part(7)."/_search?pretty={$pretty}&size={$size}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $json = ($call_back) ? $call_back . "(" . $buffer . ")" : $buffer;
        return $json;
    }
    function put_map($map, $key)
    {
        $put = \app\inc\Input::getQuery();
        $parts = parent::getUrlParts();
        if (!$this->authApiKey($parts[5], $put['key'])) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            echo json_encode($response);
            die();
        }
        $index = $parts[5]."_".$parts[6];
        $ch = curl_init("http://localhost:9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        return $buffer;
    }

    function delete_delete()
    {
        $delete = Input::getQuery();
        $parts = parent::getUrlParts();
        if (!$this->authApiKey($parts[5], $delete['key'])) {
            $response['success'] = false;
            $response['message'] = "Not the right key.";
            echo json_encode($response);
            die();
        }
        $index = $parts[5]."_".$parts[6];
        $ch = curl_init("http://localhost:9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        return $buffer;
    }
}