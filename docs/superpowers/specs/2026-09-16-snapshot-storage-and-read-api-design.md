# Snapshot storage abstraction and read API

Date: 2026-09-16
Builds on: `docs/superpowers/specs/2026-09-15-snapshot-api-design.md` (queue, worker, job API) and the requirements in `geoparquet.md` from "Storage abstraction" onwards.

## Goal

Make GeoParquet snapshots readable through GC2: a storage abstraction that
hides S3 vs local disk, a catalog that lists snapshots per relation, and
authenticated HTTP endpoints that serve Parquet files with correct HEAD and
byte-range semantics so DuckDB can `read_parquet()` straight from GC2.

## Non-goals

- Anonymous access. Every read requires a JWT; the public OGC route may get
  snapshot links later.
- Multi-file export. The worker still writes one Parquet file per snapshot, but
  the catalog, storage and API treat a snapshot as a set of files so that can
  change without an API change.
- Multipart (multi-range) responses. A multi-range request is answered with
  the whole file (RFC 9110 allows ignoring Range).
- Schema-evolution warnings. The catalog exposes `schema_version`; comparing
  snapshots is the client's job.

## Deviations from geoparquet.md

- Partition segment is `_gc2_snapshot_date=YYYY-MM-DD`, not `snapshot_date=`:
  Hive readers turn segments into columns, and the prefixed name cannot collide
  with a data column.
- The fingerprint is called `schema_version` (already in the catalog), not
  `schema_hash`.
- Relation-oriented reads live under `/api/v4/schemas/{schema}/relations/{relation}/snapshots`;
  creation stays on the existing job API `POST /api/v4/snapshots`.

## Components

```
app/inc/snapshot/
  SnapshotRef.php                 value object: database, schema, relation, snapshotDate, snapshotId
  SnapshotStorage.php             interface
  FlysystemSnapshotStorage.php    abstract base: common ops via League\Flysystem
  LocalSnapshotStorage.php        + readRange via fopen/fseek, no download URL
  S3SnapshotStorage.php           + readRange via ranged GetObject, presigned download URL
  SnapshotStorageFactory.php      fromConfig(App::$param['snapshot'])
  RangeRequest.php                parse/validate the HTTP Range header
  SnapshotAuthorizer.php          may the JWT identity read this relation's snapshots?
app/models/Snapshot.php           catalog: publish, supersede, listPublished, getPublished
app/inc/SnapshotWorker.php        writes through SnapshotStorage, publishes, supersedes
app/api/v4/controllers/RelationSnapshot.php   read API (list, metadata, data, files)
app/api/v4/Responses/StreamedResponse.php     gains headers
app/inc/Route2.php                lets HEAD reach a controller that implements head_<action>
```

Existing pieces reused: `App::$param['snapshot']` config, `settings.snapshots`,
`app\models\Snapshot`, `Route2` + `AbstractApi` conventions, JWT data
(`uid`, `superUser`, `userGroup`, `database`), `app\models\Authorization`
(`extractHighestPrivilege`, `isOwner`) and `Model::getGeometryColumns`
for the per-layer privilege JSON.

## Storage layout (unchanged from 2026-09-15, file names change)

```
{prefix}/{database}/schema={schema}/relation={relation}/_gc2_snapshot_date={YYYY-MM-DD}/
    data-{snapshot_id}.parquet
    metadata-{snapshot_id}.json
```

Files carry the snapshot id so a re-run on the same date never overwrites a
file a reader may be streaming. Readers reach files only through the catalog,
so a partially written snapshot (files without a published row) is invisible.

## Catalog

Migration (appended to `app/migration/Sql.php`):

```sql
ALTER TABLE settings.snapshots ADD COLUMN snapshot_date DATE;
ALTER TABLE settings.snapshots ADD COLUMN files JSONB;          -- [{"name":"data-<uuid>.parquet","size_bytes":123}]
ALTER TABLE settings.snapshots ADD COLUMN size_bytes BIGINT;    -- sum of data files
ALTER TABLE settings.snapshots ADD COLUMN published TIMESTAMP WITH TIME ZONE;
ALTER TABLE settings.snapshots DROP CONSTRAINT snapshots_status_check;
ALTER TABLE settings.snapshots ADD CONSTRAINT snapshots_status_check
  CHECK (status IN ('pending','running','succeeded','failed','superseded'));
CREATE UNIQUE INDEX snapshots_published_unique_idx
  ON settings.snapshots (schema_name, relation_name, snapshot_date) WHERE status = 'succeeded';
```

Rules:

- A snapshot is **visible** iff `status = 'succeeded' AND published IS NOT NULL`.
- `snapshot_date` is the UTC date the export ran (`gmdate('Y-m-d')`), set by the worker at publish.
- Re-running for the same relation and date publishes the new row and marks
  the previous `succeeded` row for that date `superseded` (its files are deleted
  afterwards, best effort). The unique index makes the swap race-free: the new
  row cannot become `succeeded` while the old one still is, so publish runs in
  one transaction: `UPDATE old SET status='superseded'; UPDATE new SET status='succeeded', published=now(), ...`.
- Failed and superseded rows stay for the job API; the read API never shows them.

Model additions (`app\models\Snapshot`):

```php
/** Marks the run succeeded and visible, superseding any earlier succeeded row for the same date. Returns the superseded uuid or null. */
public function publish(string $uuid, string $snapshotDate, string $s3Path, int $rowCount, string $schemaVersion, array $relationSchema, array $files): ?string;
/** Visible snapshots of one relation, newest date first. */
public function listPublished(string $schema, string $relation, int $limit = 100): array;
/** One visible snapshot, or throws 404 NO_SNAPSHOT_ERROR. */
public function getPublished(string $schema, string $relation, string $snapshotDate): array;
```

`finish()` keeps serving the failure path (and stays for the job API).

## Storage interface

```php
namespace app\inc\snapshot;

final readonly class SnapshotRef {
    public function __construct(public string $database, public string $schema, public string $relation, public string $snapshotDate, public string $snapshotId) {}
}

interface SnapshotStorage {
    public function exists(SnapshotRef $ref, string $file): bool;
    public function size(SnapshotRef $ref, string $file): int;
    /** @return list<array{name:string,size_bytes:int}> files in the snapshot directory */
    public function listFiles(SnapshotRef $ref): array;
    /** @return resource read stream of the whole file */
    public function readStream(SnapshotRef $ref, string $file);
    /** @return resource read stream positioned at byte $start (0-based); may hold more than $length bytes, callers copy at most $length */
    public function readRange(SnapshotRef $ref, string $file, int $start, int $length);
    /** @param resource $stream */
    public function writeStream(SnapshotRef $ref, string $file, $stream): void;
    public function write(SnapshotRef $ref, string $file, string $contents): void;
    /** Deletes one file; missing files are not an error. */
    public function delete(SnapshotRef $ref, string $file): void;
    /** Short-lived URL a client can fetch directly, or null when the backend cannot issue one. */
    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string;
    /** Storage-independent display path for the catalog, e.g. s3://bucket/prefix/... or file:///var/... */
    public function locationOf(SnapshotRef $ref): string;
}
```

`FlysystemSnapshotStorage` (abstract) implements everything except
`readRange` and `downloadUrl` on top of a `League\Flysystem\Filesystem`, and
owns the key layout:

```php
protected function key(SnapshotRef $ref, string $file = ''): string
// "{prefix}/{database}/schema={schema}/relation={relation}/_gc2_snapshot_date={date}/{file}", prefix omitted when empty
```

Contract for `readRange`: the returned stream is positioned at byte `$start`
and holds at least the requested bytes when they exist; it may hold more
(local files). Callers copy at most `$length` bytes. That keeps the local
backend a plain `fopen` + `fseek`, while S3 returns the already bounded body
of the ranged GetObject.

`LocalSnapshotStorage(string $root)`: `readRange` is `fopen` + `fseek`.
`downloadUrl` returns null. `locationOf` returns `file://{root}/{key}`.

`S3SnapshotStorage(S3Client $client, string $bucket, string $prefix, string $region)`:
`readRange` calls `getObject(['Bucket','Key','Range' => "bytes=$start-".($start+$length-1)])`
and returns the body stream (`detach()`ed PSR stream). `downloadUrl` uses
`createPresignedRequest(getObject, "+{$ttl} seconds")`. `locationOf` returns
`s3://{bucket}/{key}`. Both use `escapeshellarg`-free, key-safe names: the
controller's regex on schema/relation guarantees no `/`, `"` or whitespace.

`SnapshotStorageFactory::fromConfig(array $cfg): SnapshotStorage` reads:

```php
"snapshot" => [
    "storage"   => "s3",            // "s3" | "local"
    "bucket"    => "gc2-parquet",   // s3
    "prefix"    => "prod",          // s3 and local (sub dir under localPath)
    "region"    => "eu-west-1",     // s3
    "localPath" => "/var/lib/gc2/parquet", // local
    "download"  => "proxy",         // "proxy" | "redirect" (redirect only meaningful for s3)
    "urlTtl"    => 300,             // seconds, redirect mode
],
```

Credentials still come from `App::$param['s3']['id'|'secret']`. The factory
throws `GC2Exception` 501 `SNAPSHOT_NOT_CONFIGURED` when `storage` is s3
without bucket/credentials, or local without `localPath`.

## Worker lifecycle (changes to SnapshotWorker)

1. Claim row (unchanged), `$date = gmdate('Y-m-d')`, `$ref = new SnapshotRef(db, schema, relation, $date, $uuid)`.
2. Export with ogr2ogr to tmp (unchanged).
3. `writeStream($ref, "data-$uuid.parquet")`, then `write($ref, "metadata-$uuid.json")`. metadata.json gains `"snapshot_date"` and `"files"`.
4. `$files = [['name' => "data-$uuid.parquet", 'size_bytes' => filesize(tmp)]]`.
5. `$superseded = $this->snapshot->publish($uuid, $date, $storage->locationOf($ref), $rowCount, $schemaVersion, $columns, $files)`.
6. If `$superseded !== null`: delete that row's files (`files` column) via `delete()`, ignoring errors.
7. Failure at any step before 5: `finish(..., 'failed', ...)` as today, and delete any file written for this uuid (best effort).

The worker constructor takes `SnapshotStorage` instead of `Filesystem`,
`bucket` and `prefix`. `snapshot_worker.php` uses the factory.

## Read API

Controller `RelationSnapshot`, route
`api/v4/schemas/{schema}/relations/{relation}/snapshots/[date]/(action)/[file]`,
`Scope::SUB_USER_ALLOWED`, methods `GET, HEAD, OPTIONS`.

| Method | Path | Handler | Response |
|---|---|---|---|
| GET | `.../snapshots` | `get_index` | `{"snapshots":[{snapshot_date, snapshot_id, row_count, size_bytes, schema_version, files:[{name,size_bytes,href}], published}]}` newest first |
| GET | `.../snapshots/{date}` | `get_index` | one entry as above plus `relation_schema`, `crs`, `srs`, `_links: {data, files}` |
| GET/HEAD | `.../snapshots/{date}/data` | `get_data` / `head_data` | the single data file; 409 `MULTI_FILE_SNAPSHOT` (with the file list) when the snapshot has several |
| GET/HEAD | `.../snapshots/{date}/files/{file}` | `get_files` / `head_files` | that file |

`schema` and `relation` names must match `^[A-Za-z0-9_-]+$` (validated again
here, independently of the job API, since this controller is reached without
`doesSchemaExist()` and both are interpolated into `settings.getColumns()`'s
literal-quoted SQL by `SnapshotAuthorizer`/`Model::getGeometryColumns()`).
`date` is validated as `YYYY-MM-DD` (and a real calendar date via `checkdate`);
`file` must be one of the names in the catalog row (never a free path).
Errors: 400 `INVALID_REQUEST` (bad schema/relation/date/file), 404
`NO_SNAPSHOT_ERROR` (unknown date or file), 403 `INSUFFICIENT_PRIVILEGES`,
403 `GEOFENCE_RULES_APPLY` (a geofence rule applies to this sub-user and
relation), 409 `MULTI_FILE_SNAPSHOT` (several data files; the message names
them), 416 (see below), 501 `SNAPSHOT_NOT_CONFIGURED` (no storage configured),
502 `SNAPSHOT_STORAGE_ERROR` (the storage backend failed). The 502 message is
deliberately flat — Flysystem and the AWS SDK name the bucket, the full object
key or the local root in theirs, and that must not reach a client; the detail
goes to `error_log` instead.

File responses:

- Headers on both HEAD and GET: `Accept-Ranges: bytes`, `Content-Type: application/vnd.apache.parquet` (`application/json` for metadata files), `Content-Length`, `ETag: "<snapshot_id>"`, `Last-Modified` from `published`, `Cache-Control: private, max-age=0`.
- `Range: bytes=a-b` → 206 with `Content-Range: bytes a-b/size` and `Content-Length: b-a+1`; `bytes=a-` and `bytes=-n` per RFC 9110. `a > b`, `a >= size` or unparsable → 416 with `Content-Range: bytes */size`. A `Range` naming several ranges is ignored (200, whole file). `If-Range` is ignored.
- Sizes come from the catalog (`files[].size_bytes`), never from a storage call, so S3 costs one `GetObject` per request.
- Proxy mode streams `readRange`/`readStream` in 1 MiB chunks with `flush()` after each; output buffering is closed (`while (ob_get_level()) ob_end_clean()`), and `apache_setenv('no-gzip', '1')` when available so `Content-Length` stays truthful.
- Redirect mode (`download = redirect` and `downloadUrl()` not null): after authorization, 302 `Location: <presigned>` with `Cache-Control: no-store`. HEAD in redirect mode also 302s. Range headers are not forwarded; the client repeats them against S3.

Apache deployment note: mod_proxy_fcgi (httpd 2.4.6x+) discards the backend's `Content-Length` unless the environment variable `ap_trust_cgilike_cl` is set; the vhost sets `SetEnv ap_trust_cgilike_cl 1`. Without it HEAD has no length and GET is chunked, which breaks Parquet readers. Scoping this to just the snapshot routes (via `LocationMatch` or a `mod_rewrite` `[E=...]` flag) was attempted and did not work — the variable was confirmed present in the request's environment either way, but mod_proxy_fcgi's Content-Length trust check did not honor it from either scoped form, only from an unscoped, top-level `SetEnv` — so it is currently vhost-wide.

### Route2 and StreamedResponse changes

- `StreamedResponse` gets `public readonly array $headers = []` (name → value) emitted by Route2 before the callback, and the status is emitted as given (206 works today; nothing maps it).
- `Route2::add`: today `if ($method == "options" || $method == "head") { $listener->options(); return; }` short-circuits every HEAD before the controller. New rule: HEAD is dispatched like GET iff the controller class itself declares `head_<action>` (checked with `ReflectionMethod::getDeclaringClass()->getName() === $controller::class`); otherwise the short-circuit stays. `AbstractApi::head_index` therefore never triggers dispatch, and every existing controller keeps today's behaviour.

## Authorization

`SnapshotAuthorizer::assertCanRead(array $jwtData, string $schema, string $relation): void`
(throws `GC2Exception`):

1. `superUser` → allowed.
2. Sub-user: `Authorization::isOwner($uid, $userGroup, $schema)` → allowed.
3. Sub-user: privileges JSON from `Model::getGeometryColumns("$schema.$relation", 'privileges')`; `extractHighestPrivilege` in `read`/`read/write` → allowed; `none` → 403 `INSUFFICIENT_PRIVILEGES`. Non-spatial tables, views and materialized views are registered too (`settings.geometry_columns_view` unions `non_postgis_tables`/`views`/`matviews` with `geometry_columns`), so the same privilege row exists for every relation GC2 knows.
4. Sub-user: if **any** rule in `settings.geofence` matches the caller (or one
   of their groups) on this schema and relation → 403 `GEOFENCE_RULES_APPLY`.
   A snapshot is the whole relation in one Parquet file and cannot carry a row
   filter, so serving it to a user whose SQL/OWS reads are narrowed by a
   `limit` rule (or forbidden by a `deny` rule) would hand them exactly the
   rows the geofence exists to withhold — the snapshot route would become a
   way around the rules. Matching is not re-implemented: each rule is passed
   to `Geofence::authorize()` on its own with a `UserFilter` carrying that
   rule's own `service`/`request`, so the identity, schema, layer and IP-range
   matching (including the `fnmatch` wildcards) is byte for byte the one SQL
   and OWS use. Super-users (step 1) and schema owners (step 2) are never
   affected.
5. The check runs on the catalog row's schema/relation, so a dropped relation
   whose snapshots still exist has no privilege row any more: only super-users
   and schema owners can read its history.

The check runs before any catalog file name is resolved and before any
storage call or presigned URL. Anonymous requests never reach the controller
(`SUB_USER_ALLOWED` requires a token).

## Range handling (RangeRequest)

```php
final readonly class RangeRequest {
    public function __construct(public int $start, public int $end) {}   // inclusive
    /** null = no/ignored Range header (serve whole); throws RangeNotSatisfiable (416) */
    public static function parse(?string $header, int $size): ?self;
    public function length(): int;
    public function contentRange(int $size): string;                 // "bytes a-b/size"
}
```

Parsing: header must match `^bytes=(\d*)-(\d*)$`; exactly one of the two may be
empty; `-n` means last n bytes (clamped to size; `-0` → 416); `a-` means to
end; `b >= size` is clamped to `size-1`; `a > b` or `a >= size` → 416;
`size = 0` with any Range → 416; a header with a comma → null (ignored).

## Failure and idempotency

- Queue side unchanged: one active job per relation, stale reclaim after 2 h.
- Publish is a single transaction guarded by the unique index; two workers
  cannot both publish the same date.
- Old files of a superseded row are deleted after publish; a failure there
  leaves orphan objects but a consistent catalog (worker logs it).
- Read side is stateless; a storage error mid-stream ends the response (no
  trailer); the client sees a short body and retries.
- 501 from the factory when storage is unconfigured, both in the script and
  in the read API.

## Scheduler integration

`jobs` table (gc2scheduler database) gets `snapshot BOOL DEFAULT FALSE`. At
the end of a successful `get.php` run (in `cleanUp(1)`), when the job has
`snapshot = true`, insert a pending row via `Snapshot::create($schema, $safeName, null, $db)`
unless `hasActive()`; the cron worker does the rest. No other coupling.

## Tests

Unit (`app/tests/unit/`):

- `RangeRequestTest`: every parse rule above (table-driven).
- `LocalSnapshotStorageTest`: key layout, write/list/size/exists, `readRange`
  boundaries (start 0, last byte, `length` beyond EOF returns what exists),
  delete missing is a no-op, `downloadUrl` null.
- `S3SnapshotStorageTest`: with `Aws\MockHandler`: `readRange` sends
  `Range: bytes=a-b` and returns the body; `downloadUrl` yields a URL for the
  right bucket/key with expiry; `locationOf` format.
- `SnapshotStorageFactoryTest`: config → implementation; 501 on missing config.
- `SnapshotModelTest` additions: `publish` sets fields and supersedes; unique
  index prevents two succeeded rows on one date; `listPublished`/`getPublished`
  hide failed/superseded/unpublished rows.
- `SnapshotWorkerTest` updates: files named by uuid, metadata has
  `snapshot_date` and `files`, re-run same day supersedes and deletes the old
  file, failure leaves no published row.
- `StreamedResponseTest`: headers carried.

API (`app/tests/api/RelationSnapshotV4ApiCest.php`), with local storage
configured in the container's `App.php` under a scratch dir and the worker
run inline (`snapshot_worker.php <db>`) after queuing:

- list and metadata shapes; unknown date 404; malformed date 400.
- HEAD data: 200, `Accept-Ranges`, `Content-Length` equals file size, empty body.
- GET data: 200, body length equals size, first 4 bytes `PAR1`.
- GET with `Range: bytes=0-3` → 206, body `PAR1`, `Content-Range: bytes 0-3/size`.
- `bytes=-4` → 206, last 4 bytes `PAR1`; `bytes=999999999-` → 416.
- sub-user without privilege → 403; with `read` privilege → 200; super-user → 200.
- `files/{name}` for the metadata file → JSON; unknown name → 404.

End-to-end (manual, recorded in the plan): DuckDB `read_parquet('http://localhost:8080/api/v4/schemas/…/snapshots/<date>/data')`
with an `http` secret carrying the bearer token, in proxy mode; then the
same in redirect mode against S3.
