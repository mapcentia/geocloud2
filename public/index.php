<?php
ini_set("display_errors", "Off");
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 0);
error_reporting(3);

use \app\inc\Input;
use \app\inc\Session;
use \app\inc\Route;
use \app\inc\Util;
use \app\conf\Connection;
use \app\conf\App;
use \app\models\Database;

include_once("../app/conf/App.php");
new \app\conf\App();

// Setup host
App::$param['protocol'] = App::$param['protocol'] ?: Util::protocol();
App::$param['host'] = App::$param['host'] ?: App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];
App::$param['userHostName'] = App::$param['userHostName'] ?: App::$param['host'];

// Write Access-Control-Allow-Origin if origin is white listed
$http_origin = $_SERVER['HTTP_ORIGIN'];
if (isset(App::$param["AccessControlAllowOrigin"]) && in_array($http_origin, App::$param["AccessControlAllowOrigin"])) {
    header("Access-Control-Allow-Origin: " . $http_origin);
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    header("Access-Control-Allow-Credentials: true");
}

// Start routing
if (Input::getPath()->part(1) == "api") {
    Database::setDb(Input::getPath()->part(4)); // Default
    Route::add("api/v1/sql", function () {
        Session::start();
        $db = Input::getPath()->part(4);
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $db = $dbSplit[1];
            //$_SESSION['subuser'] = $dbSplit[0];
        }
        Database::setDb($db);
    });
    Route::add("api/v1/elasticsearch", function () {
        if (Input::getPath()->part(4) == "river") {
            Session::start(); // So we can create a session log from the indexing
        }
        Database::setDb(Input::getPath()->part(5));
    });
    Route::add("api/v1/meta", function () {
        Session::start();
    });
    Route::add("api/v1/extent");
    Route::add("api/v1/schema");
    Route::add("api/v1/setting");
    Route::add("api/v1/twitter");
    Route::add("api/v1/cartomobile", null, true); // Returns xml
    Route::add("api/v1/user");
    Route::add("api/v1/legend", function () {
        Database::setDb(Input::getPath()->part(5));
        Connection::$param["postgisschema"] = "trackunit";
    });
    Route::add("api/v1/baselayerjs");
    Route::add("api/v1/staticmap");
    Route::add("api/v1/getheader");
    Route::add("api/v1/collector");
    Route::add("api/v1/session", function () {
        Session::start();
        Database::setDb("mapcentia");
    });
} elseif (Input::getPath()->part(1) == "store") {
    Session::start();
    Session::authenticate(App::$param['userHostName'] . "/user/login/");
    $_SESSION['postgisschema'] = (Input::getPath()->part(3)) ?: "public";
    include_once("store.php");
    if (\app\conf\App::$param['intercom_io']) {
        include_once("../app/conf/intercom.js.inc");
    }
} elseif (Input::getPath()->part(1) == "editor") {
    Session::start();
    Session::authenticate(App::$param['userHostName'] . "/user/login/");
    include_once("editor.php");
} elseif (Input::getPath()->part(1) == "controllers") {
    Session::start();
    Session::authenticate(null);

    Database::setDb($_SESSION['screen_name']);
    Connection::$param["postgisschema"] = $_SESSION['postgisschema'];

    Route::add("controllers/cfgfile");
    Route::add("controllers/classification/");
    Route::add("controllers/database/");
    Route::add("controllers/layer/");
    Route::add("controllers/mapfile");
    Route::add("controllers/tinyowsfile");
    Route::add("controllers/mapcachefile");
    Route::add("controllers/setting");
    Route::add("controllers/table/");
    Route::add("controllers/tile/");
    Route::add("controllers/tilecache/");
    Route::add("controllers/mapcache/");
    Route::add("controllers/session/");
    Route::add("controllers/osm/");
    Route::add("controllers/upload/vector");
    Route::add("controllers/upload/bitmap");
    Route::add("controllers/upload/raster");
    Route::add("controllers/upload/processvector");
    Route::add("controllers/upload/processbitmap");
    Route::add("controllers/upload/processraster");
    Route::add("controllers/logstash");
    Route::add("controllers/drawing");
    Route::add("controllers/job", function () {
        Database::setDb("gc2scheduler");
    });
    Route::add("controllers/workflow");
} elseif (Input::getPath()->part(1) == "wms" || Input::getPath()->part(1) == "ows") {
    Session::start();
    new \app\controllers\Wms();

} elseif (Input::getPath()->part(1) == "tilecache") {
    Session::start();
    $tileCache = new \app\controllers\Tilecache();
    $tileCache->fetch();

} elseif (Input::getPath()->part(1) == "mapcache") {
    // Use TileCache instead if there is no MapCache settings
    Session::start();
    if (isset(App::$param["mapCache"])) {
        $cache = new \app\controllers\Mapcache();
    } else {
        $cache = new \app\controllers\Tilecache();
    }
    $cache->fetch();

} elseif (Input::getPath()->part(1) == "wfs") {
    Session::start();
    $db = Input::getPath()->part(2);
    $dbSplit = explode("@", $db);
    if (sizeof($dbSplit) == 2) {
        $db = $dbSplit[1];
        $user = $dbSplit[0];
        $parentUser = false;
    } else {
        $user = $db;
        $parentUser = true;
    }
    Database::setDb($db);
    Connection::$param["postgisschema"] = Input::getPath()->part(3);
    include_once("app/wfs/server.php");

} elseif (!Input::getPath()->part(1)) {
    if (App::$param["redirectTo"]) {
        \app\inc\Redirect::to(App::$param["redirectTo"]);
    } else {
        \app\inc\Redirect::to("/user/login");
    }
} else {
    header('HTTP/1.0 404 Not Found');
    echo "<h1>404 Not Found</h1>";
    echo "The page that you have requested could not be found.";
    exit();
}