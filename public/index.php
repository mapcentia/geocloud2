<?php
ini_set("display_errors", "Off");
ini_set('memory_limit', '256M');
error_reporting(3);

use \app\inc\Input;
use \app\inc\Session;
use \app\inc\Route;
use \app\conf\Connection;
use \app\models\Database;

include_once("../app/conf/App.php");
new \app\conf\App();
// Set the host names if they are not set in App.php
if (!\app\conf\App::$param['host']) {
    include_once("../app/conf/hosts.php");
}

if (Input::getPath()->part(1) == "api") {
    Database::setDb(Input::getPath()->part(4)); // Default
    Route::add("api/v1/sql");
    Route::add("api/v1/elasticsearch", function () {
        Database::setDb(Input::getPath()->part(5));
    });
    Route::add("api/v1/meta", function(){
        Session::start();
    });
    Route::add("api/v1/schema");
    Route::add("api/v1/twitter");
    Route::add("api/v1/cartomobile");
    Route::add("api/v1/user");
    Route::add("api/v1/legend", function () {
        Database::setDb(Input::getPath()->part(5));
        Connection::$param["postgisschema"] = "trackunit";
    });
} elseif (Input::getPath()->part(1) == "store") {
    Session::start();
    Session::authenticate(\app\conf\App::$param['userHostName'] . "/user/login/");
    $_SESSION['postgisschema'] = (Input::getPath()->part(3)) ? : "public";
    include_once("store.php");
    include_once("../app/conf/intercom.js.inc");
} elseif (Input::getPath()->part(1) == "editor") {
    Session::start();
    Session::authenticate(\app\conf\App::$param['userHostName'] . "/user/login/");
    include_once("editor.php");
} elseif (Input::getPath()->part(1) == "controllers") {

    Session::start();
    Session::authenticate("/user/login/");

    Database::setDb($_SESSION['screen_name']);
    Connection::$param["postgisschema"] = $_SESSION['postgisschema'];

    Route::add("controllers/cfgfile");
    Route::add("controllers/classification/");
    Route::add("controllers/database/");
    Route::add("controllers/layer/");
    Route::add("controllers/mapfile");
    Route::add("controllers/setting");
    Route::add("controllers/table/");
    Route::add("controllers/tile/");
    Route::add("controllers/tilecache/");
    Route::add("controllers/upload/file");
    Route::add("controllers/upload/process");
} elseif (Input::getPath()->part(1) == "wms") {
    Session::start();
    Database::setDb(Input::getPath()->part(2));
    new \app\controllers\Wms();
} elseif (Input::getPath()->part(1) == "wmsc") {
    Session::start();
    Database::setDb(Input::getPath()->part(2));
    new \app\controllers\Wmsc();
} elseif (Input::getPath()->part(1) == "wfs") {
    Session::start();
    Database::setDb(Input::getPath()->part(2));
    Connection::$param["postgisschema"] = \app\inc\Input::getPath()->part(3);
    include_once("app/wfs/server.php");
} elseif (!Input::getPath()->part(1)) {
    \app\inc\Redirect::to("/user/login");
} else {
    header('HTTP/1.0 404 Not Found');
    echo "<h1>404 Not Found</h1>";
    echo "The page that you have requested could not be found.";
    exit();
}