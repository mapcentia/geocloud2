<?php

//include("model/sql_to_es.php");
class Search_c extends Controller
{
    public $user;

    function __construct()
    {
        global $postgisdb;
        parent::__construct();
    }
    function search($q, $call_back = false, $call_counter = false)
    {
        $parts = parent::getUrlParts();
        //$cmd = "curl -XGET 'http://localhost:9200/{$parts[6]}/{$parts[7]}/_search?pretty=false&size=10' -d '" . urldecode($q) . "'";

        $ch = curl_init("http://localhost:9200/{$parts[6]}/{$parts[7]}/_search?pretty=false&size=10");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);


        if ($call_counter) {
            //$obj->call_counter = (int)$call_counter;
        }
        $json = ($call_back) ? $call_back . "(" . $buffer . ")" :$buffer;
        return $json;
    }
}

