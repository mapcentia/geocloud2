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
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\SchedulerLock;
use app\inc\snapshot\SnapshotFormat;
use app\models\Job;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * v4 scheduler jobs: the import jobs of the caller's database (super-user only).
 * A job's five cron columns are exposed as one "schedule" string.
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(schema: "SchedulerJobInput", description: "A job to create: name, schema, url and schedule are required.", required: ["name", "schema", "url", "schedule"], allOf: [new OA\Schema(ref: "#/components/schemas/SchedulerJob")])]
#[OA\Schema(schema: "SchedulerJob", description: "An import job of the scheduler. On PATCH every field is optional.", properties: [
    new OA\Property(property: "name", description: "Job name; the imported table is named after it, so it is normalised on write: transliterated to ASCII, lower-cased, characters other than letters, digits, / _ | + space and - removed, and every run of / _ | + space - replaced by a single underscore (\"Bygninger 2026-09\" becomes \"bygninger_2026_09\"). Read the job back to see the stored name.", type: "string", example: "bygninger"),
    new OA\Property(property: "schema", type: "string", example: "geodanmark"),
    new OA\Property(property: "url", type: "string", example: "https://example.com/wfs?service=WFS&version=2.0.0&request=GetFeature&typeNames=bygning"),
    new OA\Property(property: "schedule", description: "Five-field cron expression: min hour dayofmonth month dayofweek", type: "string", example: "0 3 * * *"),
    new OA\Property(property: "epsg", type: "integer", example: 25832),
    new OA\Property(property: "type", description: "ogr2ogr -nlt or AUTO", type: "string", example: "AUTO"),
    new OA\Property(property: "encoding", type: "string", example: "UTF8"),
    new OA\Property(property: "extra", type: "string", nullable: true),
    new OA\Property(property: "delete_append", type: "boolean", example: false),
    new OA\Property(property: "download_schema", type: "boolean", example: true),
    new OA\Property(property: "presql", type: "string", nullable: true),
    new OA\Property(property: "postsql", type: "string", nullable: true),
    new OA\Property(property: "active", type: "boolean", example: true),
    new OA\Property(property: "snapshot", description: "Queue a Parquet snapshot after each successful import", type: "boolean", example: false),
    new OA\Property(property: "snapshot_formats", description: "Formats of the snapshot queued after a successful import (when snapshot is true); null = the server default snapshot.formats", type: "array", items: new OA\Items(type: "string", enum: SnapshotFormat::IDS), example: ["parquet", "flatgeobuf"], nullable: true),
], type: "object")]
#[AcceptableMethods(['GET', 'POST', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/scheduler/jobs/[id]', scope: Scope::SUPER_USER_ONLY)]
class SchedulerJob extends AbstractApi
{
    private Job $job;
    private string $db;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->job = new Job(new Connection(database: 'gc2scheduler'));
        $this->db = (string)$this->route->jwt['data']['database'];
        $this->resource = 'scheduler-job';
    }

    private function present(array $r): array
    {
        return [
            'id' => (int)$r['id'], 'name' => $r['name'], 'schema' => $r['schema'], 'url' => $r['url'],
            'schedule' => trim("{$r['min']} {$r['hour']} {$r['dayofmonth']} {$r['month']} {$r['dayofweek']}"),
            'epsg' => $r['epsg'] !== null ? (int)$r['epsg'] : null, 'type' => $r['type'], 'encoding' => $r['encoding'], 'extra' => $r['extra'],
            'delete_append' => (bool)$r['delete_append'], 'download_schema' => (bool)$r['download_schema'],
            'presql' => $r['presql'], 'postsql' => $r['postsql'], 'active' => (bool)$r['active'], 'snapshot' => (bool)$r['snapshot'],
            'snapshot_formats' => is_string($r['snapshot_formats'] ?? null) ? json_decode($r['snapshot_formats'], true) : null,
            'lastcheck' => $r['lastcheck'] !== null ? (bool)$r['lastcheck'] : null, 'lasttimestamp' => $r['lasttimestamp'], 'lastrun' => $r['lastrun'],
            'report' => is_string($r['report'] ?? null) ? json_decode($r['report'], true) : null,
        ];
    }

    private function idParam(): int
    {
        return (int)$this->route->getParam('id');
    }

    /** The {id} path segment as a list of ints: "5497" or "5497,5498". */
    private function idList(): array
    {
        return array_map('intval', explode(',', (string)$this->route->getParam('id')));
    }

    #[OA\Get(path: '/api/v4/scheduler/jobs/{id}', operationId: 'getSchedulerJob', description: "Get one or more jobs (comma separated ids), or list all jobs of the database.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'id', description: 'Job id, or comma separated ids. Omit to list all jobs of the database.', in: 'path', required: false, schema: new OA\Schema(type: 'string'), example: '5497,5498')],
        responses: [new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/SchedulerJob"),
            new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/SchedulerJob"))])), new OA\Response(response: 404, description: 'Not found')])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        if (!empty($this->route->getParam('id'))) {
            $list = [];
            foreach ($this->idList() as $id) {
                $row = $this->job->getById($id, $this->db);
                if ($row === null) {
                    throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
                }
                $list[] = $this->present($row);
            }
            return $this->getResponse($list, single: count($list) === 1);
        }
        $rows = $this->job->getAll($this->db)['data'] ?? [];
        return $this->getResponse(array_map(fn($r) => $this->present($r), $rows));
    }

    #[OA\Post(path: '/api/v4/scheduler/jobs', operationId: 'postSchedulerJob', description: "Create one or more jobs.", tags: ['Scheduler'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/SchedulerJobInput"),
            new OA\Schema(type: "array", items: new OA\Items(ref: "#/components/schemas/SchedulerJobInput"))])),
        responses: [new OA\Response(response: 201, description: 'Created'), new OA\Response(response: 400, description: 'Bad request')])]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $body = json_decode(Input::getBody(), true);
        $jobs = array_is_list($body) ? $body : [$body];
        // Check every element (incl. the cron fields) before creating any, so a bad list creates nothing.
        foreach ($jobs as $job) {
            $this->job->validateFields($job);
        }
        $ids = [];
        foreach ($jobs as $job) {
            $ids[] = $this->job->createJob($job, $this->db);
        }
        return $this->postResponse("/api/v4/scheduler/jobs/", $ids);
    }

    #[OA\Patch(path: '/api/v4/scheduler/jobs/{id}', operationId: 'patchSchedulerJob', description: "Update fields of a job.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: "#/components/schemas/SchedulerJob")),
        responses: [new OA\Response(response: 303, description: 'Updated'), new OA\Response(response: 400, description: 'Bad request'), new OA\Response(response: 404, description: 'Not found')])]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function patch_index(): Response
    {
        $body = json_decode(Input::getBody(), true);
        $this->job->patchJob($this->idParam(), $this->db, $body);
        return $this->patchResponse("/api/v4/scheduler/jobs/", [$this->idParam()]);
    }

    #[OA\Delete(path: '/api/v4/scheduler/jobs/{id}', operationId: 'deleteSchedulerJob', description: "Delete one or more jobs (comma separated ids). Refused while a run of any of them is running.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'id', description: 'Job id, or comma separated ids', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: '5497,5498')],
        responses: [new OA\Response(response: 204, description: 'Deleted'), new OA\Response(response: 404, description: 'Not found'), new OA\Response(response: 409, description: 'A run is in progress')])]
    #[Override]
    public function delete_index(): Response
    {
        $ids = $this->idList();
        // Ownership first: a 409 must never quote another database's run uuid
        // for an id the caller does not own.
        foreach ($ids as $id) {
            if ($this->job->getById($id, $this->db) === null) {
                throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
            }
        }
        $lock = new SchedulerLock();
        $lock->reap();
        try {
            // Check every id before deleting any, so a bad list deletes nothing.
            foreach ($ids as $id) {
                $running = $lock->runningRun($id);
                if ($running !== null) {
                    throw new GC2Exception("Job $id has a running run ({$running['uuid']})", 409, null, "JOB_RUNNING");
                }
            }
        } finally {
            $lock->release();
        }
        foreach ($ids as $id) {
            $this->job->deleteJobById($id, $this->db);
        }
        return $this->deleteResponse();
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    #[Override]
    public function validate(): void
    {
        $id = $this->route->getParam('id');
        $method = Input::getMethod();
        if (!empty($id) && !preg_match('/^\d+(,\d+)*$/', (string)$id)) {
            throw new GC2Exception("Job id must be an integer or a comma separated list of integers", 400, null, "INVALID_REQUEST");
        }
        if ($method === 'patch' && str_contains((string)$id, ',')) {
            throw new GC2Exception("PATCH takes a single job id", 400, null, "INVALID_REQUEST");
        }
        if ($method === 'post' && !empty($id)) {
            $this->postWithResource();
        }
        if (in_array($method, ['patch', 'delete'], true) && empty($id)) {
            throw new GC2Exception("A job id is required", 400, null, "INVALID_REQUEST");
        }
        if (in_array($method, ['post', 'patch'], true)) {
            $this->validateRequest(self::getAssert($method), Input::getBody(), $method);
        }
    }

    public static function getAssert(string $method = 'post'): Assert\Collection
    {
        $required = fn(array $c) => $method === 'post' ? new Assert\Required($c) : new Assert\Optional($c);
        $str = [new Assert\Type('string'), new Assert\NotBlank()];
        return new Assert\Collection([
            'name' => $required($str), 'schema' => $required($str), 'url' => $required($str), 'schedule' => $required($str),
            'epsg' => new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()]),
            'type' => new Assert\Optional(new Assert\Type('string')), 'encoding' => new Assert\Optional(new Assert\Type('string')),
            'extra' => new Assert\Optional(), 'presql' => new Assert\Optional(), 'postsql' => new Assert\Optional(),
            'delete_append' => new Assert\Optional(new Assert\Type('bool')), 'download_schema' => new Assert\Optional(new Assert\Type('bool')),
            'active' => new Assert\Optional(new Assert\Type('bool')), 'snapshot' => new Assert\Optional(new Assert\Type('bool')),
            // null is allowed (and on PATCH it resets the job to the server
            // default); anything else must be a non-empty, duplicate-free list
            // of known format ids. Job::toColumns() enforces the same rule for
            // callers that reach the model directly.
            'snapshot_formats' => new Assert\Optional([new Assert\AtLeastOneOf([
                new Assert\IsNull(),
                new Assert\Sequentially([
                    new Assert\Type('array'), new Assert\Count(min: 1), new Assert\Unique(),
                    new Assert\All([new Assert\Type('string'), new Assert\Choice(SnapshotFormat::IDS)]),
                ]),
            ])]),
        ]);
    }
}
