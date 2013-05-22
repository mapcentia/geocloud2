<?php
/**
 *
 */
include '../../conf/main.php';
include 'functions.php';
header('Content-Type: text/plain');
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

class Controller
{
    public $urlParts;

    function __construct()
    {

    }

    public function startSession()
    {
        global $sessionName;
        global $domain;
        session_name($sessionName);
        session_set_cookie_params(0, '/', "." . $domain);
        session_start();
    }
    public function getUrlParts()
    {
        return explode("/", str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']));
    }

    public function auth($user)
    {
        global $userHostName;
        if (!$_SESSION['auth'] || ($_SESSION['screen_name'] != $user)) {
            //$_SESSION['auth'] = null;
            //$_SESSION['screen_name'] = null;
            die("<script>window.location='{$userHostName}/user/login'</script>");
        }
    }

    public function toJSON($response)
    {
        $callback = $_GET['jsonp_callback'];
        if ($callback) {
            return $callback . '(' . json_encode($response) . ');';
        } else {
            return json_encode($response);
        }
    }
}
