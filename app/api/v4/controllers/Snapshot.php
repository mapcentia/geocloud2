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
use app\api\v4\Responses\Response;
use app\api\v4\Scope;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Model;
use app\inc\Route2;
use app\inc\snapshot\SnapshotFormat;
use app\inc\snapshot\SnapshotStorageFactory;
use app\models\Snapshot as SnapshotModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Asynchronous Parquet snapshots of a table or view to S3.
 *
 * POST queues a job (202) and a cron worker (app/scripts/snapshot_worker.php)
 * exports the relation with ogr2ogr and uploads data.parquet + metadata.json.
 * GET returns the job status. Super-user only.
 *
 * @package app\api\v4
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "SnapshotAccepted",
    description: "A queued snapshot job.",
    properties: [
        new OA\Property(property: "id", type: "string", example: "0b8c2f2e-2f5a-4d9c-9d3a-1b2c3d4e5f60"),
        new OA\Property(property: "status", type: "string", example: "pending"),
        new OA\Property(property: "_links", type: "object", properties: [new OA\Property(property: "self", type: "string", example: "/api/v4/snapshots/0b8c2f2e-2f5a-4d9c-9d3a-1b2c3d4e5f60")]),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "SnapshotRequest",
    description: "A request to snapshot a table or view to Parquet on S3.",
    required: ["schema", "relation"],
    properties: [
        new OA\Property(property: "schema", title: "Schema", description: "Schema of the relation.", type: "string", example: "geodanmark"),
        new OA\Property(property: "relation", title: "Relation", description: "Table or view name.", type: "string", example: "bygning"),
        new OA\Property(property: "srs", title: "SRS", description: "Optional EPSG code to reproject to. Omit to keep the native SRID.", type: "integer", example: 25832),
    ],
    type: "object"
)]
#[OA\Schema(
    schema: "Snapshot",
    description: "Status of a snapshot job.",
    properties: [
        new OA\Property(property: "id", type: "string", example: "0f4c1b2e-..."),
        new OA\Property(property: "schema", type: "string", example: "geodanmark"),
        new OA\Property(property: "relation", type: "string", example: "bygning"),
        new OA\Property(property: "srs", type: "integer", example: 25832, nullable: true),
        new OA\Property(property: "status", type: "string", enum: ["pending", "running", "succeeded", "failed", "superseded"]),
        new OA\Property(property: "snapshot_date", description: "UTC date the export ran; the {date} segment of the relation snapshot read API.", type: "string", format: "date", example: "2026-09-16", nullable: true),
        new OA\Property(property: "s3_path", type: "string", example: "s3://gc2-parquet/prod/mydb/schema=geodanmark/relation=bygning/_gc2_snapshot_date=2026-09-15/", nullable: true),
        new OA\Property(property: "row_count", type: "integer", example: 123456, nullable: true),
        new OA\Property(property: "schema_version", description: "md5 fingerprint of relation_schema, for drift detection.", type: "string", example: "9e107d9d372bb6826bd81d3542a419d6", nullable: true),
        new OA\Property(property: "relation_schema", description: "Columns of the relation at snapshot time (name and PostgreSQL type).", type: "array", items: new OA\Items(type: "object"), example: [["column_name" => "gid", "data_type" => "integer"], ["column_name" => "the_geom", "data_type" => "geometry(Point,25832)"]], nullable: true),
        new OA\Property(property: "error", type: "string", nullable: true),
        new OA\Property(property: "username", type: "string"),
        new OA\Property(property: "created", type: "string", format: "date-time"),
        new OA\Property(property: "started", type: "string", format: "date-time", nullable: true),
        new OA\Property(property: "finished", type: "string", format: "date-time", nullable: true),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'POST', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/snapshots/[id]', scope: Scope::SUPER_USER_ONLY)]
class Snapshot extends AbstractApi
{
    private SnapshotModel $snapshot;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->snapshot = new SnapshotModel($connection);
        $this->resource = 'snapshot';
    }

    /**
     * Shapes a settings.snapshots row for output.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function present(array $row): array
    {
        return [
            'id' => $row['uuid'],
            'schema' => $row['schema_name'],
            'relation' => $row['relation_name'],
            'srs' => $row['srs'] !== null ? (int)$row['srs'] : null,
            'status' => $row['status'],
            // Exposed so a client that queued a snapshot can address it in the
            // read API (/schemas/{s}/relations/{r}/snapshots/{snapshot_date})
            // without guessing the worker's UTC date.
            'snapshot_date' => $row['snapshot_date'],
            's3_path' => $row['s3_path'],
            'row_count' => $row['row_count'] !== null ? (int)$row['row_count'] : null,
            'schema_version' => $row['schema_version'],
            'relation_schema' => $row['relation_schema'] !== null ? json_decode($row['relation_schema'], true) : null,
            'error' => $row['error'],
            'username' => $row['username'],
            'created' => $row['created'],
            'started' => $row['started'],
            'finished' => $row['finished'],
        ];
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Get(path: '/api/v4/snapshots/{id}', operationId: 'getSnapshot', description: "Get one snapshot job (object), or several by comma separated ids (array).", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'id', description: 'Snapshot id, or comma separated ids', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/Snapshot"),
                new OA\Schema(type: 'array', items: new OA\Items(ref: "#/components/schemas/Snapshot"))])),
            new OA\Response(response: 403, description: 'Super-user only'),
            new OA\Response(response: 404, description: 'Not found'),
        ],
    )]
    #[OA\Get(path: '/api/v4/snapshots', operationId: 'getSnapshots', description: "List the newest snapshot jobs.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', description: 'List filter: schema', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', description: 'List filter: relation', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/Snapshot"))),
            new OA\Response(response: 403, description: 'Super-user only'),
        ],
    )]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $id = $this->route->getParam('id');
        if (!empty($id)) {
            $list = [];
            foreach (explode(',', (string)$id) as $one) {
                $list[] = $this->present($this->snapshot->get(trim($one))['data']);
            }
            return $this->getResponse($list, single: count($list) === 1);
        }
        $schema = isset($_GET['schema']) && $_GET['schema'] !== '' ? (string)$_GET['schema'] : null;
        $relation = isset($_GET['relation']) && $_GET['relation'] !== '' ? (string)$_GET['relation'] : null;
        $rows = $this->snapshot->list($schema, $relation);
        return $this->getResponse(array_map(fn($r) => $this->present($r), $rows));
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/snapshots', operationId: 'postSnapshot', description: "Queue a Parquet snapshot of one relation (object) or several (array of objects). Returns 202; poll the returned link(s) for status. With an array, every relation is checked before anything is queued.", tags: ['Snapshots'])]
    #[OA\RequestBody(description: 'Relation(s) to snapshot.', required: true, content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/SnapshotRequest"),
        new OA\Schema(type: 'array', items: new OA\Items(ref: "#/components/schemas/SnapshotRequest"))]))]
    #[OA\Response(response: 202, description: 'Accepted; poll _links.self for status. An object for a single request, an array for an array request.',
        content: new OA\JsonContent(oneOf: [new OA\Schema(ref: "#/components/schemas/SnapshotAccepted"),
            new OA\Schema(type: 'array', items: new OA\Items(ref: "#/components/schemas/SnapshotAccepted"))]))]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 403, description: 'Super-user only')]
    #[OA\Response(response: 404, description: 'Relation not found')]
    #[OA\Response(response: 406, description: 'POST with a resource id is not allowed')]
    #[OA\Response(response: 409, description: 'A snapshot of this relation is already pending or running')]
    #[OA\Response(response: 501, description: 'Snapshot storage is not configured')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        // Same check the worker and the read API make: s3 needs bucket +
        // credentials, local needs localPath. Throws 501 SNAPSHOT_NOT_CONFIGURED.
        SnapshotStorageFactory::fromApp();
        $body = json_decode(Input::getBody(), true);
        $isList = array_is_list($body);
        $requests = $isList ? $body : [$body];
        $uid = $this->route->jwt["data"]["uid"];
        $model = new Model($this->connection);

        // Check every request before queueing any, so a bad list queues nothing.
        $seen = [];
        foreach ($requests as $r) {
            $schema = (string)$r['schema'];
            $relation = (string)$r['relation'];
            if (isset($seen["$schema.$relation"])) {
                throw new GC2Exception("Relation $schema.$relation is listed more than once", 400, null, "INVALID_REQUEST");
            }
            $seen["$schema.$relation"] = true;
            if (!$model->doesRelationExists("$schema.$relation")) {
                throw new GC2Exception("Relation $schema.$relation does not exist", 404, null, "RELATION_NOT_FOUND");
            }
            if ($this->snapshot->hasActive($schema, $relation)) {
                throw new GC2Exception("A snapshot of $schema.$relation is already pending or running", 409, null, "SNAPSHOT_IN_PROGRESS");
            }
        }
        $accepted = [];
        foreach ($requests as $r) {
            $srs = isset($r['srs']) ? (int)$r['srs'] : null;
            // Task 4 wires the request's own `formats`; until then every
            // snapshot is produced in the server default formats.
            $id = $this->snapshot->create((string)$r['schema'], (string)$r['relation'], $srs, $uid, SnapshotFormat::defaults());
            $accepted[] = ['id' => $id, 'status' => 'pending', '_links' => ['self' => "/api/v4/snapshots/$id"]];
        }
        return new AcceptedResponse($isList ? $accepted : $accepted[0]);
    }

    public function put_index(): Response
    {
        // Not supported (AcceptableMethods excludes PUT).
    }

    public function patch_index(): Response
    {
        // Not supported (AcceptableMethods excludes PATCH).
    }

    public function delete_index(): Response
    {
        // Not supported (AcceptableMethods excludes DELETE).
    }

    /**
     * @throws GC2Exception
     */
    #[Override]
    public function validate(): void
    {
        $id = $this->route->getParam('id');
        $method = Input::getMethod();
        if ($method === 'post') {
            if (!empty($id)) {
                $this->postWithResource();
            }
            $decoded = json_decode(Input::getBody(), true);
            if (is_array($decoded) && array_is_list($decoded) && $decoded === []) {
                throw new GC2Exception("An empty list of snapshot requests is not allowed", 400, null, "INVALID_REQUEST");
            }
            $this->validateRequest(self::getAssert(), Input::getBody(), $method);
        }
    }

    /**
     * Names must be safe for the S3 key and the quoted SQL identifier, and
     * for interpolation into settings.getColumns()'s literal-quoted SQL
     * (Model::getGeometryColumns() -> getColumns()): a positive class,
     * not a negated one, so a quote or other SQL metacharacter can never
     * slip through.
     */
    static public function getAssert(): Assert\Collection
    {
        $name = [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Regex('/^[A-Za-z0-9_\-]+$/')];
        return new Assert\Collection([
            'schema' => new Assert\Required($name),
            'relation' => new Assert\Required($name),
            'srs' => new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()]),
        ]);
    }
}
