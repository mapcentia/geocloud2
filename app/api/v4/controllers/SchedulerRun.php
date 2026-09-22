<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\AcceptedResponse;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\SchedulerLock;
use app\models\Job;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * v4 scheduler runs: start a job now, list and inspect runs (the
 * started_jobs registry kept by get.php), stop a running run.
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(schema: "SchedulerRun", description: "One run of a scheduler job.", properties: [
    new OA\Property(property: "uuid", type: "string"), new OA\Property(property: "job", type: "integer"), new OA\Property(property: "name", type: "string", nullable: true),
    new OA\Property(property: "pid", type: "integer"), new OA\Property(property: "host", type: "string", nullable: true), new OA\Property(property: "slot", type: "integer", nullable: true),
    new OA\Property(property: "status", type: "string", enum: ["running", "succeeded", "failed", "skipped", "lost"]), new OA\Property(property: "stale", description: "No progress signal (heartbeat or start) for 5 minutes", type: "boolean"),
    new OA\Property(property: "started_at", type: "string", format: "date-time"), new OA\Property(property: "heartbeat", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "finished_at", type: "string", format: "date-time", nullable: true), new OA\Property(property: "exit_reason", type: "string", nullable: true),
    new OA\Property(property: "log", description: "stdout of the run (the Info/Warning/Error lines get.php prints), updated at every heartbeat and on finish; capped at 1 MB with the tail kept. Only in the single-run response, never in listings.", type: "string", nullable: true),
], type: "object")]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/scheduler/runs/[uuid]', scope: Scope::SUPER_USER_ONLY)]
class SchedulerRun extends AbstractApi
{
    private const array STATUSES = ['running', 'succeeded', 'failed', 'skipped', 'lost'];
    private Job $job;
    private string $db;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->job = new Job(new Connection(database: 'gc2scheduler'));
        $this->db = (string)$this->route->jwt['data']['database'];
        $this->resource = 'scheduler-run';
    }

    private function present(array $r, bool $withLog = false): array
    {
        $out = [
            'uuid' => $r['uuid'], 'job' => (int)$r['id'], 'name' => $r['name'], 'pid' => (int)$r['pid'], 'host' => $r['host'],
            'slot' => $r['slot'] !== null ? (int)$r['slot'] : null, 'status' => $r['status'],
            // No progress signal for 5 minutes. A run that died before its
            // first heartbeat has none, so fall back to started_at.
            'stale' => $r['status'] === 'running' && (time() - strtotime($r['heartbeat'] ?? $r['started_at'])) > 300,
            'started_at' => $r['started_at'], 'heartbeat' => $r['heartbeat'], 'finished_at' => $r['finished_at'], 'exit_reason' => $r['exit_reason'],
        ];
        if ($withLog) {
            $out['log'] = $r['log'] ?? null;
        }
        return $out;
    }

    /** @return array<int, array<string,mixed>> runs of the caller's database after reaping */
    private function runs(): array
    {
        $lock = new SchedulerLock();
        $lock->reap();
        $rows = $lock->runsFor($this->db);
        $lock->release();
        return $rows;
    }

    #[OA\Get(path: '/api/v4/scheduler/runs/{uuid}', operationId: 'getSchedulerRun', description: "Get one run (with its log), or list runs (running first, then the newest finished; without log). Filters: ?job=, ?status=.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'uuid', description: 'Run uuid. Omit to list runs (running plus the newest finished ones).', in: 'path', required: false, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'job', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/SchedulerRun")), new OA\Response(response: 404, description: 'Not found')])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $uuid = $this->route->getParam('uuid');
        if (!empty($uuid)) {
            // Direct lookup, not a scan of runsFor(): a real but older run
            // falls outside the newest-50 listing.
            $lock = new SchedulerLock();
            $lock->reap();
            $run = $lock->run((string)$uuid, $this->db);
            $lock->release();
            if ($run === null) {
                throw new GC2Exception("Run $uuid not found", 404, null, "RUN_NOT_FOUND");
            }
            return $this->getResponse([$this->present($run, withLog: true)], single: true);
        }
        $rows = $this->runs();
        $job = isset($_GET['job']) && ctype_digit((string)$_GET['job']) ? (int)$_GET['job'] : null;
        $status = isset($_GET['status']) && in_array($_GET['status'], self::STATUSES, true) ? $_GET['status'] : null;
        $rows = array_values(array_filter($rows, fn($r) => ($job === null || (int)$r['id'] === $job) && ($status === null || $r['status'] === $status)));
        return $this->getResponse(array_map(fn($r) => $this->present($r), $rows));
    }

    #[OA\Post(path: '/api/v4/scheduler/runs', operationId: 'postSchedulerRun', description: "Start a job now. Asynchronous: poll the runs list for the new run.", tags: ['Scheduler'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ["job"], properties: [new OA\Property(property: "job", type: "integer"), new OA\Property(property: "force", description: "Ignore delete_append and overwrite", type: "boolean")], type: "object")),
        responses: [new OA\Response(response: 202, description: 'Starting'), new OA\Response(response: 404, description: 'Job not found'), new OA\Response(response: 409, description: 'A run of the job is already running')])]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $body = json_decode(Input::getBody(), true);
        $jobId = (int)$body['job'];
        if ($this->job->getById($jobId, $this->db) === null) {
            throw new GC2Exception("Job $jobId not found", 404, null, "JOB_NOT_FOUND");
        }
        $lock = new SchedulerLock();
        $lock->reap();
        $running = $lock->runningRun($jobId);
        $lock->release();
        if ($running !== null) {
            throw new GC2Exception("Job $jobId is already running (run {$running['uuid']})", 409, null, "JOB_RUNNING");
        }
        $this->job->runJob($jobId, $this->db, 'Started via API v4 by ' . $this->route->jwt['data']['uid'], !empty($body['force']), null, true, true);
        return new AcceptedResponse(['job' => $jobId, 'status' => 'starting', '_links' => ['runs' => "/api/v4/scheduler/runs?job=$jobId"]]);
    }

    #[OA\Delete(path: '/api/v4/scheduler/runs/{uuid}', operationId: 'deleteSchedulerRun', description: "Stop a running run: SIGINT, then SIGKILL after 30 s.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Signal sent'), new OA\Response(response: 404, description: 'No running run with that uuid'), new OA\Response(response: 409, description: 'The run is on another host')])]
    #[Override]
    public function delete_index(): Response
    {
        $uuid = (string)$this->route->getParam('uuid');
        $run = null;
        foreach ($this->runs() as $r) {
            if ($r['uuid'] === $uuid && $r['status'] === 'running') {
                $run = $r;
            }
        }
        if ($run === null) {
            throw new GC2Exception("No running run with uuid $uuid", 404, null, "RUN_NOT_FOUND");
        }
        $host = gethostname() ?: 'unknown';
        if ($run['host'] !== $host) {
            throw new GC2Exception("Run $uuid is on host {$run['host']}, not $host", 409, null, "RUN_ON_OTHER_HOST");
        }
        $this->job->kill((int)$run['pid']);
        return new GetResponse(data: ['uuid' => $uuid, 'signal' => 'SIGINT']);
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    #[Override]
    public function validate(): void
    {
        $uuid = $this->route->getParam('uuid');
        $method = Input::getMethod();
        if ($method === 'post') {
            if (!empty($uuid)) {
                $this->postWithResource();
            }
            $this->validateRequest(new Assert\Collection([
                'job' => new Assert\Required([new Assert\Type('integer'), new Assert\Positive()]),
                'force' => new Assert\Optional(new Assert\Type('bool')),
            ]), Input::getBody(), $method);
        }
        if ($method === 'delete' && empty($uuid)) {
            throw new GC2Exception("A run uuid is required", 400, null, "INVALID_REQUEST");
        }
    }
}
