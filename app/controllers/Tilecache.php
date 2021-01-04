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

    private static function deleteFromTileset($layerName)
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


