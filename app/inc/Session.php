<?php
namespace app\inc;

use \app\conf\App;
use \app\inc\Response;

class Session
{
    static function start()
    {
        session_name(App::$param['sessionName']);
        session_set_cookie_params(9999999999, '/', "." . App::$param['domain']);
        session_start();
    }
    static function authenticate($redirect="/"){
        if ($_SESSION['auth'] == true) {
            return true;
        }
        else {
            Redirect::to($redirect);
        }
    }
    static function isAuth (){
        return $_SESSION['auth'];
    }
}