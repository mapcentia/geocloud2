<?php
namespace app\inc;

use \app\conf\App;

class Session
{
    static function start()
    {
        ini_set("session.cookie_lifetime", "86400");
        ini_set("session.gc_maxlifetime", "86400");
        ini_set("session.gc_probability", 1);
        ini_set("session.gc_divisor", 1);
        if (App::$param['domain']) {
            session_name("PHPSESSID");
            session_set_cookie_params(0, '/', "." . App::$param['domain']);
        }
        session_start();
    }

    static function authenticate($redirect = " / ")
    {
        if ($_SESSION['auth'] == true) {
            return true;
        } elseif ($redirect) {
            \app\inc\Redirect::to($redirect);
            exit();
        } else {
            //\app\inc\Redirect::to("/nosession.php");
            exit();
        }
    }

    static function isAuth()
    {
        return $_SESSION['auth'];
    }

    static function getLog()
    {
        if (!$_SESSION["log"]) {
            $_SESSION["log"] = "<i > Session log started @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
        }
        return $_SESSION["log"];
    }

    static function createLog($lines, $file)
    {
        $num = 15;
        $_SESSION["log"] .= "<br /<br />";
        $_SESSION["log"] .= "<i > Failed upload of {
        $file} @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
        for ($i = 0; $i < sizeof($lines); $i++) {
            $_SESSION["log"] .= htmlentities($lines[$i]) . "</br > ";
            if ($i >= $num) {
                $_SESSION["log"] .= "<i > " . (sizeof($lines) - $num - 1) . " more lines </i ><br />";
                return;
            }
        }
    }

    static function createLogEs($obj)
    {
        $num = 35;
        $_SESSION["log"] .= "<br /<br />";
        $_SESSION["log"] .= "<i > Failed indexing of records @ " . date('l jS \of F Y h:i:s A') . " </i ><br />";
        for ($i = 0; $i < sizeof($obj); $i++) {
            $_SESSION["log"] .= $obj[$i]["id"] . ": " . htmlentities($obj[$i]["error"]) . " </br > ";
            if ($i >= $num) {
                $_SESSION["log"] .= "<i > " . (sizeof($obj) - $num - 1) . " more lines </i ><br />";
                return;
            }
        }
    }

}