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
        // In schema mode the route is .../tilecache/schema/<schema>, so part(4) is
        // the literal word "schema" and the name is in part(5). Looking part(4) up
        // as a layer — which this did — could only ever answer the install default,
        // so a schema configured for another backend silently cleared nothing.
        $target = Input::getPath()->part(4) === "schema"
            ? (string)Input::getPath()->part(5)
            : (string)Input::getPath()->part(4);
        $cache = self::cacheBackendFor($target);

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

            default:
                // s3 and memcache have no delete here. Saying so is the point: this
                // used to fall through and return an empty response, so the caller
                // was told nothing at all while nothing was deleted.
                $response['success'] = false;
                $response['message'] = "Cannot clear a '$cache' tile cache from here.";
                $response['code'] = '501';
                break;
        }
        return $response;
    }

    /**
     * Which cache backend a tileset name lives in.
     *
     * A layer's tileset is decided by the layer's own def. A merged per-schema
     * tileset — a bare schema name, or one whose only dot is the .mvt/.json
     * suffix — is decided by settings.schema_settings. Anything unresolved falls
     * back to the install default.
     *
     * Without the schema branch, clearing a schema tileset configured for disk or
     * s3 would look in sqlite, delete nothing and report success.
     */
    static function cacheBackendFor(string $tilesetName, ?\app\inc\Connection $connection = null): string
    {
        $default = !empty(App::$param["mapCache"]["type"]) ? App::$param["mapCache"]["type"] : 'sqlite';

        // Strip the format suffix: a vector tileset is <name>.mvt / <name>.json,
        // and the settings belong to <name>.
        $base = preg_replace('/\.(mvt|json)$/', '', $tilesetName);

        // A GC2 layer key is always schema.table, so a name with no dot cannot be
        // a layer — it is a merged per-schema tileset, and asking the Layer model
        // about it would be both pointless and, under the CLI, fatal.
        if (!str_contains($base, '.')) {
            $row = (new \app\models\SchemaSettings(connection: $connection))->get($base);
            $def = $row && $row['def'] ? json_decode($row['def']) : null;
            return !empty($def->cache) ? (string)$def->cache : $default;
        }

        $db = $connection ? $connection->database : Database::getDb();
        $layer = $connection ? new \app\models\Layer(connection: $connection) : new \app\models\Layer();
        $meta = $layer->getAll($db, true, $tilesetName, false, true);
        return $meta["data"][0]["def"]->cache ?? $default;
    }

    /**
     * @param string $layerName
     * @return array
     * @throws GC2Exception
     * @throws PhpfastcacheInvalidArgumentException
     * @throws PhpfastcacheLogicException
     */
    static function bust(string $layerName, ?\app\inc\Connection $connection = null): array
    {
        // Prefer an explicit connection (worker/FrankenPHP-safe) over the process
        // -global "current database"; fall back to the global for legacy callers.
        $db = $connection ? $connection->database : Database::getDb();
        $cache = self::cacheBackendFor($layerName, $connection);
        $response = [];
        $res = null;

        switch ($cache) {
            case "sqlite":
                $res = self::unlikeSQLiteFile($layerName, $connection);
                break;
            case "disk":
                $dir = App::$param['path'] . "app/wms/mapcache/disk/" . $db . "/" . $layerName;
                $res = self::unlinkTiles($dir, $layerName, $connection);
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
    private static function unlikeSQLiteFile(string $layerName, ?\app\inc\Connection $connection = null): array
    {
        $db = $connection ? $connection->database : Database::getDb();
        $layer = $connection ? new \app\models\Layer(connection: $connection) : new \app\models\Layer();
        $meta = $layer->getAll($db, true, $layerName, false, true);
        if (isset($meta["data"][0]["def"]->lock) && $meta["data"][0]["def"]->lock) {
            $response['success'] = false;
            $response['message'] = "The layer is locked in the tile cache. Unlock it in the Tile cache settings.";
            $response['code'] = '406';
            return $response;
        }
        $file1 = App::$param['path'] . "app/wms/mapcache/sqlite/" . $db . "/" . $layerName . ".sqlite3";
        $file2 = App::$param['path'] . "app/wms/mapcache/sqlite/" . $db . "/" . $layerName . ".json.sqlite3";
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
    private static function unlinkTiles(string $dir, string $layerName, ?\app\inc\Connection $connection = null): array
    {
        $db = $connection ? $connection->database : Database::getDb();
        $layer = $connection ? new \app\models\Layer(connection: $connection) : new \app\models\Layer();
        $meta = $layer->getAll($db, true, $layerName, false, true);
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

