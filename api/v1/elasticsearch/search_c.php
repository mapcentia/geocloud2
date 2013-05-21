<?php
//include("model/sql_to_es.php");
class Search_c extends Controller
{
    public $user;

    function __construct()
    {
        global $postgisdb;
        parent::__construct();
        /*
        $parts = $this->getUrlParts();
        $ch = curl_init("http://localhost:9200/{$parts[6]}/{$parts[7]}/_search?q=".urldecode($_GET['q'])."&pretty=true&fields=properties&size=1&analyze_wildcard=true");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        echo curl_exec($ch);
        curl_close($ch);*/

    }

    function search($q, $call_back = false)
    {
        $cmd = "curl -XGET 'http://localhost:9200/matrikel/jordstykke/_search?pretty=false&size=10' -d '" . urldecode($q) . "'";

        return ($call_back) ? $call_back . "(" . exec($cmd) . ")" : exec($cmd);
    }
}
