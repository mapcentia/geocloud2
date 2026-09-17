<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */


namespace app\api\v3;

use app\exceptions\GC2Exception;
use app\inc\Controller;
use app\inc\Route;
use app\inc\Input;
use app\inc\Jwt;
use app\models\Job;
use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
class Scheduler extends Controller
{
    private Job $job;
    private string $db;

    /**
     * @throws GC2Exception
     */
    public function __construct()
    {
        parent::__construct();
        $this->job = new Job();
        $this->db = Jwt::extractPayload(Input::getJwtToken())["data"]["database"];
    }

    /**
     * @return array
     */
    #[OA\Post(path: '/api/v3/scheduler/{jobid}', operationId: 'startSchedulerJob', tags: ['Scheduler'])]
    #[OA\Parameter(name: 'jobid', description: 'Job id', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '876')]
    #[OA\Response(response: 202, description: 'Accepted')]
    public function post_index(): array
    {
        $body = Input::getBody();
        $data = json_decode($body);
        $jobId = $data->job;
        $include = $data->include;
        $force = !empty($data->force);
        $name = null;
        if (!empty($data->name)) {
            $name = $data->name;
        }
        if (is_numeric($jobId)) {
            $this->job->runJob((int)$jobId, $this->db, $name, $force, null, true, true);
        } else {
            $jobs = $this->job->getAll($this->db)['data'];
            foreach ($jobs as $job) {
                if ($job['schema'] == $jobId) {
                    $this->job->runJob($job['id'], $this->db, $name, $force, $include, true, true);
                }
            }
        }
        header("Location: /api/v4/scheduler");
        return ['code' => 202];
    }

    /**
     * @return array[]
     */
    #[OA\Get(path: '/api/v3/scheduler', operationId: 'getRunningSchedulerJobs', tags: ['Scheduler'])]
    #[OA\Response(response: 200, description: 'OK',
        content: new OA\JsonContent(properties: [
            new OA\Property(property: 'jobs', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'uuid', description: 'Run uuid', type: 'string'),
                new OA\Property(property: 'id', description: 'Job id', type: 'integer'),
                new OA\Property(property: 'name', description: 'Job name', type: 'string', nullable: true),
                new OA\Property(property: 'pid', description: 'Process id', type: 'integer'),
                new OA\Property(property: 'host', description: 'Host the run happened/happens on', type: 'string'),
                new OA\Property(property: 'slot', description: 'Run slot', type: 'integer', nullable: true),
                new OA\Property(property: 'status', description: 'running|succeeded|failed|lost|skipped', type: 'string'),
                new OA\Property(property: 'started_at', description: 'Start timestamp', type: 'string'),
                new OA\Property(property: 'heartbeat', description: 'Last heartbeat timestamp', type: 'string', nullable: true),
                new OA\Property(property: 'finished_at', description: 'Finish timestamp', type: 'string', nullable: true),
                new OA\Property(property: 'exit_reason', description: 'Reason for a non-running status', type: 'string', nullable: true),
                new OA\Property(property: 'stale', description: 'No progress signal (heartbeat or start) for 5 minutes', type: 'boolean'),
            ], type: 'object')),
        ], type: 'object'))]
    public function get_index(): array
    {
        $res = [];
        foreach ($this->job->getAllStartedJobs($this->db) as $r) {
            // No progress signal for 5 minutes. A run that died before its
            // first heartbeat has none, so fall back to started_at.
            $stale = $r['status'] === 'running'
                && (time() - strtotime($r['heartbeat'] ?? $r['started_at'])) > 300;
            $res[] = [
                "uuid" => $r["uuid"], "id" => (int)$r["id"], "name" => $r["name"], "pid" => (int)$r["pid"],
                "host" => $r["host"], "slot" => $r["slot"] !== null ? (int)$r["slot"] : null,
                "status" => $r["status"], "started_at" => $r["started_at"], "heartbeat" => $r["heartbeat"],
                "finished_at" => $r["finished_at"], "exit_reason" => $r["exit_reason"], "stale" => $stale,
            ];
        }
        return ["jobs" => $res];
    }

    /**
     * Stops a running run on this host: SIGINT first (so get.php records
     * "terminated"), SIGKILL after 30 s.
     */
    #[OA\Delete(path: '/api/v3/scheduler/{uuid}', operationId: 'stopSchedulerRun', tags: ['Scheduler'])]
    #[OA\Parameter(name: 'uuid', description: 'Run uuid from GET /api/v3/scheduler', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Signal sent')]
    #[OA\Response(response: 404, description: 'No running run with that uuid')]
    #[OA\Response(response: 409, description: 'The run is on another host')]
    public function delete_index(): array
    {
        $uuid = Route::getParam("uuid");
        $run = null;
        foreach ($this->job->getAllStartedJobs($this->db) as $r) {
            if ($r['uuid'] === $uuid && $r['status'] === 'running') {
                $run = $r;
            }
        }
        if ($run === null) {
            throw new GC2Exception("No running run with uuid $uuid", 404, null, "NO_RUN");
        }
        $host = gethostname() ?: 'unknown';
        if ($run['host'] !== $host) {
            throw new GC2Exception("Run $uuid is on host {$run['host']}, not $host", 409, null, "RUN_ON_OTHER_HOST");
        }
        $this->job->kill((int)$run['pid']);
        return ["success" => true, "uuid" => $uuid, "signal" => "SIGINT"];
    }
}