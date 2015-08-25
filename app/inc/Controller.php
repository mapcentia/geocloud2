<?php
namespace app\inc;

use \app\conf\Connection;

class Controller
{
    public $response;
    
    // Implement OPTIONS method for all action controllers. Used in CORS.
    public function options_index()
    {
		//Pass
    }

    public function auth($key = null, $level = array("all" => true), $neverAllowSubUser = false)
    {
        //die($_SESSION['usergroup']);
        if (($_SESSION['subuser'] == Connection::$param['postgisschema'] || $_SESSION['usergroup'] == Connection::$param['postgisschema']) && $neverAllowSubUser == false) {
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
                //print_r($_SESSION);
                $subuserLevel = $privileges[$_SESSION['usergroup'] ?: $_SESSION['subuser']];
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


