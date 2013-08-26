<?php
namespace app\api\v1\elasticsearch;
include("../../../model/sql_to_es.php");
class Bulk_c extends \app\inc\Controller
{
    function get_index()
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
}