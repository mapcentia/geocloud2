<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

ini_set('max_execution_time', '0');

use app\controllers\Mapcachefile;
use app\controllers\Mapfile;
use app\inc\Controller;
use app\inc\Cache;
use app\inc\Model;
use app\models\Database;
use app\conf\App;
use app\conf\Connection;
use app\migration\Sql2 as Sql;
use app\models\Qgis;
use PDOException;
use Phpfastcache\Exceptions\PhpfastcacheInvalidArgumentException;


/**
 * Class Admin
 * @package app\api\v3
 */
class Admin extends Controller
{

    /**
     * Configuration constructor.
     */
    function __construct()
    {
        parent::__construct();
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/mapfiles",
     *   tags={"Admin"},
     *   summary="Write out all MapFiles for the given user/database",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Returns a list of all created MapFiles",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="array", @OA\Items(type="array", @OA\Items(type="string"))),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     * @throws PhpfastcacheInvalidArgumentException
     */
    public function get_mapfiles(): array
    {
        $response = [];
        $database = new Database();
        $schemas = $database->listAllSchemas();
        if (!empty($schemas["data"])) {
            foreach ($schemas["data"] as $schema) {
                Connection::$param['postgisschema'] = $schema["schema"];
                $res = (new  Mapfile())->get_index();
                $response["data"][] = [$res[0]["ch"], $res[1]["ch"]];
            }
        }
        $response["success"] = true;
        return $response;
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/mapcachefile",
     *   tags={"Admin"},
     *   summary="Write out the MapCache for the given user/database",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Return the name of the created MapCacheFile",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data",type="string", example="/var/www/geocloud2/app/wms/mapcache/mydb.xml"),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function get_mapcachefile(): array
    {
        $response = [];
        $mapcachefile = new Mapcachefile();
        $res = $mapcachefile->get_index();
        $response["data"] = $res["ch"];
        $response["success"] = true;
        return $response;

    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/qgisfiles",
     *   tags={"Admin"},
     *   summary="Write out the QGIS files for the given database",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Returns a list of all created QGIS files",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="array", @OA\Items(type="array", @OA\Items(type="string"))),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function get_qgisfiles(): array
    {
        $qgis = new Qgis();
        return $qgis->writeAll(Database::getDb());
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/migrations",
     *   tags={"Admin"},
     *   summary="Run database migrations for the given user/database",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Run database migration in user database and also in mapcentia and gc2scheduler",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="object", @OA\Schema(type="string")),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function get_migrations(): array
    {
        $response = [];
        $data = [];

        $arr = [Database::getDb(), "mapcentia", "gc2scheduler"];
        foreach ($arr as $db) {
            Database::setDb($db);
            $conn = new Model();

            $sqls = match ($db) {
                "mapcentia" => Sql::mapcentia(),
                "gc2scheduler" => Sql::gc2scheduler(),
                default => Sql::get(),
            };

            foreach ($sqls as $sql) {
                try {
                    $conn->execQuery($sql, "PDO", "transaction");
                    $data[$db] .= "+";
                } catch (PDOException $e) {
                    $data[$db] .= "-";
                }
            }
            $conn->db = NULL;
            $conn = NULL;
        }
        $response["success"] = true;
        $response["data"] = $data;
        return $response;
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/schema",
     *   tags={"Admin"},
     *   summary="Install GC2 schema in a PostGIS enabled database",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Create the settings schema. Returns error if schema already exists",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="message", type="string", example="Schema is installed"),
     *         @OA\Property(property="success",type="boolean", example=false)
     *       )
     *     )
     *   )
     * )
     */
    public function get_schema(): array
    {
        $admin = new \app\models\Admin();
        $res = $admin->install();
        return ["data" => $res, "success" => $res["success"], "message" => $res["message"]];
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/diskcleanup",
     *   tags={"Admin"},
     *   summary="Clean up temporary files",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Returns a list with unlinked files",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="array", @OA\Items(type="array", @OA\Items(type="string"))),
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function get_diskcleanup(): array
    {
        $result = [];
        $dirs = [App::$param["path"] . 'app/tmp'];
        foreach ($dirs as $dir) {
            $this->rrmdir($dir, $result);
        }
        return ["success" => true, "data" => $result];
    }

    /**
     *
     * @param string $dir
     * @param array<string> $result
     */
    private function rrmdir(string $dir, array &$result): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            if ($objects) {
                foreach ($objects as $object) {
                    if ($object != "." && $object != ".." && $object != ".gitignore") {
                        if (is_dir($dir . "/" . $object) && !is_link($dir . "/" . $object)) {
                            $this->rrmdir($dir . "/" . $object, $result);
                        } else {
                            unlink($dir . "/" . $object);
                        }
                        $result[] = $dir . "/" . $object;
                    }
                }
            }
            rmdir($dir);
        }
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/cachestats",
     *   tags={"Admin"},
     *   summary="Get the cache stats",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Returns the cache stats. Output depends on caching back-end",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object"
     *       )
     *     )
     *   )
     * )
     */
    public function get_cachestats(): array
    {
        return ["success" => true, "data" => Cache::getStats()];
    }

    /**
     * @return array<mixed>
     *
     * @OA\Get(
     *   path="/api/v3/admin/cachecleanup",
     *   tags={"Admin"},
     *   summary="Clean up the cache",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Clean up the cache for ALL users",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="success",type="boolean", example=true)
     *       )
     *     )
     *   )
     * )
     */
    public function get_cachecleanup(): array
    {
        $res = Cache::clear();
        return ["data" => $res, "success" => $res["success"], "message" => $res["message"]];
    }
}
