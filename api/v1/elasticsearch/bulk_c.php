<?php
include '../../../conf/main.php';
@include("../../../inc/controller.php");
include("../../../model/sql_to_es.php");
class SqlToEs_c extends Controller
{
    public $user;
    function __construct()
    {
        global $argv;
        global $postgisdb;
        parent::__construct();
        $parts = $this->getUrlParts();
        $_REQUEST['q'] = rawurldecode($_REQUEST['q']);
        $postgisdb = $argv[1];
        $api = new Sql_to_es();
        $api->execQuery("set client_encoding='UTF8'", "PDO");
        $api->sql($argv[5], $argv[2], $argv[3], $argv[4]);
    }
}
new SqlToEs_c();
