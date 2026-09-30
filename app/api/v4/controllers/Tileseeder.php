<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\api\v4\controllers;

use app\api\v4\AbstractApi;
use app\api\v4\AcceptableAccepts;
use app\api\v4\AcceptableContentTypes;
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\AcceptedResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Model;
use app\inc\Route2;
use app\inc\tileseeder\SeedCommand;
use app\models\Authorization;
use app\models\SeedJob;
use app\models\User;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Ad hoc tile seeding as a queue: the request validates and writes a row, a worker
 * (app/scripts/seed_worker.php) claims and runs it. Status, log and cancel come
 * from the row, so they work from any node — v3 read pgrep and sent kill -9 on the
 * node that happened to take the request.
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: 'SeedJobInput',
    description: 'A seed job to queue. tileset, grid, zoom_start and zoom_end are required; every other property of SeedJob is server-owned and ignored (in fact rejected) on input.',
    required: ['tileset', 'grid', 'zoom_start', 'zoom_end'],
    allOf: [new OA\Schema(ref: '#/components/schemas/SeedJob')],
)]
#[OA\Schema(
    schema: 'SeedJob',
    description: 'One tile seeding job. Queued by POST, run by a worker on whichever node claims it. name, tileset, grid, zoom_start, zoom_end, extent_layer and threads are the request fields (see SeedJobInput); every other property is server-owned (readOnly) and only ever appears in a response.',
    properties: [
        new OA\Property(property: 'uuid', description: 'Server-generated.', type: 'string', readOnly: true, example: 'c4a3797e-ec6b-4dac-9474-ada9083620f3'),
        new OA\Property(property: 'name', type: 'string', example: 'Seed roads'),
        new OA\Property(property: 'status', description: 'pending, running, succeeded, failed or cancelled. Null for a row written before v4.', type: 'string', enum: ['pending', 'running', 'succeeded', 'failed', 'cancelled'], nullable: true, readOnly: true),
        new OA\Property(property: 'stale', description: 'Computed: running, but no heartbeat for 10 minutes, so the run or its node is gone.', type: 'boolean', readOnly: true),
        new OA\Property(property: 'username', description: 'Null for a legacy row written before v4.', type: 'string', nullable: true, readOnly: true),
        new OA\Property(property: 'tileset', description: 'The mapcache tileset (layer key). Null only for a legacy row.', type: 'string', nullable: true, example: 'myschema.roads'),
        new OA\Property(property: 'grid', description: 'Null only for a legacy row.', type: 'string', nullable: true, example: 'GoogleMapsCompatible'),
        new OA\Property(property: 'zoom_start', description: 'Null only for a legacy row.', type: 'integer', nullable: true, example: 0),
        new OA\Property(property: 'zoom_end', description: 'Null only for a legacy row.', type: 'integer', nullable: true, example: 12),
        new OA\Property(property: 'extent_layer', type: 'string', nullable: true),
        new OA\Property(property: 'threads', description: 'Null only for a legacy row.', type: 'integer', nullable: true, example: 2),
        new OA\Property(property: 'host', description: 'The node that claimed the job.', type: 'string', nullable: true, readOnly: true),
        new OA\Property(property: 'pid', type: 'integer', nullable: true, readOnly: true),
        new OA\Property(property: 'created', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'started', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'finished', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'heartbeat', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'cancel_requested', type: 'string', format: 'date-time', nullable: true, readOnly: true),
        new OA\Property(property: 'error', type: 'string', nullable: true, readOnly: true),
        new OA\Property(property: 'log', description: 'The tail of the seed output. Only on a single-job read.', type: 'string', nullable: true, readOnly: true),
        new OA\Property(property: '_links', type: 'object', readOnly: true, properties: [
            new OA\Property(property: 'self', type: 'string', example: '/api/v4/tileseeder/jobs/c4a3797e-ec6b-4dac-9474-ada9083620f3'),
        ]),
    ],
    type: 'object'
)]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', name: 'bearerAuth', in: 'header', bearerFormat: 'JWT', scheme: 'bearer')]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/tileseeder/jobs/[uuid]', scope: Scope::SUB_USER_ALLOWED)]
class Tileseeder extends AbstractApi
{
    private SeedJob $jobs;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->jobs = new SeedJob(connection: $connection);
        $this->resource = 'tileseeder';
    }

    #[OA\Post(path: '/api/v4/tileseeder/jobs', operationId: 'postSeedJob', description: "Queue one seed job (object) or several (array of objects). Asynchronous: answers 202 and a worker runs it. Requires write/owner on the tileset's relation. Every job in the list is validated and authorized before any of them is queued.", tags: ['Tileseeder'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(oneOf: [
            new OA\Schema(ref: '#/components/schemas/SeedJobInput'),
            new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/SeedJobInput'))])),
        responses: [
            new OA\Response(response: 202, description: 'Queued; poll _links.self. An object for a single request, an array for an array request.',
                headers: [new OA\Header(header: 'Location', description: 'The created job(s): /api/v4/tileseeder/jobs/<uuid[,uuid…]>', schema: new OA\Schema(type: 'string'))],
                content: new OA\JsonContent(oneOf: [
                    new OA\Schema(ref: '#/components/schemas/SeedJob'),
                    new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/SeedJob'))])),
            new OA\Response(response: 400, description: 'Bad request: malformed body, an empty list, or a field failing validation'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'Unknown tileset'),
            new OA\Response(response: 429, description: 'tileseeder.maxPending would be exceeded'),
        ])]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $body = json_decode(Input::getBody(), true);
        $list = array_is_list($body) ? $body : [$body];
        $jwt = $this->route->jwt['data'];
        $pending = count($this->jobs->list(status: 'pending'));
        $max = App::$param['tileseeder']['maxPending'] ?? 20;
        if ($pending + count($list) > $max) {
            throw new GC2Exception("Too many queued seed jobs (max $max)", 429, null, 'TOO_MANY_PENDING');
        }
        // Validate every job before writing any of them, so a bad list queues
        // nothing. SeedCommand::validate() must run before requireWrite(): it is
        // what proves `tileset` is a real, known-safe tileset name before it is
        // ever interpolated into settings.getColumns()'s literal-quoted SQL by
        // requireWrite()'s privilege lookup (Model::getGeometryColumns() ->
        // getColumns()) — the same hazard Snapshot::post_index() guards against
        // for schema/relation. getAssert()'s Regex on tileset is the same rule
        // one layer earlier; either alone would leave the other caller exposed.
        foreach ($list as $job) {
            SeedCommand::validate($jwt['database'], (string)$job['tileset'], (string)$job['grid'],
                (int)$job['zoom_start'], (int)$job['zoom_end'], $job['extent_layer'] ?? null, (int)($job['threads'] ?? 1));
            $this->requireWrite((string)$job['tileset']);
        }
        $rows = [];
        foreach ($list as $job) {
            $rows[] = $this->jobs->queue([
                'name' => $job['name'] ?? $job['tileset'],
                'username' => $jwt['uid'],
                'tileset' => $job['tileset'],
                'grid' => $job['grid'],
                'zoom_start' => (int)$job['zoom_start'],
                'zoom_end' => (int)$job['zoom_end'],
                'extent_layer' => $job['extent_layer'] ?? null,
                'threads' => (int)($job['threads'] ?? 1),
            ]);
        }
        $presented = array_map(fn($r) => SeedJob::present($r), $rows);
        return new AcceptedResponse(count($presented) === 1 ? $presented[0] : $presented,
            location: '/api/v4/tileseeder/jobs/' . implode(',', array_column($rows, 'uuid')));
    }

    #[OA\Get(path: '/api/v4/tileseeder/jobs/{uuid}', operationId: 'getSeedJob', description: 'One seed job (object) with its log tail, or several by comma separated uuids (array).', tags: ['Tileseeder'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: '#/components/schemas/SeedJob')),
            new OA\Response(response: 404, description: 'Not found')])]
    #[OA\Get(path: '/api/v4/tileseeder/jobs', operationId: 'getSeedJobs', description: "The caller's seed jobs, newest first, without the log. A super-user sees every job of the database.", tags: ['Tileseeder'],
        parameters: [
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'tileset', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: '#/components/schemas/SeedJob')))])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $uuid = $this->route->getParam('uuid');
        if (!empty($uuid)) {
            $rows = array_map(fn($u) => $this->own(trim($u)), explode(',', (string)$uuid));
            $presented = array_map(fn($r) => SeedJob::present($r, withLog: true), $rows);
            return $this->getResponse($presented, single: count($presented) === 1);
        }
        $rows = $this->jobs->list(
            status: Input::get('status') ?: null,
            tileset: Input::get('tileset') ?: null,
            username: $this->isSuperUser() ? null : $this->route->jwt['data']['uid'],
        );
        return $this->getResponse(array_map(fn($r) => SeedJob::present($r), $rows));
    }

    #[OA\Delete(path: '/api/v4/tileseeder/jobs/{uuid}', operationId: 'deleteSeedJob', description: 'Ask for a seed job to stop. 204 when it was still queued (cancelled outright, or already finished), 202 when it is running and its worker has to act. Comma separated uuids allowed; every uuid is checked before anything is written.', tags: ['Tileseeder'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 202, description: 'At least one job was running; its worker will finish the cancel.', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Stopping'),
                new OA\Property(property: 'uuid', type: 'array', items: new OA\Items(type: 'string')),
            ], type: 'object')),
            new OA\Response(response: 204, description: 'Cancelled or already finished'),
            new OA\Response(response: 400, description: 'A uuid is malformed'),
            new OA\Response(response: 404, description: 'Not found'),
        ])]
    #[Override]
    public function delete_index(): Response
    {
        $uuids = array_map('trim', explode(',', (string)$this->route->getParam('uuid')));
        foreach ($uuids as $u) {
            $this->own($u);   // 404 before anything is written
        }
        $running = false;
        foreach ($uuids as $u) {
            $running = $this->jobs->requestCancel($u) === 'cancelling' || $running;
        }
        return $running
            ? new AcceptedResponse(['success' => true, 'message' => 'Stopping', 'uuid' => $uuids])
            : $this->deleteResponse();
    }

    /**
     * The row, if the caller may see it. A super-user sees every job of the
     * database; everyone else only their own — a uuid belonging to someone else is
     * 404, not 403, so a token cannot enumerate other users' jobs.
     *
     * @return array<string, mixed>
     * @throws GC2Exception
     */
    private function own(string $uuid): array
    {
        if (!preg_match('/^[0-9a-fA-F-]{36}$/', $uuid)) {
            throw new GC2Exception('Invalid uuid', 400, null, 'INVALID_REQUEST');
        }
        $row = $this->jobs->get($uuid);
        if ($row === null || (!$this->isSuperUser() && $row['username'] !== $this->route->jwt['data']['uid'])) {
            throw new GC2Exception('Seed job not found', 404, null, 'JOB_NOT_FOUND');
        }
        return $row;
    }

    private function isSuperUser(): bool
    {
        return !empty($this->route->jwt['data']['superUser']);
    }

    /**
     * Write/owner on the tileset's relation, the same rule MapcacheTileset applies
     * for deleting tiles: seeding writes to the cache of that layer.
     *
     * @throws GC2Exception|\Throwable
     */
    private function requireWrite(string $tileset): void
    {
        $jwt = $this->route->jwt['data'];
        if (!empty($jwt['superUser'])) {
            return;
        }
        $layer = preg_replace('/\.(mvt|json)$/i', '', $tileset);
        $schema = explode('.', $layer)[0];
        $auth = new Authorization(connection: $this->connection);
        // User lives in the central user database and repoints whatever
        // Connection it is handed ($connection->database = mapcentia), so it
        // gets its own: $connection must still address $jwt['database'] for the
        // privileges lookup below, which reads settings.getColumns() there.
        $chain = new User(connection: new Connection(user: $jwt['uid'], database: $jwt['database'], schema: $schema))
            ->getFullInheritance($jwt['userGroup'] ?? [], $jwt['database']);
        if ($auth->isOwner($jwt['uid'], $chain, $schema)) {
            return;
        }
        $privileges = json_decode((string)new Model(connection: $this->connection)->getGeometryColumns($layer, 'privileges'), true) ?: [];
        if ($auth->extractHighestPrivilege($privileges, $jwt['uid'], $chain) === 'read/write') {
            return;
        }
        throw new GC2Exception('Insufficient privileges to seed this tileset', 403, null, 'INSUFFICIENT_PRIVILEGES');
    }

    #[Override]
    public function validate(): void
    {
        $method = Input::getMethod();
        if ($method === 'post') {
            $decoded = json_decode(Input::getBody(), true);
            // array_is_list(null) is a TypeError, and json_decode() of a bare
            // scalar or "null" is neither a job object nor a list of them.
            if (!is_array($decoded)) {
                throw new GC2Exception('A JSON object or an array of objects is required', 400, null, 'INVALID_REQUEST');
            }
            // json_decode(..., true) makes {} and [] the same empty PHP array,
            // so this also catches an empty object body.
            if ($decoded === []) {
                throw new GC2Exception('An empty list of seed job requests is not allowed', 400, null, 'INVALID_REQUEST');
            }
            $this->validateRequest(collection: self::getAssert($method), data: Input::getBody(), method: $method);
        }
        if ($method === 'delete' && empty($this->route->getParam('uuid'))) {
            throw new GC2Exception('A job uuid is required', 400, null, 'INVALID_REQUEST');
        }
    }

    /**
     * tileset and extent_layer are interpolated into settings.getColumns()'s
     * literal-quoted SQL (Model::getGeometryColumns() -> getColumns(), reached
     * from requireWrite()) and into the mapcache_seed command line
     * (SeedCommand::argv()): a positive class, not a negated one, so a quote or
     * other SQL/shell metacharacter can never slip through. SeedCommand::validate()
     * enforces the same class again once the tileset is known — see post_index().
     */
    private static function getAssert(string $method): Assert\Collection
    {
        $ident = '/^[A-Za-z0-9_.:\-]+$/';
        return new Assert\Collection(
            fields: [
                'name' => new Assert\Optional(new Assert\Type('string')),
                'tileset' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string'), new Assert\Regex($ident)]),
                'grid' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'zoom_start' => new Assert\Required(new Assert\Type('integer')),
                'zoom_end' => new Assert\Required(new Assert\Type('integer')),
                'extent_layer' => new Assert\Optional(new Assert\AtLeastOneOf([
                    new Assert\IsNull(),
                    new Assert\Sequentially([new Assert\Type('string'), new Assert\Regex($ident)]),
                ])),
                'threads' => new Assert\Optional(new Assert\Type('integer')),
            ],
            allowExtraFields: false,
        );
    }

    #[Override]
    public function put_index(): Response
    {
        // Not supported: a queued seed is replaced by cancelling it and queueing another.
    }

    #[Override]
    public function patch_index(): Response
    {
        // Not supported.
    }
}
