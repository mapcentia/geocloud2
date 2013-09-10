<?php
include "../app/conf/main.php";

use \app\inc\Input;

$request = Input::getPath();

if ($request->part(1) == "api") {
    $class = "app\\api\\{$request->part(2)}\\{$request->part(3)}\\" . ucfirst($request->part(4)) . "_c";
    $controller = new $class();
    $method = Input::getMethod() . "_index";
    echo $controller->$method();
}
if ($request->part(1) == "store") {
    session_name($sessionName);
    session_set_cookie_params(0, '/',".".$domain);
    session_start();

    $_SESSION['schema'] = $postgisschema =($request->part(3)) ? $request->part(3) : "public";
    include_once("store.php");
}

if ($request->part(1) == "controllers") {
    session_name($sessionName);
    session_set_cookie_params(0, '/',".".$domain);
    session_start();
    header('Content-Type: text/plain');

    \Connection::$param["postgisdb"] = $_SESSION['screen_name'];
    \Connection::$param["postgisschema"] = $_SESSION['schema'];

    $postgisObject = new \app\inc\postgis();

    $class = "app\\controllers\\". ucfirst($request->part(2));
    $controller = new $class();

    if (!$request->part(3))
        $r = "index";
    else
        $r = $request->part(3);
    $method = Input::getMethod() . "_". $r;

    echo $controller->$method();
}