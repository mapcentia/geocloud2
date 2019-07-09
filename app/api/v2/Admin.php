<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2019 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

/**
 * @OA\Info(title="Geocloud API", version="0.1")
 */


namespace app\api\v2;

ini_set('max_execution_time', 0);

use \app\inc\Controller;
use \app\inc\Input;
use \app\models\Database;
use \app\conf\App;
use \app\conf\Connection;
use \app\conf\migration\Sql;

/**
 * Class Admin
 * @package app\api\v2
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
     * @return array
     */
    public function get_buildmapfiles(): array
    {
        $response = [];
        $database = new Database();
        $schemas = $database->listAllSchemas();
        $mapfile = new \app\controllers\Mapfile();
        if (!empty($schemas["data"])) foreach ($schemas["data"] as $schema) {
            Connection::$param['postgisschema'] = $schema["schema"];
            $res = $mapfile->get_index();
            $response["data"][] = [$res[0]["ch"], $res[1]["ch"]];
        }
        $response["success"] = true;
        return $response;
    }

    /**
     * @return array
     */
    public function get_buildmapcachefile(): array
    {
        $response = [];
        $mapcachefile = new \app\controllers\Mapcachefile();
        $res = $mapcachefile->get_index();
        $response["data"] = $res["ch"];
        $response["success"] = true;
        return $response;

    }

    /**
     * @return array
     */
    public function get_restoreqgisfiles(): array
    {
        $qgis = new \app\models\Qgis();
        return $qgis->writeAll(Database::getDb());
    }

    /**
     * @return array
     */
    public function get_runmigrations(): array
    {
        $response = [];
        $data = [];

        $arr = ["mapcentia", "gc2scheduler", Database::getDb()];
        foreach ($arr as $db) {
            \app\models\Database::setDb($db);
            $conn = new \app\inc\Model();

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
                $conn->execQuery($sql, "PDO", "transaction");
                if ($conn->PDOerror[0]) {
                    $data[$db].= "-";
                } else {
                    $data[$db].= "+";
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

    /**
     * @return array
     */
    public function get_reprocessqgis(): array
    {
        $response = [];
        $index = 7;
        $file = !empty(Input::get("file")) ? Input::get("file") : null;

        if ($file) {
            $files = glob(App::$param['path'] . "app/wms/qgsfiles/" . $file, GLOB_BRACE);
        } else {
            $files = glob(App::$param['path'] . "app/wms/qgsfiles/*.{qgs}", GLOB_BRACE);
        }
        if (sizeof($files) == 0) {
            $response["code"] = 400;
            $response["success"] = false;
            $response["message"] = "No files";
            return $response;
        }
        $qgis = new \app\models\Qgis();
        $processqgis = new \app\controllers\upload\Processqgis();

        usort($files, function ($a, $b) {
            return filemtime($b) < filemtime($a);
        });

        foreach ($files as $file) {
            $bits1 = explode("/", $file);
            $bits2 = explode("_", $bits1[$index]);
            if ($bits2[0] == "parsed") {
                if (strlen($bits2[sizeof($bits2) - 1]) == 36) {
                    $arr = [];
                    for ($i = 1; $i < sizeof($bits2) - 1; $i++) {
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
                touch($tmpFile, filemtime($file));
                $res = $processqgis->get_index($orgFileName);
                if ($res["success"]) {
                    unlink($file);
                }
                $response["data"][] = $res["ch"];
            }
        }
        $response["success"] = true;
        return $response;
    }

    public function get_createschema() :array {
        $admin = new \app\models\Admin();
        return $admin->install();
    }
}
