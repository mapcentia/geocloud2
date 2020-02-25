<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

/**
 * @OA\Info(title="GC2 API", version="0.1")
 * @OA\SecurityScheme(
 *      securityScheme="bearerAuth",
 *      in="header",
 *      name="bearerAuth",
 *      type="http",
 *      scheme="bearer",
 *      bearerFormat="JWT",
 * ),
 */


namespace app\api\v3;


use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\conf\Connection;

class Tileseeder extends Controller
{
    private $tileSeeder;

    /**
     * Tileseeder constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->tileSeeder = new \app\models\Tileseeder();
    }

    /**
     * @return array
     *
     * @OA\Post(
     *   path="/api/v3/tileseeder",
     *   tags={"tileseeder"},
     *   summary="Starts a mapcache_seed process",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="mapcache_seed parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string"),
     *         @OA\Property(property="layer",type="string"),
     *         @OA\Property(property="start",type="integer"),
     *         @OA\Property(property="end",type="integer"),
     *         @OA\Property(property="extent",type="string")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function post_index(): array
    {
        $body = Input::getBody();
        $arr = json_decode($body, true);

        // TODO check if object has all properties

        $db = Jwt::extractPayload(Input::getJwtToken())["data"]["database"];
        $name = $arr["name"];
        $layer = $arr["layer"];
        $startZoom = $arr["start"];
        $endZoom = $arr["end"];
        $extentLayer = $arr["extent"];

        $pgHost = Connection::$param["postgishost"];
        $pgUser = Connection::$param["postgisuser"];
        $pgPassword = Connection::$param["postgispw"];
        $pgPort = Connection::$param["postgisport"];

        $uuid = \app\inc\Util::guid();

        $cmd = "/usr/bin/nohup /usr/local/bin/mapcache_seed -c /var/www/geocloud2/app/wms/mapcache/{$db}.xml -t {$layer} -g 25832 -z {$startZoom},{$endZoom} -d PG:'host={$pgHost} port={$pgPort} user={$pgUser} dbname={$db} password={$pgPassword}' -l '{$extentLayer}' -n 1";
        $pid = exec("{$cmd} > /var/www/geocloud2/public/logs/seeder_{$uuid}.log & echo $!");

        $res = $this->tileSeeder->insert(["uuid" => $uuid, "name" => $name, "pid" => $pid, "host" => "test"]);
        if (!$res["success"]) {
            $this->kill($pid); // If we can't insert the pid we kill the process if its running
            return ["success" => false, "message" => $res["message"]];
        }
        return [
            "uuid" => $uuid,
            "pid" => $pid,
//            "cmd" => $cmd
        ];
    }

    /**
     * @return array
     * @throws \Exception
     *
     * @OA\Delete(
     *   path="/api/v3/tileseeder/{uuid}",
     *   tags={"tileseeder"},
     *   summary="Kills a mapcache_seed process by uuid. Use * to kill all processes started by user.",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="uuid",
     *     in="path",
     *     required=true,
     *     description="Uuid of Process",
     *     @OA\Schema(
     *       type="string"
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function delete_index(): array
    {
        $uuid = Route::getParam("uuid");

        if ($uuid == "*") {
            $pids = $this->deleteAll();
            return ["success" => true, "pids" => $pids];
        }

        // Get pid from seed_jobs table
        $job = $this->tileSeeder->getByUuid($uuid);
        $pid = !empty($job["data"]["pid"]) ? $job["data"]["pid"] : null;
        if ($pid) {
            // Check if pid is running
            $cmd = "pgrep mapcache_seed";
            exec($cmd, $out);
            if (in_array($pid, $out)) {
                $this->kill($pid);
            } else {
                $pid = null;
            }
            return ["success" => true, "pid" => ["uuid" => $job["data"]["uuid"], "pid" => $pid, "name" => $job["data"]["name"]]];
        } else {
            return ["success" => false, "message" => "No job with uuid: " . $uuid];
        }
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function deleteAll(): array
    {
        $pids = $this->get_index()["pids"];
        foreach ($pids as $pid) {
            $this->kill($pid["pid"]);
        }
        return $pids;
    }

    /**
     * @return array
     * @throws \Exception
     *
     * @OA\Get(
     *   path="/api/v3/tileseeder",
     *   tags={"tileseeder"},
     *   summary="Get all running mapcache_seed processes started by user",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function get_index(): array
    {
        $fromDb = $this->tileSeeder->getAll()["data"];
        $cmd = "pgrep mapcache_seed";
        exec($cmd, $out);
        $res = [];
        // Find active pids
        foreach ($fromDb as $value) {
            if (in_array($value["pid"], $out)) {
                $res[] = ["uuid" => $value["uuid"], "pid" => $value["pid"], "name" => $value["name"]];
            }
        }
        return ["success" => true, "pids" => $res];
    }

    /**
     * @param $pid
     */
    private function kill($pid)
    {
        exec("/bin/kill -9 {$pid}");
    }

    /**
     * @return array
     */
    public function get_log(): array
    {
        $uuid = Route::getParam("uuid");
        $log = file_get_contents("/var/www/geocloud2/public/logs/seeder_{$uuid}.log");
        return ["log" => $log];
    }
}