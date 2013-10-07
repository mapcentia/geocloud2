<?php
use \app\inc\Input;
use \app\conf\Connection;
use \app\conf\App;

include_once("../app/conf/Autoload.php");
new \app\conf\Autoload();
new \app\conf\IncludePath();

$request = Input::getPath();

if ($request->part(1) == "api") {
    $class = "app\\api\\{$request->part(2)}\\{$request->part(3)}\\" . ucfirst($request->part(4)) . "_c";
    $controller = new $class();
    $method = Input::getMethod() . "_index";
    echo $controller->$method();
}
if ($request->part(1) == "store") {
    session_name(App::$param['sessionName']);
    session_set_cookie_params(0, '/', "." . App::$param['domain']);
    session_start();

    $_SESSION['schema'] = $postgisschema = ($request->part(3)) ? $request->part(3) : "public";
    include_once("store.php");
}

if ($request->part(1) == "editor") {
    session_name(App::$param['sessionName']);
    session_set_cookie_params(0, '/', "." . App::$param['domain']);
    session_start();

    $_SESSION['schema'] = $postgisschema = ($request->part(3)) ? $request->part(3) : "public";
    include_once("editor.php");
}

if ($request->part(1) == "controllers") {
    session_name(App::$param['sessionName']);
    session_set_cookie_params(0, '/', "." . App::$param['domain']);
    session_start();
    //header('charset=utf-8');
    //header('Content-Type: text/plain; charset=utf-8');

    Connection::$param["postgisdb"] = $_SESSION['screen_name'];
    Connection::$param["postgisschema"] = $_SESSION['schema'];

    $postgisObject = new \app\inc\Model();

    if ($request->part(2) == "upload") {
        $class = "app\\controllers\\upload\\" . ucfirst($request->part(3));
        if (!$request->part(4))
            $r = "index";
        else
            $r = $request->part(4);
    } else {
        $class = "app\\controllers\\" . ucfirst($request->part(2));
        if (!$request->part(3))
            $r = "index";
        else
            $r = $request->part(3);
    }
    $controller = new $class();
    $method = Input::getMethod() . "_" . $r;
    echo $controller->$method();
}
if ($request->part(1) == "wms") {
    Connection::$param["postgisdb"] = \app\inc\Input::getPath()->part(2);
    Connection::$param["postgisschema"] = \app\inc\Input::getPath()->part(3);

    new \app\controllers\Wms();
}
if ($request->part(1) == "wfs") {
    include_once("app/wfs/server.php");
}