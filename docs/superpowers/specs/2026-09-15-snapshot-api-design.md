# Snapshot API design

Date: 2026-09-15

## Goal

Expose the parquet-to-S3 prototype in `app/models/Sql.php` as a proper, asynchronous
v4 API. A super-user POSTs a schema/relation, the server queues a job, a cron worker
exports the relation to Parquet with ogr2ogr and uploads it to S3 together with a
metadata file. The client polls a GET endpoint for the job status.

## Non-goals

- No cancellation or deletion of snapshots.
- No listing of what exists in S3. The API only reports what it has queued and done.
- No sub-user access. Super-user only in this iteration.
- No retries. A failed job stays failed with its error message; the client re-POSTs.

## Endpoints

Controller: `app/api/v4/controllers/Snapshot.php`, route `api/v4/snapshots/[id]`,
`Scope::SUPER_USER_ONLY`, methods GET, POST, HEAD, OPTIONS.

### POST /api/v4/snapshots

Body:

```json
{ "schema": "geodanmark", "relation": "bygning", "srs": 25832 }
```

- `schema` and `relation`: required strings. The relation may be a table or a view.
- `srs`: optional integer EPSG code. When absent the export keeps the relation's
  native SRID and no reprojection happens.

Validation (Symfony Assert collection, as other v4 controllers):

- 400 `INVALID_REQUEST` on missing or non-string schema/relation, non-integer srs.
- 404 `RELATION_NOT_FOUND` if `schema.relation` is not a table or view.
- 409 `SNAPSHOT_IN_PROGRESS` if a row for the same schema/relation is `pending`
  or `running`.

Response 202:

```json
{
  "id": "0f4c…",
  "status": "pending",
  "_links": { "self": "/api/v4/snapshots/0f4c…" }
}
```

### GET /api/v4/snapshots/{id}

Returns the snapshot row. 404 `NO_SNAPSHOT_ERROR` when the uuid is unknown.

```json
{
  "id": "0f4c…",
  "schema": "geodanmark",
  "relation": "bygning",
  "srs": 25832,
  "status": "succeeded",
  "s3_path": "s3://gc2-parquet/prod/mydb/schema=geodanmark/relation=bygning/harvest_date=2026-09-15/",
  "row_count": 123456,
  "error": null,
  "username": "mydb",
  "created": "2026-09-15T10:00:00+00:00",
  "started": "2026-09-15T10:00:31+00:00",
  "finished": "2026-09-15T10:02:10+00:00"
}
```

`status` is one of `pending`, `running`, `succeeded`, `failed`. `s3_path`,
`row_count`, `started`, `finished` are null until set. `error` is set only on
`failed`.

### GET /api/v4/snapshots

Lists the 50 newest rows, same shape as above, newest first. Optional query
filters `schema` and `relation` narrow the list.

## Persistence

New table created in `app/migration/Sql.php` next to `settings.function_invocations`:

```sql
CREATE TABLE settings.snapshots
(
  uuid          UUID                     NOT NULL DEFAULT uuid_generate_v4() PRIMARY KEY,
  schema_name   TEXT                     NOT NULL,
  relation_name TEXT                     NOT NULL,
  srs           INTEGER,
  status        CHARACTER VARYING(32)    NOT NULL DEFAULT 'pending',
  s3_path       TEXT,
  row_count     BIGINT,
  error         TEXT,
  username      CHARACTER VARYING(255),
  created       TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now(),
  started       TIMESTAMP WITH TIME ZONE,
  finished      TIMESTAMP WITH TIME ZONE,
  CHECK (status IN ('pending', 'running', 'succeeded', 'failed'))
);
CREATE INDEX snapshots_pending_idx ON settings.snapshots (created) WHERE status = 'pending';
CREATE INDEX snapshots_relation_idx ON settings.snapshots (schema_name, relation_name);
```

Model `app\models\Snapshot` (extends `Model`, takes a `Connection`):

- `create(string $schema, string $relation, ?int $srs, string $username): string`
  inserts a pending row and returns the uuid.
- `hasActive(string $schema, string $relation): bool` true if a pending row exists
  for the relation, or a running one whose `started` is within
  `STALE_RUNNING_INTERVAL` (2 hours). A `running` row older than that is presumed
  to belong to a dead worker and no longer counts as active.
- `claimPending(int $limit): array` flips up to `$limit` eligible rows to `running`
  with a fresh `started = now()`, using `FOR UPDATE SKIP LOCKED`, and returns them.
  Eligible rows are `pending` ones and `running` ones whose `started` is older than
  `STALE_RUNNING_INTERVAL` (`Snapshot::STALE_RUNNING_INTERVAL`, 2 hours) — a worker
  that died mid-run leaves its row stuck in `running`, and after 2 hours it is
  reclaimed by the next worker run.
- `finish(string $uuid, string $status, ?string $s3Path, ?int $rowCount, ?string $error): void`
  sets the final status and `finished = now()`.
- `get(string $uuid): array` throws `GC2Exception` 404 `NO_SNAPSHOT_ERROR` when missing.
- `list(?string $schema, ?string $relation, int $limit = 50): array` newest first.

## Worker

`app\inc\SnapshotWorker`, one instance per database, mirrors `FunctionWorker`:

```php
public function __construct(Connection $connection, Filesystem $filesystem, string $s3Prefix, string $bucket)
public function processPending(int $limit = 5): array  // ['processed'=>, 'succeeded'=>, 'failed'=>]
```

`Filesystem` is `League\Flysystem\Filesystem`. Production wires an `AwsS3V3Adapter`;
tests wire a `LocalFilesystemAdapter` rooted in a scratch directory. The worker
never constructs the adapter itself.

Per claimed row, `runOne`:

1. Resolve the relation with `Model::isTableOrView`. If it does not exist, finish
   as `failed` with a clear message (the relation may have been dropped after the
   POST).
2. Read column metadata with `Model::getMetaData("$schema.$relation", false, true, null, null, false)`
   for `schema_version`, and the geometry column's native SRID for the `crs` value
   when `srs` is null.
3. Run ogr2ogr:

   ```
   ogr2ogr -mapFieldType Time=String,Binary=String -f Parquet <tmp>/<uuid>.parquet
           [-t_srs EPSG:<srs>] -preserve_fid
           PG:'host=… port=… user=… password=… dbname=…'
           -sql "SELECT * FROM \"<schema>\".\"<relation>\""
   ```

   `<tmp>` is `App::$param['path'] . "app/tmp/<database>/__snapshots/"`, created if
   missing. Any line containing `ERROR` in the output fails the job with that output
   as the error.
4. Count rows with `Model::countRows`.
5. Build the partition key
   `<prefix>/<database>/schema=<schema>/relation=<relation>/harvest_date=<YYYY-MM-DD>/`
   (prefix omitted when empty) and write `data.parquet` from the tmp file and
   `metadata.json`:

   ```json
   {
     "snapshot_id": "<uuid>",
     "harvested_at": "2026-09-15T10:02:10Z",
     "database": "mydb",
     "source": "geodanmark.bygning",
     "row_count": 123456,
     "schema_version": "<md5 of serialised column metadata>",
     "crs": "EPSG:25832"
   }
   ```

   The parquet upload streams from the tmp file (`writeStream`) so large exports
   do not load into memory.
6. Finish as `succeeded` with `s3_path = "s3://<bucket>/<partition>"` and the row
   count. Any `Throwable` finishes the row as `failed` with the exception message.
7. Delete the tmp file in a `finally`.

A snapshot taken twice on the same day overwrites the previous files in that
partition. That is the intended "latest per day" semantics.

### Script and cron

`app/scripts/snapshot_worker.php` follows `function_worker.php`: bootstraps `App`
and `Cache`, iterates `Database::listAllDbs()` (skipping the system databases),
constructs the S3 `Filesystem` once, runs `SnapshotWorker::processPending` per
database, prints a summary, and swallows per-database errors. An optional first
argument limits the run to one database.

Batch size comes from env `GC2_SNAPSHOT_BATCH` (default 2). Exports are
ogr2ogr-heavy, so the default is deliberately small.

Dockerfile gets one more crontab line next to the function worker:

```
* * * * * sudo -u www-data php -f /var/www/geocloud2/app/scripts/snapshot_worker.php > /proc/1/fd/1 2>&1
```

No database argument is passed. Note that the existing `function_worker.php 1`
line passes `1` as a database filter; that line is left untouched here.

## Configuration

New block in `App.php`. The tracked template is `docker/conf/gc2/App.php`; the local `app/conf/App.php` is gitignored and is updated by hand:

```php
"snapshot" => [
    "bucket" => "gc2-parquet",
    "prefix" => "prod",          // may be ""
    "region" => "eu-west-1",
],
```

Credentials are read from the existing `App::$param['s3']['id']` and `['secret']`.
When `snapshot.bucket` is unset the worker script logs and exits, and the POST
endpoint returns 501 `SNAPSHOT_NOT_CONFIGURED` so the client does not queue
work that can never run.

## Changes to Sql.php

The prototype block is removed: the S3 upload, the `S3_FOLDER` constant, the
hard-coded schema/relation, and the now-unused imports. `ogr/Parquet` stays in
`NO_ZIP_FORMATS` and is returned as a single-file download with
`Content-type: application/octet-stream` (GPX keeps its existing header).

## Error handling summary

| Situation | Where | Result |
|---|---|---|
| Missing/invalid body fields | POST | 400 `INVALID_REQUEST` |
| Relation missing at POST | POST | 404 `RELATION_NOT_FOUND` |
| Active job for relation | POST | 409 `SNAPSHOT_IN_PROGRESS` |
| S3 not configured | POST | 501 `SNAPSHOT_NOT_CONFIGURED` |
| Unknown id | GET | 404 `NO_SNAPSHOT_ERROR` |
| Relation dropped before run | worker | row `failed`, error text |
| ogr2ogr error | worker | row `failed`, ogr2ogr output as error |
| S3 write failure | worker | row `failed`, exception message |
| Worker died mid-run (row stuck in running) | model | after 2 h the row counts as inactive: POST is allowed again and the worker reclaims it |

## Testing

- `app/tests/api/SnapshotV4ApiCest.php`: super-user creates a table, POSTs a
  snapshot (202, uuid, pending), GETs it by id (same row), lists with filter,
  POSTs again (409), POSTs unknown relation (404), POSTs bad body (400), sub-user
  POST is rejected. Tests do not wait for the worker.
- `app/tests/unit/SnapshotWorkerTest.php`: creates a small PostGIS table, inserts
  a pending row through the model, runs `SnapshotWorker` with a
  `LocalFilesystemAdapter` rooted in a scratch directory, asserts `data.parquet`
  and `metadata.json` exist under the expected partition path, the metadata JSON
  has the right `source`, `row_count` and `crs`, the row is `succeeded` with the
  count, and the tmp file is gone. A second test drops the table after inserting
  the row and asserts the row ends `failed` with a non-empty error.
- Tests run inside the dev container (see project memory on running tests in
  Docker), where ogr2ogr is available.

## Files

- `app/api/v4/controllers/Snapshot.php` (new)
- `app/models/Snapshot.php` (new)
- `app/inc/SnapshotWorker.php` (new)
- `app/scripts/snapshot_worker.php` (new)
- `app/migration/Sql.php` (table + indexes)
- `docker/conf/gc2/App.php` (snapshot block; mirror it into the local gitignored `app/conf/App.php`)
- `docker/Dockerfile` (cron line)
- `app/models/Sql.php` (prototype removal)
- `app/tests/api/SnapshotV4ApiCest.php`, `app/tests/unit/SnapshotWorkerTest.php` (new)
