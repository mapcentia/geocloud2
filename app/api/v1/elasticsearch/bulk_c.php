<?php
//include '../../../conf/main.php';
include("../../../model/sql_to_es.php");
class Bulk_c extends Controller
{
    function __construct()
    {
        parent::__construct();
    }

    function bulk()
    {
        $parts = Controller::getUrlParts();
        if (!$this->authApiKey($parts[5], $_GET['key'])) {
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
        //$postgisdb = $argv[1];
        $api = new Sql_to_es();
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        //$api->sql($argv[5], $argv[2], $argv[3], $argv[4]);
        echo $api->sql(rawurldecode($_GET['q']), $parts[6], $parts[7], $parts[8], $parts[5]);
    }
}