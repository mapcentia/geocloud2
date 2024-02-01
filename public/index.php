<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2023 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

ini_set("display_errors", "no");
error_reporting(E_ERROR | E_WARNING | E_PARSE);
ob_start("ob_gzhandler");

use app\api\v4\Oauth;
use app\api\v4\Constraint;
use app\api\v4\Geofence;
use app\api\v4\Import;
use app\api\v4\Index;
use app\api\v4\Key;
use app\api\v4\Column;
use app\api\v4\Meta;
use app\api\v4\Schema;
use app\api\v4\Sql;
use app\api\v4\Table;
use app\api\v4\User;
use app\controllers\Wms;
use app\exceptions\GC2Exception;
use app\inc\Input;
use app\inc\Route2;
use app\inc\Session;
use app\inc\Route;
use app\inc\Util;
use app\inc\Response;
use app\inc\Cache;
use app\inc\Jwt;
use app\inc\Redirect;
use app\conf\Connection;
use app\conf\App;
use app\models\Database;

include_once('../app/vendor/autoload.php');
include_once("../app/conf/App.php");
include_once("../app/inc/Globals.php");

new App();

$memoryLimit = App::$param["memoryLimit"] ?? "128M";
ini_set("memory_limit", $memoryLimit);
ini_set("max_execution_time", "30");

// Set session back-end. PHP will use default port if not set explicit
if (!empty(App::$param["sessionHandler"]["type"]) && App::$param["sessionHandler"]["type"] != "files") {
    if (!empty(App::$param['sessionHandler']["host"])) {
        ini_set("session.save_handler", App::$param['sessionHandler']["type"]);
        // If Redis then set the database
        if (App::$param["sessionHandler"]["type"] == "redis") {
            ini_set("session.save_path", "tcp://" . App::$param['sessionHandler']["host"] . "?database=" .
                (!empty(App::$param["sessionHandler"]["db"]) ? App::$param["sessionHandler"]["db"] : "0")
            );
        } else {
            ini_set("session.save_path", App::$param["sessionHandler"]["host"]);
        }
    } else {
        die("Session handler host not set");
    }
}

// Get start time of script
$executionStartTime = microtime(true);

// Reserve some memory in case of the memory limit is reached
$memoryReserve = str_repeat('*', 1024 * 1024);

// Register a shutdown callback if fatal an error occurs
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
        header("HTTP/1.0 $code " . Util::httpCodeText($code));
        echo $response->toJson($body);
    }
    return false;
});

// Setup Cache
try {
    Cache::setInstance();
} catch (Exception $e) {
    throw new Error("Could not init caching system");
}

// Setup host
App::$param['protocol'] = App::$param['protocol'] ?? Util::protocol();
App::$param['host'] = App::$param['host'] ?? App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] != "80" && $_SERVER['SERVER_PORT'] != "443" ? ":" . $_SERVER["SERVER_PORT"] : "");
App::$param['userHostName'] = App::$param['userHostName'] ?? App::$param['host'];

// Write Access-Control-Allow-Origin if origin is white listed
$http_origin = $_SERVER['HTTP_ORIGIN'] ?? null;
if (isset(App::$param["AccessControlAllowOrigin"]) && in_array($http_origin, App::$param["AccessControlAllowOrigin"])) {
    header("Access-Control-Allow-Origin: " . $http_origin);
} elseif (isset(App::$param["AccessControlAllowOrigin"]) && App::$param["AccessControlAllowOrigin"][0] == "*") {
    header("Access-Control-Allow-Origin: *");
}
header("Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept, Session, Cache-Control");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, PUT, POST, DELETE, HEAD, OPTIONS");

// Start routing
try {
    if (Input::getPath()->part(1) == "api") {

        if ($db = Input::getPath()->part(4)) {
            Database::setDb($db); // Default
        }

        //======================
        // V1
        //======================
        Route::add("api/v1/extent");
        Route::add("api/v1/schema");
        Route::add("api/v1/setting");
        Route::add("api/v1/user");
        Route::add("api/v1/legend", function () {
            Session::start();
            Database::setDb(Input::getPath()->part(5));
        });
        Route::add("api/v1/baselayerjs");
        Route::add("api/v1/staticmap");
        Route::add("api/v1/getheader");
        Route::add("api/v1/decodeimg");
        Route::add("api/v1/loriot");
        Route::add("api/v1/session/[action]", function () {
            Session::start();
            Database::setDb("mapcentia");
        });
        Route::add("api/v1/sql", function () {
            die(Response::toJson(
                [
                    "code" => 410,
                    "message" => "v1 SQL API is gone. Use v2 or v3",
                ]
            ));
        });
        Route::add("api/v1/elasticsearch/{action}/{user}/[indices]/[type]", function () {
            $r = func_get_arg(0);
            if ($r["action"] == "river") { // Only start session if no API key is provided
                Session::start();
            }
            Database::setDb($r["user"]);
        });
        Route::add("api/v1/meta/{user}/[query]", function () {
            Session::start();
        });
        Route::add("api/v1/ckan", function () {
            Session::start();
        });

        //======================
        // V2
        //======================
        Route::add("api/v2/sql/{user}/[method]", function () {
            if ((empty(Input::get("key")) || Input::get("key") == "null") && empty(json_decode(Input::getBody())->key)) { // Only start session if no API key is provided
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
        Route::add("api/v2/elasticsearch/{action}/{user}/{schema}/[rel]/[id]", function () {
            if (Route::getParam("action") == "river") {
                Session::start(); // So we can create a session log from the indexing
            }
            Database::setDb(Route::getParam("user"));
        });
        Route::add("api/v2/feature/{user}/{layer}/{srid}/[key]", function () {
            $db = Route::getParam("user");
            $dbSplit = explode("@", $db);
            if (sizeof($dbSplit) == 2) {
                $db = $dbSplit[1];
            }
            Database::setDb($db);
        });
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
        Route::add("api/v2/user/[userId]/[action]", function () {
            Session::start();
        });
        Route::add("api/v2/user/[userId]", function () {
            Session::start();
        });
        Route::add("api/v2/user", function () {
            Session::start();
        });
        Route::add("api/v2/database", function () {
            Session::start();
        });
        Route::add("api/v2/configuration/[userId]/[configurationId]", function () {
            Session::start();
        });

        //======================
        // V3 with OAuth
        //======================
        Route::add("api/v3/oauth", function () {
            Database::setDb("mapcentia");
        });
        Route::add("api/v3/admin/{action}/", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                if (!$jwt["data"]["superUser"]) {
                    echo Response::toJson(Response::SUPER_USER_ONLY);
                    exit();
                }
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });
        Route::add("api/v3/tileseeder/{action}/{uuid}", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                if (!$jwt["data"]["superUser"]) {
                    echo Response::toJson(Response::SUPER_USER_ONLY);
                    exit();
                }
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });
        Route::add("api/v3/tileseeder/", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                if (!$jwt["data"]["superUser"]) {
                    echo Response::toJson(Response::SUPER_USER_ONLY);
                    exit();
                }
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });
        Route::add("api/v3/scheduler/{id}", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                if (!$jwt["data"]["superUser"]) {
                    echo Response::toJson(Response::SUPER_USER_ONLY);
                    exit();
                }
                Database::setDb("gc2scheduler");
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route::add("api/v3/grid", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                if (!$jwt["data"]["superUser"]) {
                    echo Response::toJson(Response::SUPER_USER_ONLY);
                    exit();
                }
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route::add("api/v3/sql", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route::add("api/v3/xmlworkspace/[layer]", function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });
        //==========================
        // V4 with OAuth and Route2
        //==========================
        Route2::add("api/v4/oauth", new Oauth(), function () {
            Database::setDb("mapcentia");
        });

        Route2::add("api/v4/schemas/[schema]", new Schema(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/schemas/{schema}/tables/[table]", new Table(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/schemas/{schema}/tables/{table}/key", new Key(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/schemas/{schema}/tables/{table}/columns/[column]", new Column(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });


        Route2::add("api/v4/schemas/{schema}/tables/{table}/columns/{column}/indices/[index]", new Index(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });


        Route2::add("api/v4/schemas/{schema}/tables/{table}/columns/{column}/constraints/[constraint]", new Constraint(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/users/[id]", new User(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/rules/[rule]", new Geofence(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                if (!$jwt["data"]["superUser"]) {
                    echo Response::toJson(Response::SUPER_USER_ONLY);
                    exit();
                }
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/sql", new Sql(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/meta/[query]", new Meta(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });

        Route2::add("api/v4/import", new Import(), function () {
            $jwt = Jwt::validate();
            if ($jwt["success"]) {
                Database::setDb($jwt["data"]["database"]);
            } else {
                echo Response::toJson($jwt);
                exit();
            }
        });
        Route::miss();

    } elseif (Input::getPath()->part(1) == "admin") {
        Session::start();
        Session::authenticate(App::$param['userHostName'] . "/dashboard/");
        $_SESSION['postgisschema'] = Input::getPath()->part(3) ?: "public";
        include_once("admin.php");
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
        if (!empty(Input::getCookies()["PHPSESSID"])) { // Do not start session if no cookie is set
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
        new Wms();

    } elseif (Input::getPath()->part(1) == "wfs") {
        if (!empty(Input::getCookies()["PHPSESSID"])) {
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
            Redirect::to(App::$param["redirectTo"]);
        } else {
            Redirect::to("/dashboard/");
        }
    } else {
        Route::miss();
    }
} catch (GC2Exception $exception) {
    $response["success"] = false;
    $response["message"] = $exception->getMessage();
    $response["code"] = $exception->getCode();
    $response["errorCode"] = $exception->getErrorCode();
    echo Response::toJson($response);
} catch (PDOException $exception) {
    $response["success"] = false;
    $response["message"] = $exception->getMessage();
    $response["code"] = 400;
    $response["errorCode"] = "SQL_ERROR";
    echo Response::toJson($response);
} catch (Throwable $exception) {
    $response["success"] = false;
    $response["message"] = $exception->getMessage();
    $response["code"] = 500;
    echo Response::toJson($response);
} finally {
    exit();
}
