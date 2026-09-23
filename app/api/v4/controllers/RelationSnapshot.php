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
use app\inc\snapshot\SnapshotFormat;
use app\inc\snapshot\SnapshotRef;
use app\inc\snapshot\SnapshotStorage;
use app\inc\snapshot\SnapshotStorageFactory;
use app\models\Snapshot as SnapshotModel;
use Aws\Exception\AwsException;
use League\Flysystem\FilesystemException;
use League\Flysystem\UnableToReadFile;
use RuntimeException;
use OpenApi\Annotations\OpenApi;
use OpenApi\Attributes as OA;
use Override;

/**
 * Read API for published snapshots of a relation: list, metadata, and the
 * files themselves with HEAD + byte-range support so Parquet readers (DuckDB
 * read_parquet) can fetch footers and row groups selectively.
 *
 * A snapshot holds one data file per produced output format (SnapshotFormat).
 * `/data` serves the Parquet, as it always has; `/data/{format}` serves a
 * named one.
 *
 * Authorization runs before any catalog file name or storage call: super
 * users always, sub-users by schema ownership or layer read privilege, and
 * never a sub-user the geofence filters (a whole-table file cannot honour a
 * row filter). Storage failures answer 502 without naming bucket or key.
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
        new OA\Property(property: "formats", description: "One entry per requested output format; a produced one carries its `href` under /data/{format}.", type: "array", items: new OA\Items(ref: "#/components/schemas/SnapshotFormatResult")),
        new OA\Property(property: "published", type: "string", format: "date-time"),
    ],
    type: "object"
)]
#[AcceptableMethods(['GET', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/schemas/{schema}/relations/{relation}/snapshots/[date]/(action)/[file]', scope: Scope::SUB_USER_ALLOWED)]
class RelationSnapshot extends AbstractApi
{
    private const int CHUNK = 1048576;

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

    /** Reserved {date} value: the newest published snapshot, a fixed URL clients can keep. */
    private const string LATEST = 'latest';

    private function isLatest(): bool
    {
        return (string)$this->route->getParam('date') === self::LATEST;
    }

    /** The published row the {date} segment names, "latest" resolving to the newest. */
    private function rowForDate(): array
    {
        return $this->isLatest()
            ? $this->snapshot->getLatestPublished($this->schemaName, $this->relationName)['data']
            : $this->snapshot->getPublished($this->schemaName, $this->relationName, (string)$this->route->getParam('date'))['data'];
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
            // Every format the snapshot was asked for and what became of it; a
            // produced one says where to download it, a skipped one why there
            // is nothing to download.
            'formats' => array_map(function (array $f) use ($date) {
                if (($f['status'] ?? null) === 'produced') {
                    $f['href'] = $this->base() . "/$date/data/{$f['format']}";
                }
                return $f;
            }, SnapshotModel::presentFormats($row)),
            'published' => $row['published'],
        ];
        if ($full) {
            $out['srs'] = $row['srs'] !== null ? (int)$row['srs'] : null;
            $out['relation_schema'] = $row['relation_schema'] !== null ? json_decode($row['relation_schema'], true) : null;
            $out['crs'] = $this->crsOf($row);
            $out['_links'] = ['files' => $out['files']];
            // _links.data is the Parquet, as it has always been; a snapshot
            // without one (every Parquet-less format list) simply has no such
            // link, and its formats carry the hrefs instead.
            if ($this->producedFormat($out['formats'], 'parquet') !== null) {
                $out['_links'] = ['data' => $this->base() . "/$date/data"] + $out['_links'];
            }
            // The fixed URL of whatever is newest; the dated hrefs above pin this snapshot.
            $out['_links']['latest'] = $this->base() . '/' . self::LATEST;
        }
        return $out;
    }

    /**
     * One produced entry of a presented formats list, or null when that format
     * was skipped or never requested.
     *
     * @param array<int, array<string, mixed>> $formats
     * @return array<string, mixed>|null
     */
    private function producedFormat(array $formats, string $id): ?array
    {
        foreach ($formats as $f) {
            if (($f['status'] ?? null) === 'produced' && ($f['format'] ?? null) === $id) {
                return $f;
            }
        }
        return null;
    }

    /** The CRS as written to metadata.json: requested srs, else the native SRID stored by the worker in metadata. */
    private function crsOf(array $row): ?string
    {
        if ($row['srs'] !== null) {
            return "EPSG:" . (int)$row['srs'];
        }
        // Native SRID is only in metadata-<id>.json; read it once (small file).
        // Only a *missing metadata file* means "no CRS" (null); a misconfigured
        // backend (GC2Exception 501) and a broken one (502, via withStorage)
        // are real errors and must propagate, not be swallowed as "no CRS".
        $stream = null;
        try {
            $ref = $this->refOf($row);
            $stream = $this->withStorage(function () use ($ref) {
                try {
                    return $this->storage()->readStream($ref, $ref->metadataFile());
                } catch (UnableToReadFile) {
                    // The metadata file is simply not there (an older snapshot,
                    // or it was pruned): no CRS to report, not an outage.
                    return null;
                }
            });
            if ($stream === null) {
                return null;
            }
            $meta = json_decode(stream_get_contents($stream), true);
            return $meta['crs'] ?? null;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
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

    /**
     * Runs one storage operation and turns a backend failure into a flat 502.
     *
     * Flysystem and the AWS SDK put the bucket, the full object key or the
     * local root into their messages, and GC2's error handler renders the
     * message to the client: a storage outage must not become a map of where
     * the snapshots live. The detail goes to the error log instead.
     * GC2Exception (e.g. 501 SNAPSHOT_NOT_CONFIGURED from the factory) is
     * deliberately not caught and keeps its own status.
     *
     * @template T
     * @param callable(): T $op
     * @return T
     * @throws GC2Exception 502 SNAPSHOT_STORAGE_ERROR
     */
    private function withStorage(callable $op): mixed
    {
        try {
            return $op();
        } catch (FilesystemException | AwsException | RuntimeException $e) {
            error_log('snapshot storage error: ' . $e->getMessage());
            throw new GC2Exception("Snapshot storage unavailable", 502, null, "SNAPSHOT_STORAGE_ERROR");
        }
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots', operationId: 'getRelationSnapshots', description: "List the published snapshots of a relation, newest first. Returns a bare array like every other v4 collection.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(type: 'array', items: new OA\Items(ref: "#/components/schemas/RelationSnapshot"))),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
        ]
    )]
    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}', operationId: 'getRelationSnapshot', description: "Get one published snapshot (metadata).", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', description: 'Snapshot date YYYY-MM-DD, or "latest" for the newest published snapshot (a fixed URL; the response still carries the real snapshot_date)', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'latest'),
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
            return new GetResponse(data: $this->present($this->rowForDate(), true));
        }
        $rows = $this->snapshot->listPublished($this->schemaName, $this->relationName);
        return new GetResponse(data: array_map(fn($r) => $this->present($r, false), $rows));
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}/data', operationId: 'getRelationSnapshotData', description: "The snapshot's primary data file: the Parquet when produced, else the only produced file; 409 when several formats were produced and none is Parquet (use /data/{format}). Supports HEAD and single byte ranges (206/416). In redirect mode answers 302 to a short-lived storage URL.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', description: 'Snapshot date YYYY-MM-DD, or "latest" for the newest published snapshot', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'latest'),
            new OA\Parameter(name: 'Range', in: 'header', required: false, schema: new OA\Schema(type: 'string'), example: 'bytes=0-1023'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Whole file, in the media type of the format served (application/vnd.apache.parquet for Parquet)'),
            new OA\Response(response: 206, description: 'Partial content'),
            new OA\Response(response: 302, description: 'Redirect to a presigned URL (redirect mode)'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'No published snapshot for that date'),
            new OA\Response(response: 409, description: 'Several formats produced and none is Parquet; the message lists /data/{format} for each'),
            new OA\Response(response: 416, description: 'Range not satisfiable'),
            new OA\Response(response: 502, description: 'Snapshot storage unavailable'),
        ]
    )]
    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}/data/{format}', operationId: 'getRelationSnapshotDataFormat', description: "The snapshot's data file in one named output format. Same HEAD/Range/redirect semantics as /data. 400 for an unknown format id, 404 when the snapshot did not produce that format (it was skipped or never requested).", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', description: 'Snapshot date YYYY-MM-DD, or "latest" for the newest published snapshot', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'latest'),
            new OA\Parameter(name: 'format', description: 'Output format id', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: SnapshotFormat::IDS), example: 'flatgeobuf'),
            new OA\Parameter(name: 'Range', in: 'header', required: false, schema: new OA\Schema(type: 'string'), example: 'bytes=0-1023'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Whole file'),
            new OA\Response(response: 206, description: 'Partial content'),
            new OA\Response(response: 302, description: 'Redirect to a presigned URL (redirect mode)'),
            new OA\Response(response: 400, description: 'Unknown format id'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'No published snapshot for that date, or that format was not produced'),
            new OA\Response(response: 416, description: 'Range not satisfiable'),
            new OA\Response(response: 502, description: 'Snapshot storage unavailable'),
        ]
    )]
    public function get_data(): Response
    {
        return $this->serve($this->dataFileFor($this->requestedFormat()), false);
    }

    public function head_data(): Response
    {
        return $this->serve($this->dataFileFor($this->requestedFormat()), true);
    }

    /**
     * The format id in the `[file]` segment of the data route, or null for a
     * bare `/data`. validate() has already checked its shape.
     */
    private function requestedFormat(): ?string
    {
        $format = (string)$this->route->getParam('file');
        return $format === '' ? null : $format;
    }

    #[OA\Get(path: '/api/v4/schemas/{schema}/relations/{relation}/snapshots/{date}/files/{file}', operationId: 'getRelationSnapshotFile', description: "One file of the snapshot by catalog name (data-<id>.parquet, metadata-<id>.json). Same HEAD/Range semantics as /data.", tags: ['Snapshots'],
        parameters: [
            new OA\Parameter(name: 'schema', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'relation', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'date', description: 'Snapshot date YYYY-MM-DD, or "latest" for the newest published snapshot', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'latest'),
            new OA\Parameter(name: 'file', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'File'),
            new OA\Response(response: 206, description: 'Partial content'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'Unknown snapshot or file'),
            new OA\Response(response: 416, description: 'Range not satisfiable'),
            new OA\Response(response: 502, description: 'Snapshot storage unavailable'),
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

    /**
     * CORS preflight for the file routes. Route2 only lets AcceptableMethods
     * answer OPTIONS (204 + CORS headers) when the controller declares
     * options_<action>; without these stubs a preflight on /data or /files
     * is a 404 and browsers never send the real GET.
     */
    public function options_data(): void
    {
    }

    public function options_files(): void
    {
    }

    /**
     * The data file to serve: the one of $format, or — for a bare `/data` —
     * the Parquet a client has always got there.
     *
     * @param string|null $format A format id, or null for `/data`.
     * @return array{row:array, file:array{name:string,size_bytes:int}}
     * @throws GC2Exception 400 INVALID_REQUEST unknown format id,
     *     404 NO_SNAPSHOT_ERROR the snapshot has no such file,
     *     409 MULTI_FILE_SNAPSHOT `/data` is ambiguous
     */
    private function dataFileFor(?string $format): array
    {
        if ($format !== null && !SnapshotFormat::has($format)) {
            throw new GC2Exception("Unknown snapshot format '$format'; known formats are " . implode(', ', SnapshotFormat::ids()), 400, null, "INVALID_REQUEST");
        }
        $row = $this->authorizedRow();
        $produced = array_values(array_filter(SnapshotModel::presentFormats($row), fn(array $f) => ($f['status'] ?? null) === 'produced'));
        if ($format !== null) {
            $entry = $this->producedFormat($produced, $format);
            if ($entry === null) {
                throw new GC2Exception("This snapshot has no $format file", 404, null, "NO_SNAPSHOT_ERROR");
            }
            return ['row' => $row, 'file' => ['name' => $entry['file'], 'size_bytes' => (int)$entry['size_bytes']]];
        }
        if ($produced === []) {
            throw new GC2Exception("Snapshot has no data file", 404, null, "NO_SNAPSHOT_ERROR");
        }
        // Bare /data: the Parquet if there is one (unchanged for every client
        // written before formats were selectable), else the only file there is.
        $entry = $this->producedFormat($produced, 'parquet') ?? (count($produced) === 1 ? $produced[0] : null);
        if ($entry === null) {
            // GC2Exception carries no data payload, so the hrefs go in the
            // message: a client that gets 409 must know what to fetch instead.
            $hrefs = array_map(fn(array $f) => "/data/{$f['format']}", $produced);
            throw new GC2Exception("Snapshot has " . count($produced) . " data files and no Parquet; fetch one by format: " . implode(', ', $hrefs), 409, null, "MULTI_FILE_SNAPSHOT");
        }
        return ['row' => $row, 'file' => ['name' => $entry['file'], 'size_bytes' => (int)$entry['size_bytes']]];
    }

    /** @return array{row:array, file:array{name:string,size_bytes:int}} */
    private function namedFile(): array
    {
        $row = $this->authorizedRow();
        $name = (string)$this->route->getParam('file');
        $ref = $this->refOf($row);
        $candidates = $this->filesOf($row);
        // metadata-<id>.json is not in the files list (it is not data) but is
        // addressable; null marks "size unknown, ask storage" — 0 would be a
        // legitimate (if odd) catalog size and would trigger a pointless call.
        $candidates[] = ['name' => $ref->metadataFile(), 'size_bytes' => null];
        foreach ($candidates as $f) {
            if ($f['name'] === $name) {
                if ($f['size_bytes'] === null) {
                    $f['size_bytes'] = $this->withStorage(fn() => $this->storage()->size($ref, $name));
                }
                return ['row' => $row, 'file' => $f];
            }
        }
        throw new GC2Exception("No file $name in this snapshot", 404, null, "NO_SNAPSHOT_ERROR");
    }

    private function authorizedRow(): array
    {
        $this->authorizer->assertCanRead($this->route->jwt['data'], $this->schemaName, $this->relationName);
        return $this->rowForDate();
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
        // metadata-<id>.json is the one file that belongs to no format; every
        // other name is a format's data file, and the registry owns its media
        // type. Anything the registry does not know is served as bytes.
        $contentType = str_ends_with($name, '.json')
            ? 'application/json'
            : (SnapshotFormat::fromExtension($name)?->mediaType ?? 'application/octet-stream');

        if ((App::$param['snapshot']['download'] ?? 'proxy') === 'redirect') {
            $url = $this->withStorage(fn() => $storage->downloadUrl($ref, $name, (int)(App::$param['snapshot']['urlTtl'] ?? 300)));
            if ($url !== null) {
                header('Cache-Control: no-store');
                return new RedirectResponse(location: $url);
            }
        }

        $common = [
            'Accept-Ranges' => 'bytes',
            'ETag' => '"' . $row['uuid'] . '"',
            'Last-Modified' => gmdate('D, d M Y H:i:s \G\M\T', strtotime($row['published'])),
            // /latest points at a different snapshot after every publish: make
            // caches revalidate rather than serve yesterday's file.
            'Cache-Control' => $this->isLatest() ? 'private, no-cache' : 'private, max-age=0',
        ];
        try {
            $range = RangeRequest::parse($_SERVER['HTTP_RANGE'] ?? null, $size);
        } catch (RangeNotSatisfiable $e) {
            return new StreamedResponse($contentType, fn() => null, 416, $common + ['Content-Range' => "bytes */{$e->size}", 'Content-Length' => '0']);
        }

        // Open the stream (or not, for HEAD) before returning the response so a
        // storage-layer failure (S3 error, missing object) throws here and is
        // handled as a normal GC2 error response (502 via withStorage), rather
        // than surfacing inside the StreamedResponse callback after status and
        // headers were emitted, which would append error JSON to a binary body.
        if ($range === null) {
            $headers = $common + ['Content-Length' => (string)$size];
            $stream = $headOnly ? null : $this->withStorage(fn() => $storage->readStream($ref, $name));
            $callback = $headOnly ? fn() => null : function () use ($stream, $size) {
                $this->pump($stream, $size);
            };
            return new StreamedResponse($contentType, $callback, 200, $headers);
        }

        $headers = $common + ['Content-Range' => $range->contentRange($size), 'Content-Length' => (string)$range->length()];
        $stream = $headOnly ? null : $this->withStorage(fn() => $storage->readRange($ref, $name, $range->start, $range->length()));
        $callback = $headOnly ? fn() => null : function () use ($stream, $range) {
            $this->pump($stream, $range->length());
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
            if (!@ob_end_clean()) {
                break;
            }
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
        } catch (\Throwable $e) {
            // Status and headers are already on the wire at this point; a
            // mid-stream failure (e.g. the S3 connection drops) can only end
            // the body short, not turn into a clean error response.
            error_log('snapshot stream aborted: ' . $e->getMessage());
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
        // Load-bearing guard: schema/relation come from the route and are
        // interpolated into SQL by Model::getColumns() (via getGeometryColumns()
        // in SnapshotAuthorizer) and into storage paths on backends with no ".."
        // defence of their own. A positive character class is required — the
        // previous negated-class regex allowed "'", which is a SQL injection
        // vector into settings.getColumns('f_table_schema = ''$schema'' ...').
        // The job API's Assert\Regex uses the same pattern so both controllers agree.
        $name = '/^[A-Za-z0-9_\-]+$/';
        if (!preg_match($name, $this->schemaName) || !preg_match($name, $this->relationName)) {
            throw new GC2Exception("Invalid schema or relation name", 400, null, "INVALID_REQUEST");
        }
        $method = Input::getMethod();
        $action = $this->route->action;
        $date = $this->route->getParam('date');
        if (!in_array($method, ['get', 'head'], true)) {
            return;
        }
        if (!empty($date) && (string)$date !== self::LATEST) {
            if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string)$date, $m)) {
                throw new GC2Exception("Snapshot date must be YYYY-MM-DD or latest", 400, null, "INVALID_REQUEST");
            }
            if (!checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
                throw new GC2Exception("Snapshot date must be YYYY-MM-DD", 400, null, "INVALID_REQUEST");
            }
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
        // /data/{format}: a format id, checked against the registry in
        // dataFileFor(). The shape is checked here so nothing odd reaches a
        // lookup, and a caller gets one answer (400) for "that is not a format
        // id" whether it is misspelled or miscased.
        if ($action === 'data' && !empty($this->route->getParam('file')) && !preg_match('/^[a-z0-9]+$/', (string)$this->route->getParam('file'))) {
            throw new GC2Exception("A snapshot format id is " . implode(' or ', SnapshotFormat::ids()), 400, null, "INVALID_REQUEST");
        }
    }

    /** Not supported here: snapshots are queued via POST /api/v4/snapshots. */
    public function post_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function delete_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }
}
