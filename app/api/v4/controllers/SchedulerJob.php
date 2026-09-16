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
#[OA\Schema(schema: "SchedulerJob", description: "An import job of the scheduler.", required: ["name", "schema", "url", "schedule"], properties: [
    new OA\Property(property: "name", type: "string", example: "bygninger"),
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
            'lastcheck' => $r['lastcheck'] !== null ? (bool)$r['lastcheck'] : null, 'lasttimestamp' => $r['lasttimestamp'], 'lastrun' => $r['lastrun'],
            'report' => is_string($r['report'] ?? null) ? json_decode($r['report'], true) : null,
        ];
    }

    private function idParam(): int
    {
        return (int)$this->route->getParam('id');
    }

    #[OA\Get(path: '/api/v4/scheduler/jobs/{id}', operationId: 'getSchedulerJob', description: "Get a job, or list all jobs of the database.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/SchedulerJob")), new OA\Response(response: 404, description: 'Not found')])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $id = $this->route->getParam('id');
        if (!empty($id)) {
            $row = $this->job->getById($this->idParam(), $this->db);
            if ($row === null) {
                throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
            }
            return $this->getResponse([$this->present($row)], single: true);
        }
        $rows = $this->job->getAll($this->db)['data'] ?? [];
        return $this->getResponse(array_map(fn($r) => $this->present($r), $rows));
    }

    #[OA\Post(path: '/api/v4/scheduler/jobs', operationId: 'postSchedulerJob', description: "Create a job.", tags: ['Scheduler'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: "#/components/schemas/SchedulerJob")),
        responses: [new OA\Response(response: 201, description: 'Created'), new OA\Response(response: 400, description: 'Bad request')])]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $body = json_decode(Input::getBody(), true);
        $id = $this->job->createJob($body, $this->db);
        return $this->postResponse("/api/v4/scheduler/jobs/", [$id]);
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

    #[OA\Delete(path: '/api/v4/scheduler/jobs/{id}', operationId: 'deleteSchedulerJob', description: "Delete a job. Refused while a run of it is running.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Deleted'), new OA\Response(response: 404, description: 'Not found'), new OA\Response(response: 409, description: 'A run is in progress')])]
    #[Override]
    public function delete_index(): Response
    {
        $id = $this->idParam();
        $lock = new SchedulerLock();
        $lock->reap();
        $running = $lock->runningRun($id);
        $lock->release();
        if ($running !== null && $this->job->getById($id, $this->db) !== null) {
            throw new GC2Exception("Job $id has a running run ({$running['uuid']})", 409, null, "JOB_RUNNING");
        }
        $this->job->deleteJobById($id, $this->db);
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
        if (!empty($id) && !ctype_digit((string)$id)) {
            throw new GC2Exception("Job id must be an integer", 400, null, "INVALID_REQUEST");
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
        ]);
    }
}
