<?php
namespace app\api\v1\elasticsearch;

class Search_c extends \app\inc\Controller
{
    function get_index()
    {
        $get = \app\inc\Input::getQuery();
        $q = $get['q'];
        $call_back = $get['jsonp_callback'];
        $size = ($get['size']) ? $get['size'] : 10;
        $pretty = (($get['pretty']) || $get['pretty'] == "true") ? $get['pretty'] : "false";

        $parts = \app\inc\Input::getPath();;
        $indices = explode(",", $parts[6]);
        foreach ($indices as $v) {
            $arr[] = $parts[5] . "_" . $v;
        }
        $index = implode(",", $arr);
        $ch = curl_init("http://localhost:9200/{$index}/{$parts[7]}/_search?pretty={$pretty}&size={$size}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $json = ($call_back) ? $call_back . "(" . $buffer . ")" : $buffer;
        return $json;
    }
}
