<?php
namespace app\inc;

class Controller
{
    public $response;
    public function authApiKey($user, $key){
        global $postgisdb;
        $postgisdb = $user;
        $settings_viewer = new \app\models\Setting();
        $res = $settings_viewer->get();
        $apiKey = $res['data']['api_key'];
        if ($apiKey == $key && $key!=false) {
            return true;
        } else {
            return false;
        }
    }
}
