<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v3;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\inc\Connection;
use app\inc\tileseeder\SeedCommand;
use app\models\SeedJob;
use Exception;


/**
 * Class Tileseeder
 *
 * A thin shim over the v4 tile seeder queue (settings.seed_jobs / app\models\SeedJob):
 * every v3 action here queues or reads the same row the v4 resource
 * (api/v4/tileseeder/jobs) does, instead of spawning a seeding process and
 * signalling it directly on whichever node happened to serve the request.
 *
 * @package app\api\v3
 */
class Tileseeder extends Controller
{
    /** Matches a real uuid (hex letters case-insensitive: pre-v4 Util::guid()
     *  produced uppercase ones, and real legacy clients still hold those). Used
     *  before any path segment reaches SeedJob::get() -- AGENTS.md §3. */
    private const string UUID_PATTERN = '/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/';

    private static function isUuid(mixed $value): bool
    {
        return is_string($value) && preg_match(self::UUID_PATTERN, $value) === 1;
    }

    /**
     * @return array<mixed>
     *
     * @OA\Post(
     *   path="/api/v3/tileseeder",
     *   tags={"Tileseeder"},
     *   summary="Queues a tile seeding job; a worker runs it",
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
     *     description="The queued job's uuid. pid is always null here: a worker, not this request, claims and runs the job.",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="uuid", type="string", example="c4a3797e-ec6b-4dac-9474-ada9083620f3"),
     *         @OA\Property(property="pid", type="integer", nullable=true, example=null)
     *       )
     *     )
     *   )
     * )
     * @throws GC2Exception
     */
    public function post_index(): array
    {
        $arr = json_decode(Input::getBody(), true);
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $database = $jwt["database"];
        // v3 field names map onto the v4 columns.
        $tileset = (string)($arr["layer"] ?? '');
        $grid = (string)($arr["grid"] ?? '');
        $zoomStart = (int)($arr["start"] ?? 0);
        $zoomEnd = (int)($arr["end"] ?? 0);
        $extent = $arr["extent"] ?? null;
        if ($extent !== null) {
            // layer and grid get a (string) cast; extent does not, because null is a
            // valid value for it. A non-scalar (json_decode(..., true) only ever
            // hands back an array here, never an object) used to reach
            // SeedCommand::validate()'s ?string parameter directly and crash with a
            // TypeError (500). A scalar -- a numeric extent, say -- is cast the same
            // way layer/grid already are, not rejected: an earlier, over-eager
            // !is_string() guard turned {"extent":123} -- which used to coerce,
            // validate and queue -- into a 400 too.
            if (!is_scalar($extent)) {
                throw new GC2Exception('extent must be a string or null', 400, null, 'INVALID_REQUEST');
            }
            $extent = (string)$extent;
        }
        $threads = (int)($arr["threads"] ?? 1);

        // One Connection for both the validator (which uses it to prove an
        // `extent` relation exists, spec §7) and the queue write, so v3 and v4
        // validate against exactly the same database.
        $connection = new Connection(database: $database);
        SeedCommand::validate($database, $tileset, $grid, $zoomStart, $zoomEnd, $extent, $threads, $connection);
        // No privilege check on `extent` here: index.php refuses every
        // api/v3/tileseeder route that is not a super-user, and a super-user may
        // read every relation in its own database anyway (the v4 controller's
        // requireRead() returns early for exactly that case).
        $row = new SeedJob(connection: $connection)->queue([
            'name' => $arr["name"] ?? $tileset,
            'username' => $jwt["uid"],
            'tileset' => $tileset,
            'grid' => $grid,
            'zoom_start' => $zoomStart,
            'zoom_end' => $zoomEnd,
            'extent_layer' => $extent,
            'threads' => $threads,
        ]);
        // pid stays null until a worker claims the job; v3 used to return the pid of
        // a process this request had started itself.
        return ["uuid" => $row["uuid"], "pid" => null];
    }

    /**
     * @return array<mixed>
     * @throws Exception
     *
     * @OA\Delete(
     *   path="/api/v3/tileseeder/{uuid}",
     *   tags={"Tileseeder"},
     *   summary="Cancels a seed job by uuid. Use * to cancel every job started by user.",
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
     *     description="{success, pid} for one uuid, {success, pids} for *, or {success:false, message} when nothing matched",
     *     @OA\MediaType(
     *       mediaType="application/json",
     *       @OA\Schema(
     *         type="object",
     *         @OA\Property(property="success", type="boolean"),
     *         @OA\Property(property="message", type="string", nullable=true, description="Present when success is false"),
     *         @OA\Property(property="pid", type="object", nullable=true, description="{uuid, pid, name} of the cancelled job"),
     *         @OA\Property(property="pids", type="array", nullable=true, @OA\Items(type="object"), description="One {uuid, pid, name} per job cancelled by *")
     *       )
     *     )
     *   )
     * )
     */
    public function delete_index(): array
    {
        // The route is a single path segment (api/v3/tileseeder/{uuid}), which
        // never matches the two-segment {action}/{uuid} pattern index.php
        // registers, so Route::getParam("uuid") is always null here; read the
        // raw segment instead, exactly as index.php's own fallback dispatch does
        // (part($n + 1) with n = 3 for api/v3/tileseeder).
        $uuid = Route::getParam("uuid") ?? Input::getPath()->part(4);
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $jobs = new SeedJob(connection: new Connection(database: $jwt["database"]));
        if ($uuid === "*") {
            $cancelled = [];
            foreach ($jobs->list(status: 'running', username: $jwt["uid"]) as $row) {
                $jobs->requestCancel($row["uuid"]);
                $cancelled[] = ["uuid" => $row["uuid"], "pid" => $row["pid"] !== null ? (int)$row["pid"] : null, "name" => $row["name"]];
            }
            return ["success" => true, "pids" => $cancelled];
        }
        // AGENTS.md §3: validate the id segment with a positive regex before it
        // reaches SQL. A missing segment (part(4) === null, e.g. a client that
        // interpolated an empty variable) or anything that is not a real uuid used
        // to reach SeedJob::get() directly: null crashed with a TypeError and a
        // non-uuid string crashed Postgres with SQLSTATE 22P02, both as a 500 that
        // echoed internals back to the client. Neither ever matched a row, so both
        // get the same not-found shape the old (also broken) routing always gave.
        if (!self::isUuid($uuid)) {
            return ["success" => false, "message" => "No job with uuid: " . $uuid];
        }
        $row = $jobs->get($uuid);
        if ($row === null) {
            return ["success" => false, "message" => "No job with uuid: " . $uuid];
        }
        $result = $jobs->requestCancel($uuid);
        if ($result === 'noop') {
            // Already finished, or a legacy row with no status at all (no v4 code
            // ever claims it): nothing was cancelled, so do not say it was.
            return ["success" => false, "message" => "No running job with uuid: " . $uuid];
        }
        return ["success" => true, "pid" => ["uuid" => $row["uuid"], "pid" => $row["pid"] !== null ? (int)$row["pid"] : null, "name" => $row["name"]]];
    }

    /**
     * @return array<mixed>
     * @throws Exception
     *
     * @OA\Get(
     *   path="/api/v3/tileseeder",
     *   tags={"Tileseeder"},
     *   summary="Get every running seed job in the database (not filtered by user)",
     *   security={{"bearerAuth":{}}},
     *   @OA\Response(
     *     response="200",
     *     description="Operation status"
     *   )
     * )
     */
    public function get_index(): array
    {
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $jobs = new SeedJob(connection: new Connection(database: $jwt["database"]));
        $res = [];
        foreach ($jobs->list(status: 'running') as $row) {
            $res[] = ["uuid" => $row["uuid"], "pid" => $row["pid"] !== null ? (int)$row["pid"] : null, "name" => $row["name"]];
        }
        return ["success" => true, "pids" => $res];
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
        // No segment at all keeps its existing {"data":null} answer; so does a
        // segment that isn't a real uuid (AGENTS.md §3) -- it used to reach
        // SeedJob::get() directly and crash with Postgres's SQLSTATE 22P02,
        // echoing the query back to the client, the same bug round 1 closed in
        // delete_index() but left open here.
        if (!self::isUuid($uuid)) {
            return ["data" => null];
        }
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $row = new SeedJob(connection: new Connection(database: $jwt["database"]))->get($uuid);
        $log = $row["log"] ?? null;
        if ($log === null) {
            return ["data" => null];
        }
        // v3 returned one line: the last thing the process said.
        $lines = preg_split('/[\r\n]+/', trim($log)) ?: [];
        return ["data" => $lines ? end($lines) : null];
    }
}
