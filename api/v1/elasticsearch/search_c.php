<?php
class Search_c extends Controller
{
    function __construct()
    {
        parent::__construct();
    }

    function search($q, $call_back, $size, $pretty, $call_counter = false)
    {
        $parts = parent::getUrlParts();
        $index = $parts[5]."_".$parts[6];
        $ch = curl_init("http://localhost:9200/{$index}/{$parts[7]}/_search?pretty={$pretty}&size={$size}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        if ($call_counter) {
            //$obj->call_counter = (int)$call_counter;
        }
        $json = ($call_back) ? $call_back . "(" . $buffer . ")" : $buffer;
        return $json;
    }
}
