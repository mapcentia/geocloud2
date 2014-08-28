<?php
namespace app\inc;

class Controller
{
    public $response;

    public function auth($key = null, $level = array("all" => true))
    {
        if ($_SESSION['subuser'] == \app\conf\Connection::$param['postgisschema'] && $neverAllowSubUser == false) {
            $response['success'] = true;
        } elseif ($_SESSION['subuser']) {
            $text = "You don't have privileges to do this. Please contact the database owner, who can grant you privileges.";
            if (sizeof($level) == 0) {
                $response['success'] = false;
                $response['message'] = $text;
                $response['code'] = 403;
            } else {
                $layer = new \app\models\Layer();
                $privileges = (array)json_decode($layer->getValueFromKey($key, "privileges"));
                $subuserLevel = $privileges[$_SESSION['subuser']];
                if (!isset($level[$subuserLevel])) {
                    $response['success'] = false;
                    $response['message'] = $text;
                    $response['code'] = 403;
                } else {
                    $response['success'] = true;
                }
            }
        } else {
            $response['success'] = true;
        }
        return $response;
    }

    public function authApiKey($user, $key)
    {
        global $postgisdb;
        $postgisdb = $user;
        $settings_viewer = new \app\models\Setting();
        $res = $settings_viewer->get();
        $apiKey = $res['data']['api_key'];
        if ($apiKey == $key && $key != false) {
            return true;
        } else {
            return false;
        }
    }
}


