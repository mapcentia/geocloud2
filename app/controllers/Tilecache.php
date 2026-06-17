<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

namespace app\controllers;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Input;
use app\conf\Connection;
use app\conf\App;
use app\models\Database;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;
use Phpfastcache\Exceptions\PhpfastcacheLogicException;
use Psr\Cache\InvalidArgumentException;

class Tilecache extends Controller
{
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     * @throws InvalidArgumentException
     * @throws GC2Exception
     */
    public function delete_index(): array
    {
        $layer = new \app\models\Layer();
        $cache = $layer->getAll(Database::getDb(), true, Input::getPath()->part(4), false, true)["data"][0]["def"]->cache;

        // Default
        // =======
        $cache = $cache ?: App::$param["mapCache"]["type"];

        $response = [];
        switch ($cache) {
            case "sqlite":
                if (Input::getPath()->part(4) === "schema") {
                    $response = $this->isOwner();
                    if (!$response['success']) {
                        return $response;
                    }
                    $schema = Input::getPath()->part(5);
                    $file = App::$param['path'] . "app/wms/mapcache/sqlite/" . Connection::$param["postgisdb"] . "/" . $schema . ".sqlite3";
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
                if ($searchStr) {
                    $res = self::unlikeSQLiteFile($searchStr);
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
                    $response = $this->isOwner();
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
     * @return array
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    static function bust(string $layerName): array
    {
        $layer = new \app\models\Layer();
        $cache = isset($layer->getAll(Database::getDb(), true, $layerName, false, true)["data"][0]["def"]->cache) ? $layer->getAll(Database::getDb(), true, $layerName, false, true)["data"][0]["def"]->cache : null;
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
     * @return array
     * @throws GC2Exception
     * @throws PhpfastcacheLogicException
     */
    private static function unlikeSQLiteFile(string $layerName): array
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll(Database::getDb(), true, $layerName, false, true);
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
     * @return array
     * @throws GC2Exception
     * @throws PhpfastcacheLogicException
     */
    private static function unlinkTiles(string $dir, string $layerName): array
    {
        $layer = new \app\models\Layer();
        $meta = $layer->getAll(Database::getDb(), true, $layerName, false, true);
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }
        if ($dir) {
            exec("rm -R $dir 2> /dev/null");
            if (str_contains($dir, ".*")) {
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

