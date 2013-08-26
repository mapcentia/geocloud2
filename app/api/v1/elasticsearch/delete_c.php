<?php
namespace app\api\v1\elasticsearch;

class Delete_c extends \app\inc\Controller
{
    function delete_index()
    {
        $delete = \app\inc\Input::getQuery();
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
