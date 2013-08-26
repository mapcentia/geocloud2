<?php
namespace app\api\v1\elasticsearch;

class Map_c extends \app\inc\Controller
{

    function put_index($map, $key)
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
}
