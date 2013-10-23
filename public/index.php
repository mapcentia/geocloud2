<?php
ini_set("display_errors", "On");
error_reporting(3);

use \app\inc\Input;
use \app\inc\Session;
use \app\inc\Route;
use \app\conf\Connection;

include_once("../app/conf/App.php");
new \app\conf\App();

if (Input::getPath()->part(1) == "api") {
    Route::add("api/v1/sql", function () {
        Connection::$param["postgisdb"] = Input::getPath()->part(4);
    });
    Route::add("api/v1/elasticsearch", function () {
        Connection::$param["postgisdb"] = Input::getPath()->part(5);
    });
    Route::add("api/v1/meta", function () {
        Connection::$param["postgisdb"] = Input::getPath()->part(5);
        Connection::$param["postgisschema"] = Input::getPath()->part(6);
    });
    Route::add("api/v1/twitter");
}

if (Input::getPath()->part(1) == "store") {
    Session::start();
    Session::authenticate();
    $_SESSION['postgisschema'] = Input::getPath()->part(3);
    include_once("store.php");
}

if (Input::getPath()->part(1) == "editor") {
    Session::start();
    Session::authenticate();
    include_once("editor.php");
}

if (Input::getPath()->part(1) == "controllers") {

    Session::start();
    Session::authenticate();
    //header('charset=utf-8');
    //header('Content-Type: text/plain; charset=utf-8');

    Connection::$param["postgisdb"] = $_SESSION['screen_name'];
    Connection::$param["postgisschema"] = ($_SESSION['postgisschema']) ? : "public";

    Route::add("controllers/cfgfile");
    Route::add("controllers/classification");
    Route::add("controllers/database");
    Route::add("controllers/layer");
    Route::add("controllers/mapfile");
    Route::add("controllers/setting");
    Route::add("controllers/table");
    Route::add("controllers/tile/");
    Route::add("controllers/tilecache");
    Route::add("controllers/upload/file");
    Route::add("controllers/upload/process");
}

if (Input::getPath()->part(1) == "wms") {
    new \app\controllers\Wms();
}

if (Input::getPath()->part(1) == "wfs") {
    Session::start();
    Connection::$param["postgisdb"] = \app\inc\Input::getPath()->part(2);
    Connection::$param["postgisschema"] = \app\inc\Input::getPath()->part(3);
    include_once("app/wfs/server.php");
}