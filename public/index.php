<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

ini_set("display_errors", "off");
ob_start("ob_gzhandler");

use \app\inc\Input;
use \app\inc\Session;
use \app\inc\Route;
use \app\inc\Util;
use \app\inc\Response;
use \app\conf\Connection;
use \app\conf\App;
use \app\models\Database;
use Phpfastcache\CacheManager;
use Phpfastcache\Drivers\Files\Config;

include_once('../app/vendor/autoload.php');

include_once("../app/conf/App.php");

new \app\conf\App();

$memoryLimit = isset(App::$param["memoryLimit"]) ? App::$param["memoryLimit"] : "128M";
ini_set('memory_limit', $memoryLimit);
ini_set('max_execution_time', 30);

//ini_set('session.save_handler', 'redis');
//ini_set('session.save_path', "tcp://172.18.0.4:6379");


// Get start time of script
$executionStartTime = microtime(true);

// Reserve some memory in case of the memory limit is reached
$memoryReserve = str_repeat('*', 1024 * 1024);

// Register a shutdown callback if fatal a error occurs
register_shutdown_function(function () {
    global $memoryReserve;
    global $executionStartTime;
    $memoryReserve = null; // Free memory reserve
    if ((!is_null($err = error_get_last())) && (!in_array($err['type'], [E_NOTICE, E_WARNING]))) {
        $code = "500";
        $response = new Response();
        $body = [
            "message" => $err["message"],
            "file" => $err["file"],
            "line" => $err["line"],
            "code" => $code . " " . Util::httpCodeText($code),
            "execute_time" => microtime(true) - $executionStartTime,
            "memory_peak_usage" => round(memory_get_peak_usage() / 1024) . " KB",
            "success" => false,
        ];
        header("HTTP/1.0 {$code} " . Util::httpCodeText($code));
        echo $response->toJson($body);

    }
    return false;
});

$globalInstanceCache = null;
try {
    $globalInstanceCache = CacheManager::getInstance('Files',
        new Config([
            'securityKey' => "phpfastcache",
            'path' => '/var/www/geocloud2/app/tmp',
            'itemDetailedDate' => true
        ])
    );
} catch (\Exception $exception) {
}

// Setup host
App::$param['protocol'] = isset(App::$param['protocol']) ? App::$param['protocol'] : Util::protocol();
App::$param['host'] = App::$param['host'] ?: App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ":" . $_SERVER['SERVER_PORT'];
App::$param['userHostName'] = App::$param['userHostName'] ?: App::$param['host'];

// Write Access-Control-Allow-Origin if origin is white listed
$http_origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : null;
if (isset(App::$param["AccessControlAllowOrigin"]) && in_array($http_origin, App::$param["AccessControlAllowOrigin"])) {
    header("Access-Control-Allow-Origin: " . $http_origin);
    header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, HEAD");
} elseif (isset(App::$param["AccessControlAllowOrigin"]) && App::$param["AccessControlAllowOrigin"][0] == "*") {
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept");
    header("Access-Control-Allow-Credentials: true");
}

// Start routing
if (Input::getPath()->part(1) == "api") {

    Database::setDb(Input::getPath()->part(4)); // Default

    Route::add("api/v1/sql", function () {
        if (empty(Input::get("key"))) {
               Session::start();
        }        $db = Input::getPath()->part(4);
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $db = $dbSplit[1];
            //$_SESSION['subuser'] = $dbSplit[0];
        }
        Database::setDb($db);
    });

    Route::add("api/v2/sql/{user}/[method]",

        function () {
            if (empty(Input::get("key"))) { // Only start session if no API key is provided
                Session::start();
            }
            $r = func_get_arg(0);
            $db = $r["user"];
            $dbSplit = explode("@", $db);
            if (sizeof($dbSplit) == 2) {
                $db = $dbSplit[1];
            }
            Database::setDb($db);
        });

    Route::add("api/v1/elasticsearch/{action}/{user}/[indices]/[type]",

        function () {
            $r = func_get_arg(0);
            if ($r["action"] == "river") { // Only start session if no API key is provided
                Session::start();
            }
            Database::setDb($r["user"]);
        }

    );

    Route::add("api/v2/elasticsearch/{action}/{user}/{schema}/[rel]/[id]",

        function () {
            if (Route::getParam("action") == "river") {
                Session::start(); // So we can create a session log from the indexing
            }
            Database::setDb(Route::getParam("user"));
        }

    );

    Route::add("api/v2/feature/{user}/{layer}/{srid}/[key]",

        function () {
            $db = Route::getParam("user");
            $dbSplit = explode("@", $db);
            if (sizeof($dbSplit) == 2) {
                $db = $dbSplit[1];
            }
            Database::setDb($db);
        }

    );

    Route::add("api/v2/keyvalue/{user}/[key]", function () {
        $db = Route::getParam("user");
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $db = $dbSplit[1];
        }
        Database::setDb($db);
    });

    Route::add("api/v2/preparedstatement/{user}", function () {
        $db = Route::getParam("user");
        $dbSplit = explode("@", $db);
        if (sizeof($dbSplit) == 2) {
            $db = $dbSplit[1];
        }
        Database::setDb($db);
    });

    Route::add("api/v2/qgis/{action}/{user}", function () {
        Database::setDb(Route::getParam("user"));
    });

    Route::add("api/v2/mapfile/{action}/{user}/{schema}", function () {
        Database::setDb(Route::getParam("user"));
    });

    Route::add("api/v2/mapcachefile/{action}/{user}", function () {
        Database::setDb(Route::getParam("user"));
    });

    Route::add("api/v2/sqlwrapper/{user}", function () {
        Database::setDb(Route::getParam("user"));
    });

    Route::add("api/v2/session", function () {
        Session::start();
        Database::setDb("mapcentia");
    });

    Route::add("api/v1/meta/{user}/[query]", function () {
        Session::start();
    });


    Route::add("api/v1/ckan", function () {
        Session::start();
    });

    // User API
    Route::add("api/v2/user/[userId]/[action]", function () {
        Session::start();
    });
    Route::add("api/v2/user/[userId]", function () {
        Session::start();
    });
    Route::add("api/v2/user", function () {
        Session::start();
    });

    // Database API
    Route::add("api/v2/database", function () {
        Session::start();
    });

    // Configuration API
    Route::add("api/v2/configuration/[userId]/[configurationId]", function () {
        Session::start();
    });

    // Admin API
    Route::add("api/v2/admin/{action}/{user}", function () {
        $db = Route::getParam("user");
        Database::setDb($db);
    });
    //Route::add("api/v2/configuration", function () { Session::start(); });

    Route::add("api/v1/extent");
    Route::add("api/v1/schema");
    Route::add("api/v1/setting");
    Route::add("api/v1/twitter");
    Route::add("api/v1/cartomobile", null, true); // Returns xml
    Route::add("api/v1/user");
    Route::add("api/v1/legend", function () {
        Session::start();
        Database::setDb(Input::getPath()->part(5));
    });
    Route::add("api/v1/baselayerjs");
    Route::add("api/v1/staticmap");
    Route::add("api/v1/getheader");
    Route::add("api/v1/collector");
    Route::add("api/v1/decodeimg");
    Route::add("api/v1/senti");
    Route::add("api/v1/loriot");
    Route::add("api/v1/session/[action]", function () {
        Session::start();
        Database::setDb("mapcentia");
    });
    Route::miss();
} elseif (Input::getPath()->part(1) == "admin") {
    Session::start();
    Session::authenticate(App::$param['userHostName'] . "/dashboard/");
    $_SESSION['postgisschema'] = Input::getPath()->part(3) ?: "public";
    include_once("admin.php");
    if (\app\conf\App::$param['intercom_io']) {
        include_once("../app/conf/intercom.js.inc");
    }
} elseif (Input::getPath()->part(1) == "editor") {
    Session::start();
    Session::authenticate(App::$param['userHostName'] . "/dashboard/");
    include_once("editor.php");
} elseif (Input::getPath()->part(1) == "controllers") {
    Session::start();

    Route::add("controllers/subuser/[user]");

    Session::authenticate(null);

    Database::setDb($_SESSION['parentdb']);
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
    Route::add("controllers/upload/qgis");
    Route::add("controllers/upload/processvector");
    Route::add("controllers/upload/processbitmap");
    Route::add("controllers/upload/processraster");
    Route::add("controllers/upload/processqgis");
    Route::add("controllers/logstash");
    Route::add("controllers/drawing");
    Route::add("controllers/job", function () {
        Database::setDb("gc2scheduler");
    });
    Route::add("controllers/workflow");
    Route::add("controllers/qgis/");

} elseif (Input::getPath()->part(1) == "extensions") {

    foreach (glob(dirname(__FILE__) . "/../app/extensions/**/routes/*.php") as $filename) {
        include_once($filename);
    }
    Route::miss();

} elseif (Input::getPath()->part(1) == "wms" || Input::getPath()->part(1) == "ows") {
    if (!empty(Input::getCookies()["PHPSESSID"])){ // Do not start session if no cookie is set
        Session::start();
    }
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
    new \app\controllers\Wms();

} elseif (Input::getPath()->part(1) == "mapcache") {
// Is not in use. Apache redirects all request to MapCache CGI
//    Session::start();
//    $cache = new \app\controllers\Mapcache();
//    $cache->fetch();

} elseif (Input::getPath()->part(1) == "wfs") {
    if (!empty(Input::getCookies()["PHPSESSID"])){
        Session::start();
    }
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
    if (!empty(App::$param["redirectTo"])) {
        \app\inc\Redirect::to(App::$param["redirectTo"]);
    } else {
        \app\inc\Redirect::to("/dashboard/");
    }
} else {
    Route::miss();
}
