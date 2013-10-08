<?php
namespace app\inc;

use \app\conf\App;
use \app\inc\Response;

class Session
{
    static function start()
    {
        session_name(App::$param['sessionName']);
        session_set_cookie_params(0, '/', "." . App::$param['domain']);
        session_start();
    }
    static function authenticate(){
        if ($_SESSION['auth'] == true) {
            return true;
        }
        else {
            die(Response::json(array("success"=>false,"message"=>"Not authenticated")));
        }
    }
    static function isAuth (){
        return $_SESSION['auth'];
    }
}