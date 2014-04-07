<?php
namespace app\inc;

use \app\conf\App;
use \app\inc\Response;

class Session
{
    static function start()
    {
        if (App::$param['domain']) {
            session_name("PHPSESSID");
            session_set_cookie_params(0, '/', "." . App::$param['domain']);
        }
        session_start();
    }

    static function authenticate($redirect = "/")
    {
        if ($_SESSION['auth'] == true) {
            return true;
        } else {
            Redirect::to($redirect);
        }
    }

    static function isAuth()
    {
        return $_SESSION['auth'];
    }

    static function getLog()
    {
        return $_SESSION["log"];
    }

    static function createLog($lines, $file)
    {
        $num = 15;
        $_SESSION["log"] .= "<br/<br/>";
        $_SESSION["log"] .= "<i>{$file} @ " . date('l jS \of F Y h:i:s A') . "</i><br/>";
        for ($i = 0; $i <= sizeof($lines); $i++) {
            $_SESSION["log"] .= $lines[$i] . "</br>";
            if ($i >= $num) {
                $_SESSION["log"] .= "<i>" . (sizeof($lines) - $num - 1) . " more lines</i><br/>";
                return;
            }
        }
    }
}