<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2020 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\conf\Connection;
use app\inc\Util;
use Exception;


/**
 * Class Tileseeder
 * @package app\api\v3
 */
class Tileseeder extends Controller
{
    /**
     * @var \app\models\Tileseeder
     */
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
     * @return array<mixed>
     *
     * @OA\Post(
     *   path="/api/v3/tileseeder",
     *   tags={"Tileseeder"},
     *   summary="Starts a mapcache_seed process",
     *   security={{"bearerAuth":{}}},
     *   @OA\RequestBody(
     *     description="mapcache_seed parameters",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="name",type="string", example="My seeder job"),
     *         @OA\Property(property="layer",type="string", example="my_schema.my_table"),
     *         @OA\Property(property="start",type="integer", example=10),
     *         @OA\Property(property="end",type="integer", example=10),
     *         @OA\Property(property="extent",type="string", example="my_schema.my_table_with_extent")
     *       )
     *     )
     *   ),
     *   @OA\Response(
     *     response="200",
     *     description="Return the UUID and process id",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="uuid", type="string", example="C4A3797E-EC6B-4DAC-9474-ADA9083620F3"),
     *         @OA\Property(property="pid",type="integer", example=20326)
     *       )
     *     )
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
        $grid = $arr["grid"];
        $nthreads = !empty($arr["threads"]) ? $arr["threads"] : 1;

        $pgHost = Connection::$param["postgishost"];
        $pgUser = Connection::$param["postgisuser"];
        $pgPassword = Connection::$param["postgispw"];
        $pgPort = Connection::$param["postgisport"];

        $uuid = Util::guid();

        $cmd = "/usr/bin/nohup /usr/local/bin/mapcache_seed -c /var/www/geocloud2/app/wms/mapcache/{$db}.xml -v -t {$layer} -g {$grid} -z {$startZoom},{$endZoom} -d PG:'host={$pgHost} port={$pgPort} user={$pgUser} dbname={$db} password={$pgPassword}' -l '{$extentLayer}' -n {$nthreads}";
        $pid = (int)exec("{$cmd} > /var/www/geocloud2/public/logs/seeder_{$uuid}.log & echo $!");

        $res = $this->tileSeeder->insert(["uuid" => $uuid, "name" => $name, "pid" => $pid, "host" => "test"]);
        if (!$res["success"]) {
            $this->kill($pid); // If we can't insert the pid we kill the process if its running
            return ["success" => false, "message" => $res["message"]];
        }
        return [
            "uuid" => $uuid,
            "pid" => $pid,
            "cmd" => $cmd,
        ];
    }

    /**
     * @return array<mixed>
     * @throws Exception
     *
     * @OA\Delete(
     *   path="/api/v3/tileseeder/{uuid}",
     *   tags={"Tileseeder"},
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
     *     description="Operation status",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="data", type="array", @OA\Items(type="object", @OA\Items(type="string"))),
     *         @OA\Property(property="pid",type="integer", example=20326)
     *       )
     *     )
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
     * @return array<int>
     * @throws Exception
     */
    public function deleteAll(): array
    {
        $pids = $this->get_index()["pids"];
        //TODO check if it's a mapache_seed pid
        foreach ($pids as $pid) {
            $this->kill($pid["pid"]);
        }
        return $pids;
    }

    /**
     * @return array<mixed>
     * @throws Exception
     *
     * @OA\Get(
     *   path="/api/v3/tileseeder",
     *   tags={"Tileseeder"},
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
     * @param int $pid
     */
    private function kill(int $pid): void
    {
        exec("/bin/kill -9 {$pid}");
    }

    /**
     * @return array<string|null>
     * @throws Exception
     *
     * @OA\Get(
     *   path="/api/v3/tileseeder/log/{uuid}",
     *   tags={"Tileseeder"},
     *   summary="Get staus of a running job",
     *   security={{"bearerAuth":{}}},
     *   @OA\Parameter(
     *     name="uuid",
     *     in="path",
     *     required=true,
     *     description="Job identifier",
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
    public function get_log(): array
    {
        $uuid = Route::getParam("uuid");
        if ($uuid) {
            $uuid = strtoupper($uuid);
            $file = "/var/www/geocloud2/public/logs/seeder_{$uuid}.log";
            $handle = fopen($file, "r");
            if ($handle) {
                $str = fgets($handle);
                if ($str) {
                    $data = explode("\r", $str);
                    // There is a carrier return in both end of the string
                    $line = $data[count($data) - 2];
                    pclose($handle);
                } else {
                    $line = null;
                }
            } else {
                $line = null;
            }
            return [
                "data" => $line
            ];
        } else {
            return [
                "data" => null
            ];
        }
    }
}