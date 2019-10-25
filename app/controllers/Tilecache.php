<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use \app\inc\Response;
use \app\inc\Input;
use \app\conf\Connection;
use \app\conf\App;
use \app\models\Database;

class Tilecache extends \app\inc\Controller
{
    private $db;
    private $host;
    private $subUser;
    private $type;

    function __construct()
    {
        parent::__construct();

        $this->db = \app\inc\Input::getPath()->part(2);
        $this->host = "http://127.0.0.1";
        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->subUser = $dbSplit[0];
            $this->db = $dbSplit[1];
        } else {
            $this->subUser = null;
        }
    }

    public function fetch()
    {
        $uriParts = array();
        $parts = explode("/", $_SERVER['REQUEST_URI']);
        for ($i = 0; $i < sizeof($parts); $i++) {
            if ($i == 2) {
                $b = explode("@", $parts[$i]);
                if (sizeof($b) > 1) {
                    $parts[$i] = $b[1];
                }
            }
            $uriParts[] = $parts[$i];
        }
        $uri = implode("/", $uriParts);
        $layer = null;
        $url = null;
        switch (explode("?", $parts[3])[0]) {
            case "tms";
                $layer = explode("@", $parts[5])[0];
                $url = $this->host . "/cgi/tilecache.py" . "/" . $uriParts[4] . "/" . $uriParts[5] . "/" . $uriParts[6] . "/" . $uriParts[7] . "/" . $uriParts[8] . "?cfg=" . $this->db;
                break;
            case "wms";
                $get = array_change_key_case($_GET, CASE_UPPER);
                if (strtolower($get["REQUEST"]) == "getcapabilities" ||
                    strtolower($get["REQUEST"]) == "getlegendgraphic" ||
                    strtolower($get["REQUEST"]) == "getfeatureinfo" ||
                    strtolower($get["REQUEST"]) == "describefeaturetype" ||
                    isset($get["FORMAT_OPTIONS"]) == true
                ) {
                    $url = $this->host . "/ows/" . $this->db . "/" . $parts[4] . "?" . explode("?", $uri)[1];
                    $url = rtrim($url, '?');
                } else {
                    $layer = $get["LAYERS"];
                    $url = $this->host . "/cgi/tilecache.py" . "?" . explode("?", $uri)[1] . "&cfg=" . $this->db;
                }
                break;
        }
        //die(print_r($layer, true));
        $url = $url ?: $this->host . $uri;

        header("X-Powered-By: GC2 TileCache");

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($curl, $header_line) {
            $bits = explode(":", $header_line);
            if ($bits[0] == "Content-Type") {
                $this->type = trim($bits[1]);
            }
            // Send text/xml instead of application/vnd.ogc.se_xml
            if ($bits[0] == "Content-Type" && trim($bits[1]) == "application/vnd.ogc.se_xml") {
                header("Content-Type: text/xml");
            } elseif ($bits[0] != "Content-Encoding" && trim($bits[1]) != "chunked") {
                header($header_line);
            }
            return strlen($header_line);
        });
        $content = curl_exec($ch);
        curl_close($ch);

        // Check authentication level if image
        if (explode("/", $this->type)[0] == "image") {
            $this->basicHttpAuthLayer($layer, $this->db, $this->subUser);
        }

        // Return content
        echo $content;
        exit();
    }

    public function delete_index()
    {
        $layer = new \app\models\Layer();
        $cache = $layer->getAll(Input::getPath()->part(4), true, false, true, false, Database::getDb())["data"][0]["def"]->cache;

        // Default
        // =======
        $cache = $cache ?: App::$param["mapCache"]["type"];

        $response = [];
        switch ($cache) {
            case "sqlite":
                if (Input::getPath()->part(4) === "schema") {
                    $response = $this->auth(null, array());
                    if (!$response['success']) {
                        return $response;
                    }
                    $searchStr = Input::getPath()->part(5) . ".%";
                } else {
                    $parts = explode(".", Input::getPath()->part(4));
                    $searchStr = $parts[0] . "." . $parts[1];
                    $response = $this->auth(Input::getPath()->part(4), array("all" => true, "write" => true));

                    if (!$response['success']) {
                        return $response;
                    }
                }
                if ($searchStr) {
                    $res = self::deleteFromTileset($searchStr, Connection::$param["postgisdb"]);
                    if (!$res["success"]) {
                        $response['success'] = false;
                        $response['message'] = $res["message"];
                        $response['code'] = '403';
                        return $response;
                    }
                    $response['success'] = true;
                    $response['message'] = "Tile cache deleted.";
                } else {
                    $response['success'] = false;
                    $response['message'] = "No tile cache to delete.";
                }
                break;

            case "disk":
                if (Input::getPath()->part(4) === "schema") {
                    $response = $this->auth(null, array());
                    if (!$response['success']) {
                        return $response;
                    }
                    $layer = Input::getPath()->part(5);
                    $dir = App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param["postgisdb"] . "/" . Input::getPath()->part(5) . ".*";
                } else {
                    $parts = explode(".", Input::getPath()->part(4));
                    $layer = $parts[0] . "." . $parts[1];
                    $response = $this->auth(Input::getPath()->part(4), array("all" => true, "write" => true));
                    $dir = App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param["postgisdb"] . "/" . $layer;

                }
                $res = self::unlinkTiles($dir, $layer);
                if (!$res["success"]) {
                    $response['success'] = false;
                    $response['message'] = $res["message"];
                    $response['code'] = '403';
                    return $response;
                }
                $response['success'] = true;
                $response['message'] = "Tile cache deleted.";
                break;

            case "bdb";
                $dba = \dba_open(App::$param['path'] . "app/wms/mapcache/bdb/" . Connection::$param["postgisdb"] . "/" . "feature.polygon/bdb_feature.polygon.db", "c", "db4");

                $key = dba_firstkey($dba);
                while ($key !== false && $key !== null) {
                    dba_delete($key, $dba);
                    $key = dba_nextkey($dba);
                }
                dba_sync($dba);

                $response['success'] = true;
                $response['message'] = "Tile cache deleted.";
                break;
        }
        return Response::json($response);
    }

    static function bust($layerName)
    {
        $layer = new \app\models\Layer();
        $cache = isset($layer->getAll($layerName, true, false, true, false, Database::getDb())["data"][0]["def"]->cache) ? $layer->getAll($layerName, true, false, true, false, Database::getDb())["data"][0]["def"]->cache : null;

        // Default
        // =======
        $cache = $cache ?: App::$param["mapCache"]["type"];

        $response = [];

        $res = null;

        switch ($cache) {
            case "sqlite":
                $res = self::deleteFromTileset($layerName, Connection::$param["postgisdb"]);
                break;
            case "disk":
                $dir = App::$param['path'] . "app/wms/mapcache/disk/" . Connection::$param["postgisdb"] . "/" . $layerName;
                $res = self::unlinkTiles($dir, $layerName);
                break;
        }
        if (!$res["success"]) {
            $response['success'] = false;
            $response['message'] = $res["message"];
            $response['code'] = '406';
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Tile cache deleted.";
        return $response;
    }

    private function deleteFromTileset($layerName)
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll($layerName, true, false, true, false, Database::getDb());
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock == true) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }

        try {
            $db = new \SQLite3(App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param['postgisdb'] . "/" . $layerName . ".sqlite3");
        } catch (\Exception $exception) {
            // sqlite3 throws an exception when it is unable to connect
            $response['success'] = false;
            $response['message'] = $exception->getMessage();
            $response['code'] = '406';

            return $response;
        }

        $result = $db->query("DELETE FROM tiles");
        if (!$result) {
            $response['success'] = false;
            $response['message'] = $db->lastErrorMsg();
            $response['code'] = '406';
            return $response;
        }
        $db->query("VACUUM");
        $response['success'] = true;
        return $response;
    }

    private static function unlinkTiles($dir, $layerName)
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll($layerName, true, false, true, false, Database::getDb());
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock == true) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }

        if ($dir) {
            exec("rm -R {$dir} 2> /dev/null");
            if (strpos($dir, ".*") !== false) {
                $dir = str_replace(".*", "", $dir);
                exec("rm -R {$dir} 2> /dev/null");
            }
            $response['success'] = true;
        } else {
            $response['success'] = false;
        }
        return $response;
    }

}


