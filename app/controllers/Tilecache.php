<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\controllers;

use app\inc\Controller;
use app\inc\Input;
use app\conf\Connection;
use app\conf\App;
use app\models\Database;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Tilecache
 * @package app\controllers
 */
class Tilecache extends Controller
{
    /**
     * @var string
     */
    private $db;

    /**
     * Tilecache constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->db = Input::getPath()->part(2);
        $dbSplit = explode("@", $this->db);
        if (sizeof($dbSplit) == 2) {
            $this->db = $dbSplit[1];
        }
    }

    /**
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function delete_index(): array
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
                    $schema = Input::getPath()->part(5);
                    $file = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $schema . ".sqlite3";
//                    die($file);
                    @unlink($file);
                    $response['success'] = true;
                    $response['message'] = "Tile cache for schema deleted";
                    return $response;
                } else {
                    $parts = explode(".", Input::getPath()->part(4));
                    $searchStr = $parts[0] . "." . $parts[1];
                    $response = $this->auth(Input::getPath()->part(4), array("all" => true, "write" => true));
                    if (!$response['success']) {
                        return $response;
                    }
                }
                $res = self::unlikeSQLiteFile($searchStr);
                if (!$res["success"]) {
                    $response['success'] = false;
                    $response['message'] = $res["message"];
                    $response['code'] = '403';
                    return $response;
                }
                $response['success'] = true;
                $response['message'] = "Tile cache deleted.";

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
                $dba = dba_open(App::$param['path'] . "app/wms/mapcache/bdb/" . Connection::$param["postgisdb"] . "/" . "feature.polygon/bdb_feature.polygon.db", "c", "db4");

                $key = dba_firstkey($dba);
                while ($key !== false) {
                    dba_delete($key, $dba);
                    $key = dba_nextkey($dba);
                }
                dba_sync($dba);

                $response['success'] = true;
                $response['message'] = "Tile cache deleted.";
                break;
        }
        return $response;
    }

    /**
     * @param string $layerName
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    static function bust(string $layerName): array
    {
        $layer = new \app\models\Layer();
        $cache = isset($layer->getAll($layerName, true, false, true, false, Database::getDb())["data"][0]["def"]->cache) ? $layer->getAll($layerName, true, false, true, false, Database::getDb())["data"][0]["def"]->cache : null;
        $cache = $cache ?: App::$param["mapCache"]["type"];
        $response = [];
        $res = null;

        switch ($cache) {
            case "sqlite":
                $res = self::unlikeSQLiteFile($layerName);
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

    /**
     * @param string $layerName
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    private static function unlikeSQLiteFile(string $layerName): array
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll($layerName, true, false, true, false, Database::getDb());
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }
        $file1 = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $layerName . ".sqlite3";
        $file2 = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $layerName . ".json.sqlite3";
        @unlink($file1);
        @unlink($file2);
        $response['success'] = true;
        return $response;
    }

    /**
     * @param string $dir
     * @param string $layerName
     * @return array<mixed>
     * @throws PhpfastcacheInvalidArgumentException
     */
    private static function unlinkTiles(string $dir, string $layerName): array
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll($layerName, true, false, true, false, Database::getDb());
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }
        if ($dir) {
            exec("rm -R $dir 2> /dev/null");
            if (strpos($dir, ".*") !== false) {
                $dir = str_replace(".*", "", $dir);
                exec("rm -R $dir 2> /dev/null");
            }
            $response['success'] = true;
        } else {
            $response['success'] = false;
        }
        return $response;
    }
}

