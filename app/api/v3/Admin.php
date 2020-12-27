<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

ini_set('max_execution_time', '0');

use app\inc\Controller;
use app\inc\Cache;
use app\inc\Input;
use app\inc\Model;
use app\models\Database;
use app\conf\App;
use app\conf\Connection;
use app\migration\Sql;
use PDOException;


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
     */
    public function get_mapfiles(): array
    {
        $response = [];
        $database = new Database();
        $schemas = $database->listAllSchemas();
        $mapfile = new \app\controllers\Mapfile();
        if (!empty($schemas["data"])) {
            foreach ($schemas["data"] as $schema) {
                Connection::$param['postgisschema'] = $schema["schema"];
                $res = $mapfile->get_index();
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
        $mapcachefile = new \app\controllers\Mapcachefile();
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
        $qgis = new \app\models\Qgis();
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

            switch ($db) {
                case "mapcentia":
                    $sqls = Sql::mapcentia();
                    break;
                case "gc2scheduler":
                    $sqls = Sql::gc2scheduler();
                    break;
                default:
                    $sqls = Sql::get();
                    break;
            }

            foreach ($sqls as $sql) {
                try {
                    $conn->execQuery($sql, "PDO", "transaction");
                } catch (PDOException $e) {
                    $response["success"] = false;
                    $response["message"] = $e->getMessage();
                    return $response;
                }
                if ($conn->PDOerror[0]) {
                    $data[$db] .= "-";
                } else {
                    $data[$db] .= "+";
                }
                $conn->PDOerror = NULL;
            }
            $conn->db = NULL;
            $conn = NULL;
        }
        $response["success"] = true;
        $response["data"] = $data;
        return $response;
    }

    public function get_reprocessqgis(): array
    {
        // TODO
        // SELECT FROM settings.qgis_files WHERE db=....
    }

    /**
     * @return array<mixed>
     *
     * DEPRECATED
     */
    public function get_qgisfromfiles(): array
    {
        $response = [];
        $index = 7;
        $file = !empty(Input::get("file")) ? Input::get("file") : null;

        if ($file) {
            $files = glob(App::$param['path'] . "app/wms/qgsfiles/" . $file, GLOB_BRACE);
        } else {
            $files = glob(App::$param['path'] . "app/wms/qgsfiles/*.{qgs}", GLOB_BRACE);
        }
        if ($files && sizeof($files) == 0) {
            $response["code"] = 400;
            $response["success"] = false;
            $response["message"] = "No files";
            return $response;
        }
        $qgis = new \app\models\Qgis();
        $processqgis = new \app\controllers\upload\Processqgis();

        if ($files) {
            usort($files, function ($a, $b) {
                return filemtime($b) < filemtime($a);
            });
            foreach ($files as $file) {
                $bits1 = explode("/", $file);
                $bits2 = explode("_", $bits1[$index]);
                if ($bits2[0] == "parsed") {
                    if (strlen($bits2[sizeof($bits2) - 1]) == 36) {
                        $arr = [];
                        $size = sizeof($bits2);
                        for ($i = 1; $i < $size - 1; $i++) {
                            $arr[] = $bits2[$i];
                        }
                        $orgFileName = implode("_", $arr) . ".qgs";
                        $qgis->flagAsOld($bits1[$index]);

                    } else {
                        continue;
                    }
                } else {
                    $orgFileName = $bits1[$index];
                }

                $tmpFile = App::$param['path'] . "/app/tmp/" . Connection::$param["postgisdb"] . "/__qgis/" . $orgFileName;

                if (copy($file, $tmpFile)) {
                    $time = filemtime($file);
                    if ($time) {
                        touch($tmpFile, $time);
                    }
                    $res = $processqgis->get_index($orgFileName);
                    if ($res["success"]) {
                        unlink($file);
                    }
                    $response["data"][] = $res["ch"];
                }
            }
        }
        $response["success"] = true;
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
        return ["data" => $admin->install()];
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
        return ["success" => true, "message" => "Unlinked files", "data" => $result];
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
        return ["stats" => Cache::getStats()];
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
        return Cache::clear();
    }
}
