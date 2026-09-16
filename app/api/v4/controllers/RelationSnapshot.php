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
use app\api\v4\AcceptableMethods;
use app\api\v4\Controller;
use app\api\v4\Responses\GetResponse;
use app\api\v4\Responses\RedirectResponse;
use app\api\v4\Responses\Response;
use app\api\v4\Responses\StreamedResponse;
use app\api\v4\Scope;
use app\conf\App;
use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\inc\Input;
use app\inc\Route2;
use app\inc\snapshot\RangeNotSatisfiable;
use app\inc\snapshot\RangeRequest;
use app\inc\snapshot\SnapshotAuthorizer;
use app\inc\snapshot\SnapshotRef;
use app\inc\snapshot\SnapshotStorage;
use app\inc\snapshot\SnapshotStorageFactory;
use app\models\Snapshot as SnapshotModel;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;

/**
 * Read API for published GeoParquet snapshots of a relation: list, metadata,
 * and the files themselves with HEAD + byte-range support so Parquet readers
 * (DuckDB read_parquet) can fetch footers and row groups selectively.
 *
 * Authorization runs before any catalog file name or storage call: super
 * users always, sub-users by schema ownership or layer read privilege.
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(
    schema: "RelationSnapshot",
    description: "A published snapshot of a relation.",
    properties: [
        new OA\Property(property: "snapshot_date", type: "string", format: "date", example: "2026-09-16"),
        new OA\Property(property: "snapshot_id", type: "string"),
        new OA\Property(property: "row_count", type: "integer"),
        new OA\Property(property: "size_bytes", type: "integer"),
        new OA\Property(property: "schema_version", type: "string"),
        new OA\Property(property: "files", type: "array", items: new OA\Items(type: "object")),
        new OA\Property(property: "published", type: "string", format: "date-time"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/relations/{relation}/snapshots/[date]/(action)/[file]', scope: Scope::SUB_USER_ALLOWED)]
class RelationSnapshot extends AbstractApi
{
    private const int CHUNK = 1048576;
    private const string PARQUET = 'application/vnd.apache.parquet';

    private SnapshotModel $snapshot;
    private SnapshotAuthorizer $authorizer;
    private string $schemaName;
    private string $relationName;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->snapshot = new SnapshotModel($connection);
        $this->authorizer = new SnapshotAuthorizer($connection);
        $this->resource = 'snapshot';
    }

    /**
     * Route params are only available after Route2 has matched the request
     * (the dispatcher constructs every route candidate up front, before
     * matching), so they cannot be read in the constructor; validate() runs
     * first for every dispatched request, so initializing here guarantees
     * $schemaName/$relationName are set before any action method runs.
     */
    private function initParams(): void
    {
        $this->schemaName = (string)$this->route->getParam('schema');
        $this->relationName = (string)$this->route->getParam('relation');
    }

    private function base(): string
    {
        return "/api/v4/schemas/{$this->schemaName}/relations/{$this->relationName}/snapshots";
    }

    /** @return array<int, array{name:string, size_bytes:int}> */
    private function filesOf(array $row): array
    {
        return is_string($row['files'] ?? null) ? (json_decode($row['files'], true) ?: []) : [];
    }

    private function present(array $row, bool $full): array
    {
        $date = $row['snapshot_date'];
        $out = [
            'snapshot_date' => $date,
            'snapshot_id' => $row['uuid'],
            'row_count' => $row['row_count'] !== null ? (int)$row['row_count'] : null,
            'size_bytes' => $row['size_bytes'] !== null ? (int)$row['size_bytes'] : null,
            'schema_version' => $row['schema_version'],
            'files' => array_map(fn($f) => [
                'name' => $f['name'],
                'size_bytes' => (int)$f['size_bytes'],
                'href' => $this->base() . "/$date/files/{$f['name']}",
            ], $this->filesOf($row)),
            'published' => $row['published'],
        ];
        if ($full) {
            $out['srs'] = $row['srs'] !== null ? (int)$row['srs'] : null;
            $out['relation_schema'] = $row['relation_schema'] !== null ? json_decode($row['relation_schema'], true) : null;
            $out['crs'] = $this->crsOf($row);
            $out['_links'] = ['data' => $this->base() . "/$date/data", 'files' => $out['files']];
        }
        return $out;
    }

    /** The CRS as written to metadata.json: requested srs, else the native SRID stored by the worker in metadata. */
    private function crsOf(array $row): ?string
    {
        if ($row['srs'] !== null) {
            return "EPSG:" . (int)$row['srs'];
        }
        // Native SRID is only in metadata-<id>.json; read it once (small file).
        try {
            $ref = $this->refOf($row);
            $meta = json_decode(stream_get_contents($this->storage()->readStream($ref, $ref->metadataFile())), true);
            return $meta['crs'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function refOf(array $row): SnapshotRef
    {
        return new SnapshotRef($this->connection->database, $row['schema_name'], $row['relation_name'], $row['snapshot_date'], $row['uuid']);
    }

    private ?SnapshotStorage $storageInstance = null;

    private function storage(): SnapshotStorage
    {
        return $this->storageInstance ??= SnapshotStorageFactory::fromApp();
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}', operationId: 'getRelationSnapshot', description: "Get one published snapshot (metadata), or list them when {date} is omitted.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', description: 'Snapshot date YYYY-MM-DD', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/RelationSnapshot")),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'No published snapshot for that date'),
        ]
    )]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $this->authorizer->assertCanRead($this->route->jwt['data'], $this->schemaName, $this->relationName);
        $date = $this->route->getParam('date');
        if (!empty($date)) {
            $row = $this->snapshot->getPublished($this->schemaName, $this->relationName, $date)['data'];
            return new GetResponse(data: $this->present($row, true));
        }
        $rows = $this->snapshot->listPublished($this->schemaName, $this->relationName);
        return new GetResponse(data: ['snapshots' => array_map(fn($r) => $this->present($r, false), $rows)]);
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}/data', operationId: 'getRelationSnapshotData', description: "The snapshot's Parquet file. Supports HEAD and single byte ranges (206/416). In redirect mode answers 302 to a short-lived storage URL.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'Range', in: 'header', required: false, schema: new OA\Schema(type: 'string'), example: 'bytes=0-1023'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Whole file', content: new OA\MediaType(mediaType: 'application/vnd.apache.parquet')),
            new OA\Response(response: 206, description: 'Partial content'),
            new OA\Response(response: 302, description: 'Redirect to a presigned URL (redirect mode)'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'No published snapshot for that date'),
            new OA\Response(response: 409, description: 'Snapshot has several files; use /files/{file}'),
            new OA\Response(response: 416, description: 'Range not satisfiable'),
        ]
    )]
    public function get_data(): Response
    {
        return $this->serve($this->dataFile(), false);
    }

    public function head_data(): Response
    {
        return $this->serve($this->dataFile(), true);
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}/files/{file}', operationId: 'getRelationSnapshotFile', description: "One file of the snapshot by catalog name (data-<id>.parquet, metadata-<id>.json). Same HEAD/Range semantics as /data.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File'),
            new OA\Response(response: 206, description: 'Partial content'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'Unknown snapshot or file'),
            new OA\Response(response: 416, description: 'Range not satisfiable'),
        ]
    )]
    public function get_files(): Response
    {
        return $this->serve($this->namedFile(), false);
    }

    public function head_files(): Response
    {
        return $this->serve($this->namedFile(), true);
    }

    /** @return array{row:array, file:array{name:string,size_bytes:int}} */
    private function dataFile(): array
    {
        $row = $this->authorizedRow();
        $data = array_values(array_filter($this->filesOf($row), fn($f) => str_ends_with($f['name'], '.parquet')));
        if (count($data) !== 1) {
            throw new GC2Exception("Snapshot has " . count($data) . " data files; fetch them via /files/{file}", 409, null, "MULTI_FILE_SNAPSHOT");
        }
        return ['row' => $row, 'file' => $data[0]];
    }

    /** @return array{row:array, file:array{name:string,size_bytes:int}} */
    private function namedFile(): array
    {
        $row = $this->authorizedRow();
        $name = (string)$this->route->getParam('file');
        $ref = $this->refOf($row);
        $candidates = $this->filesOf($row);
        // metadata-<id>.json is not in the files list (it is not data) but is addressable.
        $candidates[] = ['name' => $ref->metadataFile(), 'size_bytes' => 0];
        foreach ($candidates as $f) {
            if ($f['name'] === $name) {
                if ($f['size_bytes'] === 0) {
                    $f['size_bytes'] = $this->storage()->size($ref, $name);
                }
                return ['row' => $row, 'file' => $f];
            }
        }
        throw new GC2Exception("No file $name in this snapshot", 404, null, "NO_SNAPSHOT_ERROR");
    }

    private function authorizedRow(): array
    {
        $this->authorizer->assertCanRead($this->route->jwt['data'], $this->schemaName, $this->relationName);
        return $this->snapshot->getPublished($this->schemaName, $this->relationName, (string)$this->route->getParam('date'))['data'];
    }

    /**
     * Streams (or redirects to) one file with HEAD/Range semantics. Sizes come
     * from the catalog so the storage backend is hit exactly once per request.
     *
     * @param array{row:array, file:array{name:string,size_bytes:int}} $target
     */
    private function serve(array $target, bool $headOnly): Response
    {
        $row = $target['row'];
        $name = $target['file']['name'];
        $size = (int)$target['file']['size_bytes'];
        $ref = $this->refOf($row);
        $storage = $this->storage();
        $contentType = str_ends_with($name, '.json') ? 'application/json' : self::PARQUET;

        if ((App::$param['snapshot']['download'] ?? 'proxy') === 'redirect') {
            $url = $storage->downloadUrl($ref, $name, (int)(App::$param['snapshot']['urlTtl'] ?? 300));
            if ($url !== null) {
                header('Cache-Control: no-store');
                return new RedirectResponse(location: $url);
            }
        }

        $common = [
            'Accept-Ranges' => 'bytes',
            'ETag' => '"' . $row['uuid'] . '"',
            'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T', strtotime($row['published'])),
            'Cache-Control' => 'private, max-age=0',
        ];
        try {
            $range = RangeRequest::parse($_SERVER['HTTP_RANGE'] ?? null, $size);
        } catch (RangeNotSatisfiable $e) {
            return new StreamedResponse($contentType, fn() => null, 416, $common + ['Content-Range' => "bytes */{$e->size}", 'Content-Length' => '0']);
        }

        if ($range === null) {
            $headers = $common + ['Content-Length' => (string)$size];
            $callback = $headOnly ? fn() => null : function () use ($storage, $ref, $name, $size) {
                $this->pump($storage->readStream($ref, $name), $size);
            };
            return new StreamedResponse($contentType, $callback, 200, $headers);
        }

        $headers = $common + ['Content-Range' => $range->contentRange($size), 'Content-Length' => (string)$range->length()];
        $callback = $headOnly ? fn() => null : function () use ($storage, $ref, $name, $range) {
            $this->pump($storage->readRange($ref, $name, $range->start, $range->length()), $range->length());
        };
        return new StreamedResponse($contentType, $callback, 206, $headers);
    }

    /**
     * Copies at most $length bytes from $stream to the client in 1 MiB chunks
     * with output buffering and compression disabled, so Content-Length stays
     * truthful and memory use stays flat for multi-gigabyte files.
     *
     * @param resource $stream
     */
    private function pump($stream, int $length): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', '1');
        }
        @ini_set('zlib.output_compression', '0');
        try {
            $left = $length;
            while ($left > 0 && !feof($stream)) {
                $chunk = fread($stream, min(self::CHUNK, $left));
                if ($chunk === false || $chunk === '') {
                    break;
                }
                echo $chunk;
                flush();
                $left -= strlen($chunk);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    #[Override]
    public function validate(): void
    {
        $this->initParams();
        // Load-bearing guard: schema/relation come from the route and are used
        // to build storage paths on backends with no ".." defence of their own.
        // The job API validated them on creation with this same regex; validate
        // again here since this controller is reached independently.
        $name = '/^[^"\/\\\\\s]+$/';
        if (!preg_match($name, $this->schemaName) || !preg_match($name, $this->relationName)) {
            throw new GC2Exception("Invalid schema or relation name", 400, null, "INVALID_REQUEST");
        }
        $method = Input::getMethod();
        $action = $this->route->action;
        $date = $this->route->getParam('date');
        if (!in_array($method, ['get', 'head'], true)) {
            return;
        }
        if (!empty($date) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$date)) {
            throw new GC2Exception("Snapshot date must be YYYY-MM-DD", 400, null, "INVALID_REQUEST");
        }
        if ($action === 'index' && !empty($this->route->getParam('file'))) {
            throw new GC2Exception("Unknown resource", 404, null, "NO_SNAPSHOT_ERROR");
        }
        if (in_array($action, ['data', 'files'], true) && empty($date)) {
            throw new GC2Exception("A snapshot date is required", 400, null, "INVALID_REQUEST");
        }
        if ($action === 'files' && (empty($this->route->getParam('file')) || preg_match('#[/\\\\]#', (string)$this->route->getParam('file')))) {
            throw new GC2Exception("A file name is required", 400, null, "INVALID_REQUEST");
        }
        if ($action === 'data' && !empty($this->route->getParam('file'))) {
            throw new GC2Exception("Unknown resource", 404, null, "NO_SNAPSHOT_ERROR");
        }
    }

    public function post_index(): Response
    {
        // Not supported: snapshots are queued via POST /api/v4/snapshots.
    }

    public function put_index(): Response
    {
    }

    public function patch_index(): Response
    {
    }

    public function delete_index(): Response
    {
    }
}
