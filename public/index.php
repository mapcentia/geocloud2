<?php
include "../app/conf/main.php";

use \app\inc\Input;

$request = Input::getPath();

if ($request[1] == "api") {
    $class = "app\\api\\{$request[2]}\\{$request[3]}\\" . ucfirst($request[4]) . "_c";
    $controller = new $class();
    $method = Input::getMethod() . "_index";
    echo $controller->$method();
}
if ($request[1] == "store") {
    session_name($sessionName);
    session_set_cookie_params(0, '/',".".$domain);
    session_start();

    $_SESSION['schema'] = $postgisschema =($request[4]) ? $request[4] : "public";
    include_once("store.php");
}

if ($request[1] == "controller") {

    session_name($sessionName);
    session_set_cookie_params(0, '/',".".$domain);
    session_start();

    \Connection::$param["postgisdb"] = $request[3];
    \Connection::$param["postgisschema"] = $_SESSION['schema'];

    $postgisObject = new \app\inc\postgis();

    header('Content-Type: text/plain');

    if ($request[2] == "tables")
        include_once("app/controllers/Table.php");
    if ($request[2] == "settings_viewer")
        include_once("app/controllers/settings_viewer_c.php");
    if ($request[2] == "databases")
        include_once("app/controllers/databases_c.php");

    $callback = $_GET['jsonp_callback'];

    if ($callback) {
        echo $callback.'('.json_encode($response).');';
    }
    else {
        echo json_encode($response);
    }
}