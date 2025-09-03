<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

ini_set("display_errors", "no");
//ini_set("display_errors", "yes");
error_reporting(E_ERROR | E_WARNING | E_PARSE | E_DEPRECATED | E_USER_DEPRECATED);
//error_reporting(E_ALL);
ob_start("ob_gzhandler");

use app\api\v3\Meta;
use app\api\v4\Call;
use app\api\v4\Client;
use app\api\v4\Column;
use app\api\v4\Constraint;
use app\api\v4\Commit;
use app\api\v4\Geofence;
use app\api\v4\Import;
use app\api\v4\Index;
use app\api\v4\Method;
use app\api\v4\Oauth;
use app\api\v4\Privilege;
use app\api\v4\Schema;
use app\api\v4\Sql;
use app\api\v4\Stat;
use app\api\v4\Table;
use app\api\v4\User;
use app\conf\App;
use app\conf\Connection;
use app\controllers\Wms;
use app\exceptions\GC2Exception;
use app\exceptions\OwsException;
use app\exceptions\RPCException;
use app\exceptions\ServiceException;
use app\inc\Cache;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\RateLimiter;
use app\inc\Redirect;
use app\inc\Response;
use app\inc\Route;
use app\inc\Route2;
use app\inc\Session;
use app\inc\Util;
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
    if (App::$param["sessionHandler"]["type"] == "redis") {
        if (!empty(App::$param['sessionHandler']["host"])) {
            $u = parse_url(App::$param['sessionHandler']["host"]);
        } else {
            throw new GC2Exception("Session handler host not set", 500, null, "SESSION_HANDLER_ERROR");
        }
        $scheme = $u['scheme'] ?? 'tcp';
        $host = $u['host'] ?? $u['path'] ?? 'redis';
        $port = $u['port'] ?? 6379;
        $fullUrl = $scheme . '://' . $host . ':' . $port;
        $db = empty(App::$param["sessionHandler"]["db"]) ? "" : "?database=" . App::$param["sessionHandler"]["db"];
        $fullUrl .= $db;
        ini_set("session.save_handler", 'redis');
        ini_set("session.save_path", $fullUrl);
    } elseif (App::$param["sessionHandler"]["type"] == "redisCluster") {
        if (!empty(App::$param['sessionHandler']["seeds"])) {
            $seeds = App::$param['sessionHandler']["seeds"];
            $stream = !empty(App::$param['sessionHandler']["tls"]) ? "stream[verify_peer]=0" : "";
        } else {
            throw new GC2Exception("Session handler seeds not set", 500, null, "SESSION_HANDLER_ERROR");
        }
        $seedsStr = implode("&seed[]=", $seeds);
        $path = "seed[]=" . $seedsStr . "&timeout=2&read_timeout=2&failover=error&persistent=0&" . $stream;
        ini_set("session.save_handler", 'rediscluster');
        ini_set("session.save_path", $path);
    } else {
        throw new GC2Exception('Session type must be either file, redis or redisCluster', 500, null, 'CACHE_ERROR');
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
    if ((!is_null($err = error_get_last())) && (!in_array($err['type'], [E_NOTICE, E_WARNING, E_DEPRECATED, E_USER_DEPRECATED]))) {
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
    } elseif (!empty($err["message"])) {
        error_log($err["message"]);
    }
    return false;
});

// Setup Cache
Cache::setInstance();

// Setup host
App::$param['protocol'] = App::$param['protocol'] ?? Util::protocol();
App::$param['host'] = App::$param['host'] ?? App::$param['protocol'] . "://" . $_SERVER['SERVER_NAME'] . ($_SERVER['SERVER_PORT'] != "80" && $_SERVER['SERVER_PORT'] != "443" ? ":" . $_SERVER["SERVER_PORT"] : "");
App::$param['userHostName'] = App::$param['userHostName'] ?? App::$param['host'];

// Handle OWS outside handler handler function
try {
    if (Input::getPath()->part(1) == "wfs") {
        if (!empty(Input::getCookies()["PHPSESSID"])) {
            Session::start();
        }
        // Support of legacy user@database notation in URI. The user part (before @) will be completely ignored
        $dbSplit = explode("@", Input::getPath()->part(2));
        // User is either from basic auth, session or URI. The latter is same as database
        $user = Input::getAuthUser() ?? Session::getUser() ?? $dbSplit[1] ?? $dbSplit[0];
        $db = $dbSplit[1] ?? $dbSplit[0];
        // parentUser is superuser
        $parentUser = $user == $db;
        Database::setDb($db);
        Connection::$param["postgisschema"] = Input::getPath()->part(3);
        include_once("app/wfs/server.php");
    } elseif (Input::getPath()->part(1) == "wms" || Input::getPath()->part(1) == "ows") {
        if (!empty(Input::getCookies()["PHPSESSID"])) { // Do not start session if no cookie is set
            Session::start();
        }
        $dbSplit = explode("@", Input::getPath()->part(2));
        Database::setDb($dbSplit[1] ?? $dbSplit[0]);
        new Wms();
    }
} catch (OwsException|ServiceException $exception) {
    ob_clean();
    header('Content-Type:text/xml; charset=UTF-8', TRUE);
    echo $exception->getReport();
    flush();
}

// Start routing
$handler = static function () {
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
                if (empty(Input::get("key"))) {
                    Session::start();
                }
                $db = Input::getPath()->part(4);
                $dbSplit = explode("@", $db);
                if (sizeof($dbSplit) == 2) {
                    $db = $dbSplit[1];
                }
                Database::setDb($db);
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
                if ((empty(Input::get("key")) || Input::get("key") == "null") && empty(json_decode(Input::getBody() ?? '{}')->key)) { // Only start session if no API key is provided
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
            Route::add("api/v2/stat", function () {
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
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });
            Route::add("api/v3/tileseeder/{action}/{uuid}", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    if (!$jwt["data"]["superUser"]) {
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });
            Route::add("api/v3/tileseeder/", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    if (!$jwt["data"]["superUser"]) {
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });
            Route::add("api/v3/scheduler", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    if (!$jwt["data"]["superUser"]) {
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb("gc2scheduler");
                } else {
                    echo Response::toJson($jwt);
                }
            });

            Route::add("api/v3/grid", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    if (!$jwt["data"]["superUser"]) {
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });

            Route::add("api/v3/sql", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });

            Route::add("api/v3/xmlworkspace/[layer]", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });

            Route::add("api/v3/view/[schema]", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    if (!$jwt["data"]["superUser"]) {
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });
            Route::add("api/v3/foreign", function () {
                $jwt = Jwt::validate();
                if ($jwt["success"]) {
                    if (!$jwt["data"]["superUser"]) {
                        throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                    }
                    Database::setDb($jwt["data"]["database"]);
                } else {
                    echo Response::toJson($jwt);
                }
            });

            //==========================
            // V4 with OAuth and Route2
            //==========================
            $Route2 = new Route2();
            $Route2->add("api/v4/oauth", new Oauth($Route2, new \app\inc\Connection()));
            $Route2->add("api/v4/oauth/(action)", new Oauth($Route2, new \app\inc\Connection()));
            if (headers_sent()) {
                return;
            }
            $jwt = Jwt::validate();
            // Rate limit per JWT token for all API v4 routes
            RateLimiter::consumeForJwt(Input::getJwtToken(), App::$param['apiV4']['rateLimitPerMinute'] ?? 120);
            $conn = new \app\inc\Connection(database: $jwt["data"]["database"]);
            $Route2->add("api/v4/schemas/[schema]", new Schema($Route2, $conn), function () use ($jwt) {
                if (!$jwt["data"]["superUser"]) {
                    throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                }
            });
            $Route2->add("api/v4/schemas/{schema}/tables/[table]", new Table($Route2, $conn));
            $Route2->add("api/v4/schemas/{schema}/tables/{table}/columns/[column]", new Column($Route2, $conn));
            $Route2->add("api/v4/schemas/{schema}/tables/{table}/indices/[index]", new Index($Route2, $conn));
            $Route2->add("api/v4/schemas/{schema}/tables/{table}/constraints/[constraint]", new Constraint($Route2, $conn));
            $Route2->add("api/v4/users/[user]", new User($Route2, $conn));
            $Route2->add("api/v4/schemas/{schema}/tables/{table}/privileges", new Privilege($Route2, $conn));
            $Route2->add("api/v4/rules/[id]", new Geofence($Route2, $conn), function () use ($jwt) {
                if (!$jwt["data"]["superUser"] && !Input::getMethod() == 'options') {
                    throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                }
            });
            $Route2->add("api/v4/sql", new Sql($Route2, $conn));
            $Route2->add("api/v4/sql/(database)/{database}", new Sql($Route2, $conn));
            $Route2->add("api/v4/methods/[id]", new Method($Route2, $conn));
            $Route2->add("api/v4/call", new Call($Route2, $conn));
            $Route2->add("api/v3/meta/[query]", new Meta($Route2, $conn));
            $Route2->add("api/v4/import/{schema}/[file]", new Import($Route2, $conn));
            $Route2->add("api/v4/clients/[id]", new Client($Route2, $conn));
            $Route2->add("api/v4/stats", new Stat($Route2, $conn), function () use ($jwt) {
                if (!$jwt["data"]["superUser"] && !Input::getMethod() == 'options') {
                    throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
                }
            });
            $Route2->add("api/v4/commit", new Commit($Route2, $conn), function () use ($jwt) {
                if (!$jwt["data"]["superUser"] && !Input::getMethod() == 'options') {
                    throw new GC2Exception(Response::SUPER_USER_ONLY['message'], 400);
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

        } elseif (!Input::getPath()->part(1)) {
            if (!empty(App::$param["redirectTo"])) {
                Redirect::to(App::$param["redirectTo"]);
            } else {
                Redirect::to("/dashboard/");
            }
        } else {
            foreach (glob(dirname(__FILE__) . "/../app/extensions/**/routes/*.php") as $filename) {
                include($filename);
            }
            foreach (glob(dirname(__FILE__) . "/../app/auth/routes/*.php") as $filename) {
                include($filename);
            }
            Route::miss();
        }
    } catch (GC2Exception $exception) {
        $response["success"] = false;
        $response["message"] = $exception->getMessage();
        $response["code"] = $exception->getCode();
        $response["errorCode"] = $exception->getErrorCode();
        echo Response::toJson($response);
    } catch (RPCException $exception) {
        echo Response::toJson($exception->getResponse());
    } catch (PDOException $exception) {
        $response["success"] = false;
        $response["message"] = $exception->errorInfo[2];
        $response["code"] = 500;
        $response["errorCode"] = $exception->getCode();
        $response["file"] = $exception->getFile();
        $response["line"] = $exception->getLine();
        $response["trace"] = $exception->getTraceAsString();

        echo Response::toJson($response);
    } catch (Throwable $exception) {
        $response["success"] = false;
        $response["message"] = $exception->getMessage();
        $response["file"] = $exception->getFile();
        $response["line"] = $exception->getLine();
        $response["trace"] = $exception->getTraceAsString();
        $response["code"] = 500;
        echo Response::toJson($response);
    } finally {
        return;
    }
};

if (function_exists('frankenphp_handle_request')) {
    ignore_user_abort(true);
    $maxRequests = (int)($_SERVER['MAX_REQUESTS'] ?? 500);
    error_log("Starting worker");
    for ($nbRequests = 0; !$maxRequests || $nbRequests < $maxRequests; ++$nbRequests) {
        $keepRunning = frankenphp_handle_request($handler);
        error_log($keepRunning);
        // Call the garbage collector to reduce the chances of it being triggered in the middle of a page generation
        gc_collect_cycles();
        if (!$keepRunning) break;
    }
} else {
    $handler();
}