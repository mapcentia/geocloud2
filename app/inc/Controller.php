<?php
namespace app\inc;

class Controller
{
    public $response;
    public function authApiKey($user, $key){
        global $postgisdb;
        $postgisdb = $user;
        $settings_viewer = new Settings_viewer();
        $res = $settings_viewer->get();
        $apiKey = $res['data']['api_key'];
        if ($apiKey == $key && $key!=false) {
            return true;
        } else {
            return false;
        }
    }
}
