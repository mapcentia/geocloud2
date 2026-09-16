# Snapshot Storage Abstraction and Read API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Serve GeoParquet snapshots through authenticated v4 endpoints with correct HEAD and byte-range semantics, behind a storage abstraction that hides S3 vs local disk.

**Architecture:** A `SnapshotStorage` interface (Flysystem-backed base, S3 and local implementations, factory from config) replaces the raw Flysystem use in the worker. The catalog (`settings.snapshots`) gains `snapshot_date`, `files`, `size_bytes`, `published` and a `superseded` status with a unique index per relation/date. A new controller under `/api/v4/schemas/{schema}/relations/{relation}/snapshots` lists snapshots and streams files (proxy or presigned redirect) after a privilege check. `Route2` learns to dispatch HEAD to controllers that implement it, and `StreamedResponse` carries headers.

**Tech Stack:** PHP 8.4, PostgreSQL/PostGIS, `league/flysystem` 3.30 (+ `flysystem-aws-s3-v3`, `flysystem-local`), `aws/aws-sdk-php` 3.349 (`Aws\MockHandler` for tests), ogr2ogr, Codeception unit + api suites.

**Spec:** `docs/superpowers/specs/2026-09-16-snapshot-storage-and-read-api-design.md` (builds on `docs/superpowers/specs/2026-09-15-snapshot-api-design.md`).

## Global Constraints

- All tests run inside the dev container `docker-dev-1`, repo mounted at `/var/www/geocloud2`. Unit: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit <File>`. API: `... run api <File>` (ordered Cests: always the whole file). Run suites as separate foreground commands, never in a shell `for` loop.
- Migrations are appended to `app/migration/Sql.php` `get()`; apply to the dev stack with `psql` against `template_geocloud` and `mydb` (the full `run.php` over ~500 databases is slow).
- Partition layout: `{prefix}/{database}/schema={schema}/relation={relation}/_gc2_snapshot_date={YYYY-MM-DD}/`; files `data-{snapshot_id}.parquet` and `metadata-{snapshot_id}.json`.
- Status vocabulary: `pending`, `running`, `succeeded`, `failed`, `superseded`. Visible iff `status = 'succeeded' AND published IS NOT NULL`.
- Error codes: `NO_SNAPSHOT_ERROR` (404), `INSUFFICIENT_PRIVILEGES` (403), `MULTI_FILE_SNAPSHOT` (409), `SNAPSHOT_NOT_CONFIGURED` (501), `INVALID_REQUEST` (400).
- Range semantics: single `bytes=a-b` / `a-` / `-n`; 206 with `Content-Range: bytes a-b/size`; 416 with `Content-Range: bytes */size`; multiple ranges → whole file 200. Sizes come from the catalog, never from storage, on the read path.
- Config block `App::$param['snapshot']`: `storage` (`s3`|`local`), `bucket`, `prefix`, `region`, `localPath`, `download` (`proxy`|`redirect`), `urlTtl`. Credentials from `App::$param['s3']['id'|'secret']`. The local `app/conf/App.php` is gitignored and never committed; the tracked template is `docker/conf/gc2/App.php`.
- Commit messages end with the attribution lines the session-reminder specifies.
- The dev stack's `App.php` uses S3 (bucket `gc2-parquet`, prefix `dev`); the API Cest therefore exercises the S3 backend through the proxy path. Local storage is covered by unit tests.

---

## File map

| File | Responsibility |
|---|---|
| `app/api/v4/Responses/StreamedResponse.php` | + `headers` |
| `app/inc/Route2.php` | emit headers; dispatch HEAD when the controller declares `head_<action>` |
| `app/migration/Sql.php` | catalog columns, status check, unique index |
| `app/models/Snapshot.php` | `publish`, `listPublished`, `getPublished` |
| `app/inc/snapshot/SnapshotRef.php` (new) | value object |
| `app/inc/snapshot/SnapshotStorage.php` (new) | interface |
| `app/inc/snapshot/FlysystemSnapshotStorage.php` (new) | abstract base + key layout |
| `app/inc/snapshot/LocalSnapshotStorage.php` (new) | local backend |
| `app/inc/snapshot/S3SnapshotStorage.php` (new) | S3 backend |
| `app/inc/snapshot/SnapshotStorageFactory.php` (new) | config → storage |
| `app/inc/snapshot/RangeRequest.php` (new) | Range header parsing |
| `app/inc/snapshot/RangeNotSatisfiable.php` (new) | exception for 416 |
| `app/inc/snapshot/SnapshotAuthorizer.php` (new) | read authorization |
| `app/inc/SnapshotWorker.php` | writes via storage, publishes, supersedes |
| `app/scripts/snapshot_worker.php` | uses the factory |
| `app/api/v4/controllers/RelationSnapshot.php` (new) | read API |
| `docker/conf/gc2/App.php` | config template keys |
| tests: `app/tests/unit/wfs/StreamedResponseTest.php`, `app/tests/unit/RangeRequestTest.php`, `app/tests/unit/LocalSnapshotStorageTest.php`, `app/tests/unit/S3SnapshotStorageTest.php`, `app/tests/unit/SnapshotStorageFactoryTest.php`, `app/tests/unit/SnapshotModelTest.php`, `app/tests/unit/SnapshotWorkerTest.php`, `app/tests/api/RelationSnapshotV4ApiCest.php` | |

---

### Task 1: StreamedResponse headers and HEAD dispatch in Route2

**Files:**
- Modify: `app/api/v4/Responses/StreamedResponse.php`
- Modify: `app/inc/Route2.php` (the `AcceptableMethods` block around line 84-97 and the `StreamedResponse` emit around line 146-150)
- Test: `app/tests/unit/wfs/StreamedResponseTest.php`

**Interfaces:**
- Produces: `new StreamedResponse(string $contentType, Closure $callback, int $status = 200, array $headers = [])` with `public readonly array $headers`; Route2 emits each header before running the callback. A controller that declares `head_<action>` itself receives HEAD requests; all others keep the current short-circuit.

- [ ] **Step 1: Write the failing test**

Append to `app/tests/unit/wfs/StreamedResponseTest.php` inside the class:

```php
    public function testHeadersDefaultToEmptyArray(): void
    {
        $r = new StreamedResponse('text/xml', fn() => null);
        $this->assertSame([], $r->headers);
    }

    public function testHeadersAreCarried(): void
    {
        $r = new StreamedResponse('application/vnd.apache.parquet', fn() => null, 206, [
            'Content-Range' => 'bytes 0-3/100',
            'Accept-Ranges' => 'bytes',
        ]);
        $this->assertSame(206, $r->getStatus());
        $this->assertSame('bytes 0-3/100', $r->headers['Content-Range']);
        $this->assertSame('bytes', $r->headers['Accept-Ranges']);
    }
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit wfs/StreamedResponseTest.php`
Expected: 2 failures (`Undefined property: ... $headers` / unknown named parameter).

- [ ] **Step 3: Extend StreamedResponse**

Replace the class body in `app/api/v4/Responses/StreamedResponse.php`:

```php
final class StreamedResponse extends Response
{
    /**
     * @param array<string,string> $headers extra response headers (name => value), emitted before the callback runs
     */
    public function __construct(
        public readonly string  $contentType,
        public readonly Closure $callback,
        int                     $status = 200,
        public readonly array   $headers = [],
    ) {
        parent::__construct(status: $status, data: null);
    }
}
```

- [ ] **Step 4: Emit headers and dispatch HEAD in Route2**

In `app/inc/Route2.php`, change the emit block:

```php
            if ($response instanceof StreamedResponse) {
                header('HTTP/1.0 ' . $response->getStatus() . ' ' . Util::httpCodeText($response->getStatus()));
                header('Content-Type: ' . $response->contentType);
                foreach ($response->headers as $name => $value) {
                    header("$name: $value");
                }
                ($response->callback)();
                return;
            }
```

Change the short-circuit inside the `AcceptableMethods` branch (the variable `$action` already holds `"<method>_<action>"`, e.g. `head_data`, at that point):

```php
                    if ($method == "options" || ($method == "head" && !self::declaresHead($controller, $action))) {
                        if ($method == "options") {
                            $m = Input::getAccessControlRequestMethod();
                            $m = $m ? strtolower($m) : null;
                            if (!in_array($m, $allowedMethods)) {
                                $listener->throwException();
                            }
                        }
                        $listener->options();
                        return;
                    }
```

Add the helper to the class:

```php
    /**
     * True when the controller class itself (not AbstractApi) declares the
     * given head_<action> method. Such controllers answer HEAD with real
     * headers (e.g. Content-Length for range-capable downloads); every other
     * controller keeps the generic short-circuit.
     */
    private static function declaresHead(ApiInterface $controller, string $headAction): bool
    {
        if (!method_exists($controller, $headAction)) {
            return false;
        }
        return (new ReflectionMethod($controller, $headAction))->getDeclaringClass()->getName() === $controller::class;
    }
```

- [ ] **Step 5: Run tests**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit wfs/StreamedResponseTest.php`
Expected: `OK (5 tests, ...)`.
Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api KeyvalueV4ApiCest.php`
Expected: `OK` (HEAD/OPTIONS behaviour for existing controllers unchanged).

- [ ] **Step 6: Commit**

```bash
git add app/api/v4/Responses/StreamedResponse.php app/inc/Route2.php app/tests/unit/wfs/StreamedResponseTest.php
git commit -m "feat(api): StreamedResponse headers and HEAD dispatch for controllers that declare head_<action>"
```

---

### Task 2: Catalog columns and publish/list/get in the Snapshot model

**Files:**
- Modify: `app/migration/Sql.php` (append after the `relation_schema` ALTER)
- Modify: `app/models/Snapshot.php`
- Test: `app/tests/unit/SnapshotModelTest.php`

**Interfaces:**
- Produces:

```php
public function publish(string $uuid, string $snapshotDate, string $location, int $rowCount, string $schemaVersion, array $relationSchema, array $files): ?string; // superseded uuid or null
public function listPublished(string $schema, string $relation, int $limit = 100): array;   // rows, newest snapshot_date first
public function getPublished(string $schema, string $relation, string $snapshotDate): array; // ['success','message','data'=>row], throws 404 NO_SNAPSHOT_ERROR
```
Rows carry `snapshot_date` (YYYY-MM-DD), `files` (JSON text), `size_bytes`, `published`.

- [ ] **Step 1: Add the migration**

After the `relation_schema` line in `Sql::get()`:

```php
        // Read side of snapshots: the catalog identifies a snapshot by relation +
        // date, remembers its files, and only shows published rows.
        $sqls[] = "ALTER TABLE settings.snapshots ADD COLUMN snapshot_date DATE";
        $sqls[] = "ALTER TABLE settings.snapshots ADD COLUMN files JSONB";
        $sqls[] = "ALTER TABLE settings.snapshots ADD COLUMN size_bytes BIGINT";
        $sqls[] = "ALTER TABLE settings.snapshots ADD COLUMN published TIMESTAMP WITH TIME ZONE";
        $sqls[] = "ALTER TABLE settings.snapshots DROP CONSTRAINT snapshots_status_check";
        $sqls[] = "ALTER TABLE settings.snapshots ADD CONSTRAINT snapshots_status_check CHECK (status IN ('pending', 'running', 'succeeded', 'failed', 'superseded'))";
        $sqls[] = "CREATE UNIQUE INDEX snapshots_published_unique_idx ON settings.snapshots (schema_name, relation_name, snapshot_date) WHERE status = 'succeeded'";
```

Apply to the dev stack (both commands must print `ALTER TABLE` / `CREATE INDEX`):

```bash
for db in template_geocloud mydb; do docker exec postgres psql -U mydb -d $db -c "ALTER TABLE settings.snapshots ADD COLUMN snapshot_date DATE, ADD COLUMN files JSONB, ADD COLUMN size_bytes BIGINT, ADD COLUMN published TIMESTAMP WITH TIME ZONE; ALTER TABLE settings.snapshots DROP CONSTRAINT snapshots_status_check; ALTER TABLE settings.snapshots ADD CONSTRAINT snapshots_status_check CHECK (status IN ('pending','running','succeeded','failed','superseded')); CREATE UNIQUE INDEX snapshots_published_unique_idx ON settings.snapshots (schema_name, relation_name, snapshot_date) WHERE status = 'succeeded'"; done
```

Note: existing `succeeded` rows have `snapshot_date IS NULL`; the unique index treats NULLs as distinct, and they stay invisible to the read API because `published IS NULL`.

- [ ] **Step 2: Write the failing tests**

Append to `SnapshotModelTest` (uses the class's existing `model()` and `self::$database`):

```php
    public function testPublishMakesRowVisibleAndSupersedesSameDate(): void
    {
        $m = $this->model();
        $files = [['name' => 'data-a.parquet', 'size_bytes' => 10]];
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];

        $first = $m->create('pub', 'rel', null, self::$database);
        $m->claimPending(100);
        $this->assertNull($m->publish($first, '2026-09-16', 's3://b/x/', 5, 'v1', $cols, $files));

        $row = $m->getPublished('pub', 'rel', '2026-09-16')['data'];
        $this->assertSame($first, $row['uuid']);
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('2026-09-16', $row['snapshot_date']);
        $this->assertSame(10, (int)$row['size_bytes']);
        $this->assertEquals($files, json_decode($row['files'], true));
        $this->assertNotNull($row['published']);
        $this->assertNotNull($row['finished']);

        $second = $m->create('pub', 'rel', null, self::$database);
        $m->claimPending(100);
        $this->assertSame($first, $m->publish($second, '2026-09-16', 's3://b/y/', 6, 'v1', $cols, [['name' => 'data-b.parquet', 'size_bytes' => 12]]));

        $this->assertSame('superseded', $m->get($first)['data']['status']);
        $this->assertSame($second, $m->getPublished('pub', 'rel', '2026-09-16')['data']['uuid']);
        $this->assertCount(1, $m->listPublished('pub', 'rel'));
    }

    public function testListPublishedIsNewestDateFirstAndHidesOtherStates(): void
    {
        $m = $this->model();
        $cols = [['column_name' => 'gid', 'data_type' => 'integer']];
        $f = [['name' => 'data-x.parquet', 'size_bytes' => 1]];

        $old = $m->create('lp', 'rel', null, self::$database);
        $new = $m->create('lp', 'rel', null, self::$database);
        $failed = $m->create('lp', 'rel', null, self::$database);
        $pending = $m->create('lp', 'rel', null, self::$database);
        $m->claimPending(3); // claims old, new, failed (oldest first); pending stays pending
        $m->publish($old, '2026-09-14', 's3://b/1/', 1, 'v', $cols, $f);
        $m->publish($new, '2026-09-15', 's3://b/2/', 1, 'v', $cols, $f);
        $m->finish($failed, 'failed', null, null, 'boom');

        $list = $m->listPublished('lp', 'rel');
        $this->assertSame(['2026-09-15', '2026-09-14'], array_column($list, 'snapshot_date'));
        $this->assertNotContains($failed, array_column($list, 'uuid'));
        $this->assertNotContains($pending, array_column($list, 'uuid'));
        $this->assertSame([], $m->listPublished('lp', 'other'));
    }

    public function testGetPublishedUnknownDateThrows404(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->getPublished('lp', 'rel', '1999-01-01');
    }
```

- [ ] **Step 3: Run to verify failure**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotModelTest.php`
Expected: errors `Call to undefined method app\models\Snapshot::publish()`.

- [ ] **Step 4: Implement the model methods**

Add to `app\models\Snapshot`:

```php
    /**
     * Marks a claimed run succeeded and visible, in one transaction: any
     * earlier succeeded row for the same relation and date is flipped to
     * 'superseded' first (the partial unique index forbids two succeeded rows
     * per date), then this row gets its catalog data and published = now().
     *
     * @param array<int, array{column_name:string, data_type:string}> $relationSchema
     * @param array<int, array{name:string, size_bytes:int}> $files
     * @return string|null uuid of the superseded row, so the caller can delete its files
     */
    public function publish(string $uuid, string $snapshotDate, string $location, int $rowCount, string $schemaVersion, array $relationSchema, array $files): ?string
    {
        return $this->withTransaction(function () use ($uuid, $snapshotDate, $location, $rowCount, $schemaVersion, $relationSchema, $files) {
            $res = $this->prepare("UPDATE settings.snapshots SET status = 'superseded'
                                   WHERE uuid <> :uuid AND status = 'succeeded' AND snapshot_date = :date
                                     AND (schema_name, relation_name) = (SELECT schema_name, relation_name FROM settings.snapshots WHERE uuid = :uuid)
                                   RETURNING uuid");
            $this->execute($res, ['uuid' => $uuid, 'date' => $snapshotDate]);
            $superseded = $res->fetchColumn();

            $sizeBytes = array_sum(array_map(fn($f) => (int)$f['size_bytes'], $files));
            $res = $this->prepare("UPDATE settings.snapshots
                                   SET status = 'succeeded', snapshot_date = :date, s3_path = :location, row_count = :row_count,
                                       schema_version = :schema_version, relation_schema = :relation_schema,
                                       files = :files, size_bytes = :size_bytes, error = NULL,
                                       published = now(), finished = now()
                                   WHERE uuid = :uuid");
            $res->bindValue(':uuid', $uuid);
            $res->bindValue(':date', $snapshotDate);
            $res->bindValue(':location', $location);
            $res->bindValue(':row_count', $rowCount, PDO::PARAM_INT);
            $res->bindValue(':schema_version', $schemaVersion);
            $res->bindValue(':relation_schema', json_encode($relationSchema));
            $res->bindValue(':files', json_encode($files));
            $res->bindValue(':size_bytes', $sizeBytes, PDO::PARAM_INT);
            $this->execute($res);
            return $superseded === false || $superseded === null ? null : (string)$superseded;
        });
    }

    /**
     * Visible snapshots of a relation (succeeded and published), newest date first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublished(string $schema, string $relation, int $limit = 100): array
    {
        $sql = "SELECT * FROM settings.snapshots
                WHERE schema_name = :schema AND relation_name = :relation
                  AND status = 'succeeded' AND published IS NOT NULL
                ORDER BY snapshot_date DESC, published DESC
                LIMIT :limit";
        $res = $this->prepare($sql);
        $res->bindValue(':schema', $schema);
        $res->bindValue(':relation', $relation);
        $res->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $this->execute($res);
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * One visible snapshot by relation and date.
     *
     * @throws GC2Exception 404 NO_SNAPSHOT_ERROR
     */
    public function getPublished(string $schema, string $relation, string $snapshotDate): array
    {
        $sql = "SELECT * FROM settings.snapshots
                WHERE schema_name = :schema AND relation_name = :relation AND snapshot_date = :date
                  AND status = 'succeeded' AND published IS NOT NULL";
        $res = $this->prepare($sql);
        $this->execute($res, ['schema' => $schema, 'relation' => $relation, 'date' => $snapshotDate]);
        $row = $this->fetchRow($res);
        if (!$row) {
            throw new GC2Exception("No snapshot of $schema.$relation for $snapshotDate", 404, null, "NO_SNAPSHOT_ERROR");
        }
        return ['success' => true, 'message' => "Snapshot fetched", 'data' => $row];
    }
```

Also update the class docblock to mention: "`superseded`: replaced by a newer run for the same date; `published` marks visibility."

- [ ] **Step 5: Run tests**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotModelTest.php`
Expected: `OK (12 tests, ...)`. (If `withTransaction` returns void in this codebase, check `app/inc/Model.php:284`: it is declared `: mixed` and returns the callable's result.)

- [ ] **Step 6: Commit**

```bash
git add app/migration/Sql.php app/models/Snapshot.php app/tests/unit/SnapshotModelTest.php
git commit -m "feat(snapshot): catalog columns, publish with supersede, listPublished/getPublished"
```

---

### Task 3: Storage abstraction (ref, interface, base, local, S3, factory)

**Files:**
- Create: `app/inc/snapshot/SnapshotRef.php`, `SnapshotStorage.php`, `FlysystemSnapshotStorage.php`, `LocalSnapshotStorage.php`, `S3SnapshotStorage.php`, `SnapshotStorageFactory.php`
- Test: `app/tests/unit/LocalSnapshotStorageTest.php`, `app/tests/unit/S3SnapshotStorageTest.php`, `app/tests/unit/SnapshotStorageFactoryTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: the classes below, used verbatim by Tasks 4 and 6. Key layout: `FlysystemSnapshotStorage::key(SnapshotRef $ref, string $file = ''): string`.

- [ ] **Step 1: Write the failing tests**

`app/tests/unit/LocalSnapshotStorageTest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\LocalSnapshotStorage;
use app\inc\snapshot\SnapshotRef;
use Codeception\Test\Unit;

class LocalSnapshotStorageTest extends Unit
{
    protected UnitTester $tester;
    private string $root;
    private LocalSnapshotStorage $storage;
    private SnapshotRef $ref;

    protected function _before(): void
    {
        $this->root = sys_get_temp_dir() . '/local_snapshot_storage_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
        $this->storage = new LocalSnapshotStorage($this->root, 'unit');
        $this->ref = new SnapshotRef('mydb', 'geo', 'roads', '2026-09-16', 'abc-123');
    }

    protected function _after(): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->root);
    }

    public function testKeyLayoutAndLocation(): void
    {
        $this->assertSame('unit/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/data-abc-123.parquet', $this->storage->key($this->ref, 'data-abc-123.parquet'));
        $this->assertSame('mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/', (new LocalSnapshotStorage($this->root, ''))->key($this->ref));
        $this->assertSame('file://' . $this->root . '/unit/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/', $this->storage->locationOf($this->ref));
    }

    public function testWriteExistsSizeListAndDelete(): void
    {
        $this->assertFalse($this->storage->exists($this->ref, 'a.bin'));
        $this->storage->write($this->ref, 'a.bin', 'PAR1hello');
        $tmp = tmpfile();
        fwrite($tmp, '0123456789');
        rewind($tmp);
        $this->storage->writeStream($this->ref, 'b.bin', $tmp);
        fclose($tmp);

        $this->assertTrue($this->storage->exists($this->ref, 'a.bin'));
        $this->assertSame(9, $this->storage->size($this->ref, 'a.bin'));
        $this->assertSame(10, $this->storage->size($this->ref, 'b.bin'));
        $this->assertEquals(
            [['name' => 'a.bin', 'size_bytes' => 9], ['name' => 'b.bin', 'size_bytes' => 10]],
            $this->storage->listFiles($this->ref)
        );
        $this->assertSame('PAR1hello', stream_get_contents($this->storage->readStream($this->ref, 'a.bin')));

        $this->storage->delete($this->ref, 'a.bin');
        $this->assertFalse($this->storage->exists($this->ref, 'a.bin'));
        $this->storage->delete($this->ref, 'a.bin'); // missing: no exception
        $this->assertSame([['name' => 'b.bin', 'size_bytes' => 10]], $this->storage->listFiles($this->ref));
    }

    public function testReadRangeIsPositionedAtStart(): void
    {
        $this->storage->write($this->ref, 'r.bin', '0123456789');
        $this->assertSame('0123', fread($this->storage->readRange($this->ref, 'r.bin', 0, 4), 4));
        $this->assertSame('789', fread($this->storage->readRange($this->ref, 'r.bin', 7, 3), 3));
        $this->assertSame('9', fread($this->storage->readRange($this->ref, 'r.bin', 9, 1), 1));
        // Beyond EOF: the stream yields what exists; callers bound by the catalog size anyway.
        $this->assertSame('89', stream_get_contents($this->storage->readRange($this->ref, 'r.bin', 8, 100)));
    }

    public function testNoDownloadUrlForLocalStorage(): void
    {
        $this->storage->write($this->ref, 'x.bin', 'x');
        $this->assertNull($this->storage->downloadUrl($this->ref, 'x.bin', 60));
    }
}
```

`app/tests/unit/S3SnapshotStorageTest.php` (offline: `Aws\MockHandler`; presigning is a local signature computation):

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\S3SnapshotStorage;
use app\inc\snapshot\SnapshotRef;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Codeception\Test\Unit;
use GuzzleHttp\Psr7\Utils;

class S3SnapshotStorageTest extends Unit
{
    protected UnitTester $tester;
    private MockHandler $mock;
    private S3SnapshotStorage $storage;
    private SnapshotRef $ref;

    protected function _before(): void
    {
        $this->mock = new MockHandler();
        $client = new S3Client([
            'region' => 'eu-west-1',
            'version' => 'latest',
            'credentials' => ['key' => 'AKIATEST', 'secret' => 'secret'],
            'handler' => $this->mock,
        ]);
        $this->storage = new S3SnapshotStorage($client, 'gc2-parquet', 'prod');
        $this->ref = new SnapshotRef('mydb', 'geo', 'roads', '2026-09-16', 'abc-123');
    }

    public function testKeyAndLocation(): void
    {
        $this->assertSame('prod/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/data-abc-123.parquet', $this->storage->key($this->ref, 'data-abc-123.parquet'));
        $this->assertSame('s3://gc2-parquet/prod/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/', $this->storage->locationOf($this->ref));
    }

    public function testReadRangeSendsRangedGetObjectAndReturnsBody(): void
    {
        $this->mock->append(new Result(['Body' => Utils::streamFor('PAR1')]));
        $stream = $this->storage->readRange($this->ref, 'data-abc-123.parquet', 0, 4);
        $this->assertSame('PAR1', stream_get_contents($stream));

        $cmd = $this->mock->getLastCommand();
        $this->assertSame('GetObject', $cmd->getName());
        $this->assertSame('gc2-parquet', $cmd['Bucket']);
        $this->assertSame('prod/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/data-abc-123.parquet', $cmd['Key']);
        $this->assertSame('bytes=0-3', $cmd['Range']);
    }

    public function testReadRangeOffsetArithmetic(): void
    {
        $this->mock->append(new Result(['Body' => Utils::streamFor('xyz')]));
        $this->storage->readRange($this->ref, 'f', 100, 3);
        $this->assertSame('bytes=100-102', $this->mock->getLastCommand()['Range']);
    }

    public function testDownloadUrlIsPresignedForBucketAndKey(): void
    {
        $url = $this->storage->downloadUrl($this->ref, 'data-abc-123.parquet', 300);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://gc2-parquet.s3.eu-west-1.amazonaws.com/prod/mydb/schema%3Dgeo/relation%3Droads/_gc2_snapshot_date%3D2026-09-16/data-abc-123.parquet?', $url);
        $this->assertStringContainsString('X-Amz-Expires=300', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }
}
```

If the presigned host format differs in this SDK version (path-style vs virtual-hosted), adjust the expected prefix to what the SDK produces and assert on `gc2-parquet`, the key and `X-Amz-Expires=300` separately.

`app/tests/unit/SnapshotStorageFactoryTest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\snapshot\LocalSnapshotStorage;
use app\inc\snapshot\S3SnapshotStorage;
use app\inc\snapshot\SnapshotStorageFactory;
use Codeception\Test\Unit;

class SnapshotStorageFactoryTest extends Unit
{
    protected UnitTester $tester;

    public function testLocalStorage(): void
    {
        $s = SnapshotStorageFactory::fromConfig(['storage' => 'local', 'localPath' => sys_get_temp_dir(), 'prefix' => 'p'], []);
        $this->assertInstanceOf(LocalSnapshotStorage::class, $s);
    }

    public function testS3StorageDefaultsWhenBucketSet(): void
    {
        $s = SnapshotStorageFactory::fromConfig(['bucket' => 'b', 'prefix' => '', 'region' => 'eu-west-1'], ['id' => 'k', 'secret' => 's']);
        $this->assertInstanceOf(S3SnapshotStorage::class, $s);
    }

    public function testUnconfiguredThrows501(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(501);
        SnapshotStorageFactory::fromConfig(['bucket' => ''], []);
    }

    public function testS3WithoutCredentialsThrows501(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(501);
        SnapshotStorageFactory::fromConfig(['storage' => 's3', 'bucket' => 'b'], ['id' => '', 'secret' => '']);
    }

    public function testLocalWithoutPathThrows501(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(501);
        SnapshotStorageFactory::fromConfig(['storage' => 'local'], []);
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run each: `... run unit LocalSnapshotStorageTest.php`, `... run unit S3SnapshotStorageTest.php`, `... run unit SnapshotStorageFactoryTest.php`
Expected: class-not-found errors.

- [ ] **Step 3: Implement the classes**

`app/inc/snapshot/SnapshotRef.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * Logical identity of one snapshot. Only the storage layer turns this into a
 * physical key or path.
 */
final readonly class SnapshotRef
{
    public function __construct(
        public string $database,
        public string $schema,
        public string $relation,
        public string $snapshotDate,
        public string $snapshotId,
    ) {
    }

    public function dataFile(): string
    {
        return "data-{$this->snapshotId}.parquet";
    }

    public function metadataFile(): string
    {
        return "metadata-{$this->snapshotId}.json";
    }
}
```

`app/inc/snapshot/SnapshotStorage.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * Physical storage of snapshot files. Callers address files by SnapshotRef +
 * file name and never see buckets, keys or paths.
 */
interface SnapshotStorage
{
    public function exists(SnapshotRef $ref, string $file): bool;

    public function size(SnapshotRef $ref, string $file): int;

    /** @return list<array{name:string, size_bytes:int}> files in the snapshot directory, by name */
    public function listFiles(SnapshotRef $ref): array;

    /** @return resource read stream of the whole file */
    public function readStream(SnapshotRef $ref, string $file);

    /**
     * @return resource read stream positioned at byte $start (0-based). It holds
     *     at least the requested bytes when they exist and may hold more (local
     *     files); callers copy at most $length bytes.
     */
    public function readRange(SnapshotRef $ref, string $file, int $start, int $length);

    /** @param resource $stream */
    public function writeStream(SnapshotRef $ref, string $file, $stream): void;

    public function write(SnapshotRef $ref, string $file, string $contents): void;

    /** Deletes one file; a missing file is not an error. */
    public function delete(SnapshotRef $ref, string $file): void;

    /** Short-lived URL a client can fetch directly, or null when the backend cannot issue one. */
    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string;

    /** Display location of the snapshot directory, e.g. s3://bucket/prefix/.../ or file:///root/.../ */
    public function locationOf(SnapshotRef $ref): string;
}
```

`app/inc/snapshot/FlysystemSnapshotStorage.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use League\Flysystem\Filesystem;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToDeleteFile;

/**
 * Everything a Flysystem adapter can do for a snapshot, plus the key layout.
 * Backends add readRange and downloadUrl, which Flysystem does not offer.
 */
abstract class FlysystemSnapshotStorage implements SnapshotStorage
{
    public function __construct(
        protected readonly Filesystem $filesystem,
        protected readonly string     $prefix,
    ) {
    }

    /**
     * "{prefix}/{database}/schema={schema}/relation={relation}/_gc2_snapshot_date={date}/{file}"
     * (prefix omitted when empty; ends with '/' when $file is empty). The
     * segment is prefixed with _gc2_ so Hive-style readers never confuse it
     * with a data column.
     */
    public function key(SnapshotRef $ref, string $file = ''): string
    {
        $prefix = trim($this->prefix, '/');
        return ($prefix !== '' ? $prefix . '/' : '')
            . "{$ref->database}/schema={$ref->schema}/relation={$ref->relation}/_gc2_snapshot_date={$ref->snapshotDate}/$file";
    }

    public function exists(SnapshotRef $ref, string $file): bool
    {
        return $this->filesystem->fileExists($this->key($ref, $file));
    }

    public function size(SnapshotRef $ref, string $file): int
    {
        return $this->filesystem->fileSize($this->key($ref, $file));
    }

    public function listFiles(SnapshotRef $ref): array
    {
        $files = [];
        foreach ($this->filesystem->listContents($this->key($ref), false) as $item) {
            /** @var StorageAttributes $item */
            if (!$item->isFile()) {
                continue;
            }
            $files[] = ['name' => basename($item->path()), 'size_bytes' => (int)$this->filesystem->fileSize($item->path())];
        }
        usort($files, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $files;
    }

    public function readStream(SnapshotRef $ref, string $file)
    {
        return $this->filesystem->readStream($this->key($ref, $file));
    }

    public function writeStream(SnapshotRef $ref, string $file, $stream): void
    {
        $this->filesystem->writeStream($this->key($ref, $file), $stream);
    }

    public function write(SnapshotRef $ref, string $file, string $contents): void
    {
        $this->filesystem->write($this->key($ref, $file), $contents);
    }

    public function delete(SnapshotRef $ref, string $file): void
    {
        try {
            $this->filesystem->delete($this->key($ref, $file));
        } catch (UnableToDeleteFile) {
            // Missing files are not an error: delete is used for best-effort cleanup.
        }
    }
}
```

`app/inc/snapshot/LocalSnapshotStorage.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use RuntimeException;

/**
 * Snapshots on local disk under $root. Ranges are served with fseek; there is
 * no direct-download URL, so the API always proxies.
 */
final class LocalSnapshotStorage extends FlysystemSnapshotStorage
{
    public function __construct(private readonly string $root, string $prefix = '')
    {
        parent::__construct(new Filesystem(new LocalFilesystemAdapter($root)), $prefix);
    }

    public function readRange(SnapshotRef $ref, string $file, int $start, int $length)
    {
        $path = rtrim($this->root, '/') . '/' . $this->key($ref, $file);
        $h = @fopen($path, 'rb');
        if ($h === false) {
            throw new RuntimeException("Could not open $path");
        }
        if ($start > 0 && fseek($h, $start) !== 0) {
            fclose($h);
            throw new RuntimeException("Could not seek to $start in $path");
        }
        return $h;
    }

    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string
    {
        return null;
    }

    public function locationOf(SnapshotRef $ref): string
    {
        return 'file://' . rtrim($this->root, '/') . '/' . $this->key($ref);
    }
}
```

`app/inc/snapshot/S3SnapshotStorage.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use Aws\S3\S3Client;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

/**
 * Snapshots in an S3 bucket. Ranges become ranged GetObject calls (only the
 * requested bytes leave S3); downloads can be handed out as presigned URLs.
 */
final class S3SnapshotStorage extends FlysystemSnapshotStorage
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string   $bucket,
        string                    $prefix = '',
    ) {
        parent::__construct(new Filesystem(new AwsS3V3Adapter($client, $bucket)), $prefix);
    }

    public function readRange(SnapshotRef $ref, string $file, int $start, int $length)
    {
        $end = $start + $length - 1;
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($ref, $file),
            'Range' => "bytes=$start-$end",
            '@http' => ['stream' => true],
        ]);
        return StreamWrapper::getResource($result['Body']);
    }

    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string
    {
        $cmd = $this->client->getCommand('GetObject', ['Bucket' => $this->bucket, 'Key' => $this->key($ref, $file)]);
        return (string)$this->client->createPresignedRequest($cmd, "+$ttlSeconds seconds")->getUri();
    }

    public function locationOf(SnapshotRef $ref): string
    {
        return "s3://{$this->bucket}/" . $this->key($ref);
    }
}
```

`app/inc/snapshot/SnapshotStorageFactory.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use app\conf\App;
use app\exceptions\GC2Exception;
use Aws\S3\S3Client;

/**
 * Builds the configured SnapshotStorage from App::$param['snapshot'] (+ the
 * s3 block for credentials). Shared by the cron worker and the read API so
 * both always see the same backend.
 */
final class SnapshotStorageFactory
{
    /**
     * @param array<string,mixed>|null $cfg   App::$param['snapshot']
     * @param array<string,mixed>|null $s3Cfg App::$param['s3']
     * @throws GC2Exception 501 SNAPSHOT_NOT_CONFIGURED
     */
    public static function fromConfig(?array $cfg, ?array $s3Cfg): SnapshotStorage
    {
        $cfg = $cfg ?? [];
        $storage = $cfg['storage'] ?? (!empty($cfg['bucket']) ? 's3' : '');
        $prefix = (string)($cfg['prefix'] ?? '');
        if ($storage === 'local') {
            $root = (string)($cfg['localPath'] ?? '');
            if ($root === '') {
                throw new GC2Exception("Snapshot storage is local but snapshot.localPath is not set", 501, null, "SNAPSHOT_NOT_CONFIGURED");
            }
            return new LocalSnapshotStorage($root, $prefix);
        }
        if ($storage === 's3') {
            $bucket = (string)($cfg['bucket'] ?? '');
            $id = (string)($s3Cfg['id'] ?? '');
            $secret = (string)($s3Cfg['secret'] ?? '');
            if ($bucket === '' || $id === '' || $secret === '') {
                throw new GC2Exception("Snapshot storage is s3 but snapshot.bucket or s3.id/s3.secret is not set", 501, null, "SNAPSHOT_NOT_CONFIGURED");
            }
            $client = new S3Client([
                'credentials' => ['key' => $id, 'secret' => $secret],
                'region' => (string)($cfg['region'] ?? 'eu-west-1'),
                'version' => 'latest',
            ]);
            return new S3SnapshotStorage($client, $bucket, $prefix);
        }
        throw new GC2Exception("Snapshot storage is not configured on this server", 501, null, "SNAPSHOT_NOT_CONFIGURED");
    }

    /** Convenience for runtime code. */
    public static function fromApp(): SnapshotStorage
    {
        return self::fromConfig(App::$param['snapshot'] ?? null, App::$param['s3'] ?? null);
    }
}
```

- [ ] **Step 4: Run the three unit tests**

Expected: all `OK`. If `StreamWrapper::getResource` is missing, check `app/vendor/guzzlehttp/psr7/src/StreamWrapper.php` (it exists in psr7 ≥1.4); if `listContents` items need `->path()` vs `['path']`, follow `League\Flysystem\StorageAttributes`.

- [ ] **Step 5: Commit**

```bash
git add app/inc/snapshot app/tests/unit/LocalSnapshotStorageTest.php app/tests/unit/S3SnapshotStorageTest.php app/tests/unit/SnapshotStorageFactoryTest.php
git commit -m "feat(snapshot): storage abstraction with local and S3 backends and a config factory"
```

---

### Task 4: Worker writes through SnapshotStorage and publishes

**Files:**
- Modify: `app/inc/SnapshotWorker.php`
- Modify: `app/scripts/snapshot_worker.php`
- Modify: `docker/conf/gc2/App.php` (snapshot block) and the local gitignored `app/conf/App.php` (add `storage`, `download`, `urlTtl` keys)
- Test: `app/tests/unit/SnapshotWorkerTest.php`

**Interfaces:**
- Consumes: `SnapshotStorage`, `SnapshotRef`, `SnapshotStorageFactory` (Task 3); `Snapshot::publish` (Task 2).
- Produces: `new SnapshotWorker(Connection $connection, SnapshotStorage $storage, string $tmpDir)`; `processPending(int $limit = 2): array` unchanged. `partitionKey()` is removed (layout now lives in `FlysystemSnapshotStorage::key`).

- [ ] **Step 1: Update the worker test**

In `app/tests/unit/SnapshotWorkerTest.php`:
- Replace the `use League\Flysystem\...` imports with `use app\inc\snapshot\LocalSnapshotStorage;` and `use app\inc\snapshot\SnapshotRef;`.
- `worker()` becomes:

```php
    private function worker(string $prefix = 'unit'): SnapshotWorker
    {
        return new SnapshotWorker(
            new Connection(database: self::$database),
            new LocalSnapshotStorage($this->storeDir, $prefix),
            $this->tmpDir,
        );
    }
```

- Delete `testPartitionKeyLayout` (covered by `LocalSnapshotStorageTest`).
- In every test that builds `$partition`, keep the same string but the file names change: `data.parquet` → `'data-' . $uuid . '.parquet'`, `metadata.json` → `'metadata-' . $uuid . '.json'`.
- In `testTableSnapshotSucceedsAndWritesParquetAndMetadata` add, after the metadata assertions:

```php
        $this->assertSame($day, $meta['snapshot_date']);
        $this->assertEquals([['name' => 'data-' . $uuid . '.parquet', 'size_bytes' => filesize($this->storeDir . '/' . $partition . 'data-' . $uuid . '.parquet')]], $meta['files']);
        $this->assertSame($day, $row['snapshot_date']);
        $this->assertNotNull($row['published']);
        $this->assertEquals($meta['files'], json_decode($row['files'], true));
        $this->assertSame((int)$meta['files'][0]['size_bytes'], (int)$row['size_bytes']);
        $this->assertSame('file://' . $this->storeDir . '/' . $partition, $row['s3_path']);
```

- Add two tests:

```php
    public function testRerunSameDaySupersedesAndDeletesOldFile(): void
    {
        $first = $this->snapshot()->create('snap', 'points', null, self::$database);
        $this->worker()->processPending(5);
        $day = gmdate('Y-m-d');
        $partition = 'unit/' . self::$database . '/schema=snap/relation=points/_gc2_snapshot_date=' . $day . '/';
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data-' . $first . '.parquet');

        $second = $this->snapshot()->create('snap', 'points', null, self::$database);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded']);

        $this->assertSame('superseded', $this->snapshot()->get($first)['data']['status']);
        $this->assertSame($second, $this->snapshot()->getPublished('snap', 'points', $day)['data']['uuid']);
        $this->assertFileDoesNotExist($this->storeDir . '/' . $partition . 'data-' . $first . '.parquet', 'superseded files are removed');
        $this->assertFileDoesNotExist($this->storeDir . '/' . $partition . 'metadata-' . $first . '.json');
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data-' . $second . '.parquet');
    }

    public function testFailedRunIsNotPublishedAndLeavesNoFiles(): void
    {
        $uuid = $this->snapshot()->create('snap', 'does_not_exist', null, self::$database);
        $this->worker()->processPending(5);
        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertNull($row['published']);
        $this->assertSame([], $this->snapshot()->listPublished('snap', 'does_not_exist'));
    }
```

(The existing `testMissingRelationFailsRowWithError` may be merged into the second test or kept; keep both if they do not conflict.)

- [ ] **Step 2: Run to verify failure**

Run: `... run unit SnapshotWorkerTest.php`
Expected: constructor type errors (Filesystem vs SnapshotStorage).

- [ ] **Step 3: Rewrite the worker**

Replace `app/inc/SnapshotWorker.php` with the version below (private helpers `export`, `rowCount`, `nativeSrid`, `columns` and `schemaVersion` are unchanged from the current file; copy them verbatim):

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\inc\snapshot\SnapshotRef;
use app\inc\snapshot\SnapshotStorage;
use app\models\Snapshot as SnapshotModel;
use RuntimeException;
use Throwable;

/**
 * Runs queued snapshots for one database: claims pending rows in
 * settings.snapshots, exports each relation to Parquet with ogr2ogr, writes
 * data-<id>.parquet + metadata-<id>.json through SnapshotStorage, and
 * publishes the row (superseding an earlier snapshot of the same date).
 *
 * Files are named by snapshot id, so a rerun never overwrites a file a
 * reader may be streaming; readers only reach files via the catalog, so a
 * run that fails before publish leaves nothing visible.
 */
class SnapshotWorker
{
    private SnapshotModel $snapshot;
    private Model $model;

    public function __construct(
        private readonly Connection      $connection,
        private readonly SnapshotStorage $storage,
        private readonly string          $tmpDir,
    )
    {
        $this->snapshot = new SnapshotModel($connection);
        $this->model = new Model($connection);
    }

    /**
     * Claim and run up to $limit pending snapshots.
     *
     * @return array{processed:int, succeeded:int, failed:int}
     */
    public function processPending(int $limit = 2): array
    {
        $summary = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];
        foreach ($this->snapshot->claimPending($limit) as $row) {
            $status = $this->runOne($row);
            $summary['processed']++;
            $summary[$status === 'succeeded' ? 'succeeded' : 'failed']++;
        }
        return $summary;
    }

    // schemaVersion(): unchanged, copy from the current file.

    /**
     * @return string 'succeeded' | 'failed'
     */
    private function runOne(array $row): string
    {
        $uuid = $row['uuid'];
        $schema = $row['schema_name'];
        $relation = $row['relation_name'];
        $srs = $row['srs'] !== null ? (int)$row['srs'] : null;
        $tmpFile = rtrim($this->tmpDir, '/') . "/$uuid.parquet";
        $ref = new SnapshotRef($this->connection->database, $schema, $relation, gmdate('Y-m-d'), $uuid);
        $written = [];
        try {
            if (!$this->model->doesRelationExists("$schema.$relation")) {
                throw new RuntimeException("Relation $schema.$relation does not exist");
            }
            $crs = $srs ?? $this->nativeSrid($schema, $relation);
            $columns = $this->columns($schema, $relation);
            $schemaVersion = self::schemaVersion($columns);

            if (!is_dir($this->tmpDir) && !mkdir($this->tmpDir, 0775, true) && !is_dir($this->tmpDir)) {
                throw new RuntimeException("Could not create tmp dir {$this->tmpDir}");
            }
            $this->export($schema, $relation, $srs, $tmpFile);

            // Counted after the export on a separate connection, so on a live table this is approximate.
            $rowCount = $this->rowCount($schema, $relation);
            $files = [['name' => $ref->dataFile(), 'size_bytes' => (int)filesize($tmpFile)]];

            $stream = fopen($tmpFile, 'rb');
            if ($stream === false) {
                throw new RuntimeException("Could not open $tmpFile");
            }
            try {
                $this->storage->writeStream($ref, $ref->dataFile(), $stream);
                $written[] = $ref->dataFile();
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            $this->storage->write($ref, $ref->metadataFile(), json_encode([
                'snapshot_id' => $uuid,
                'snapshot_date' => $ref->snapshotDate,
                'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'database' => $this->connection->database,
                'source' => "$schema.$relation",
                'row_count' => $rowCount,
                'schema_version' => $schemaVersion,
                'schema' => $columns,
                'crs' => $crs !== null ? "EPSG:$crs" : null,
                'files' => $files,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $written[] = $ref->metadataFile();

            $superseded = $this->snapshot->publish($uuid, $ref->snapshotDate, $this->storage->locationOf($ref), $rowCount, $schemaVersion, $columns, $files);
            if ($superseded !== null) {
                $this->deleteFilesOf($superseded, $ref);
            }
            return 'succeeded';
        } catch (Throwable $e) {
            // Bounded and redacted: the PG connection string (with password)
            // can end up in an ogr2ogr/PDO error, and the message can be
            // arbitrarily long (e.g. the full ogr2ogr output).
            $msg = preg_replace('/password=\S+/', 'password=***', $e->getMessage());
            if (strlen($msg) > 2000) {
                $msg = substr($msg, -2000);
            }
            $this->snapshot->finish($uuid, 'failed', null, null, $msg);
            foreach ($written as $file) {
                try {
                    $this->storage->delete($ref, $file);
                } catch (Throwable) {
                    // best effort; orphans without a catalog row are invisible
                }
            }
            return 'failed';
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Removes the files of a superseded row (same relation and date, so the
     * same directory as $current). Best effort: the catalog is already
     * consistent, an orphaned object only costs storage.
     */
    private function deleteFilesOf(string $supersededUuid, SnapshotRef $current): void
    {
        try {
            $old = $this->snapshot->get($supersededUuid)['data'];
            $files = is_string($old['files'] ?? null) ? (json_decode($old['files'], true) ?: []) : [];
            $oldRef = new SnapshotRef($current->database, $current->schema, $current->relation, $current->snapshotDate, $supersededUuid);
            foreach ($files as $f) {
                $this->storage->delete($oldRef, $f['name']);
            }
            $this->storage->delete($oldRef, $oldRef->metadataFile());
        } catch (Throwable $e) {
            error_log("snapshot: could not delete files of superseded $supersededUuid: " . $e->getMessage());
        }
    }

    // export(), rowCount(), nativeSrid(), columns(): unchanged, copy from the current file.
}
```

- [ ] **Step 4: Update the script and config**

`app/scripts/snapshot_worker.php`: remove the bucket/credential checks and the S3Client/Flysystem construction; replace with

```php
use app\inc\snapshot\SnapshotStorageFactory;
...
try {
    $storage = SnapshotStorageFactory::fromApp();
} catch (GC2Exception $e) {
    echo "SNAPSHOT WORKER: {$e->getMessage()}, nothing to do\n";
    exit(0);
} catch (Throwable $e) {
    echo "SNAPSHOT WORKER: could not initialise snapshot storage: {$e->getMessage()}\n";
    exit(1);
}
```

(add `use app\exceptions\GC2Exception;`, drop the now-unused `Aws`/`League` imports and `$bucket/$prefix/$region/$s3Id/$s3Secret` variables) and construct the worker as `new SnapshotWorker($connection, $storage, $tmpDir)`.

`docker/conf/gc2/App.php` snapshot block becomes:

```php
        // Parquet snapshots (POST /api/v4/snapshots, GET .../relations/{relation}/snapshots).
        // storage: "s3" (credentials from the "s3" block) or "local" (localPath).
        // download: "proxy" streams through GC2; "redirect" answers with a short-lived
        // presigned URL (s3 only). Leave bucket/localPath empty to disable.
        "snapshot" => [
            "storage" => "s3",
            "bucket" => "",
            "prefix" => "",
            "region" => "eu-west-1",
            "localPath" => "",
            "download" => "proxy",
            "urlTtl" => 300,
        ],
```

Add the same keys to the local `app/conf/App.php` (`storage` = `s3`, keep bucket `gc2-parquet` / prefix `dev`, `download` = `proxy`, `urlTtl` = 300). Do not commit that file.

- [ ] **Step 5: Run tests and a smoke run**

Run: `... run unit SnapshotWorkerTest.php` → `OK (8 tests, ...)` (7 existing minus partitionKey plus two new).
Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php -l scripts/snapshot_worker.php` and `docker exec -w /var/www/geocloud2/app docker-dev-1 sudo -u www-data php -f scripts/snapshot_worker.php mydb` → `SNAPSHOT WORKER START` + `TOTAL`.

- [ ] **Step 6: Commit**

```bash
git add app/inc/SnapshotWorker.php app/scripts/snapshot_worker.php docker/conf/gc2/App.php app/tests/unit/SnapshotWorkerTest.php
git commit -m "refactor(snapshot): worker writes through SnapshotStorage and publishes with supersede"
```

---

### Task 5: RangeRequest

**Files:**
- Create: `app/inc/snapshot/RangeRequest.php`, `app/inc/snapshot/RangeNotSatisfiable.php`
- Test: `app/tests/unit/RangeRequestTest.php`

**Interfaces:**
- Produces:

```php
final readonly class RangeRequest {
    public int $start; public int $end;                       // inclusive
    public static function parse(?string $header, int $size): ?self;  // null = serve whole; throws RangeNotSatisfiable
    public function length(): int;
    public function contentRange(int $size): string;          // "bytes a-b/size"
}
final class RangeNotSatisfiable extends \RuntimeException { public function __construct(public readonly int $size) {} }
```

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\RangeNotSatisfiable;
use app\inc\snapshot\RangeRequest;
use Codeception\Test\Unit;

class RangeRequestTest extends Unit
{
    protected UnitTester $tester;

    /** @return array<string, array{?string, int, ?array{int,int}}> */
    public static function satisfiable(): array
    {
        return [
            'no header' => [null, 100, null],
            'empty header' => ['', 100, null],
            'closed' => ['bytes=0-3', 100, [0, 3]],
            'open end' => ['bytes=90-', 100, [90, 99]],
            'suffix' => ['bytes=-10', 100, [90, 99]],
            'suffix larger than file' => ['bytes=-500', 100, [0, 99]],
            'end clamped' => ['bytes=95-200', 100, [95, 99]],
            'single last byte' => ['bytes=99-99', 100, [99, 99]],
            'multiple ranges ignored' => ['bytes=0-1,5-6', 100, null],
            'other unit ignored' => ['items=0-1', 100, null],
            'garbage ignored' => ['bytes=abc', 100, null],
        ];
    }

    /** @dataProvider satisfiable */
    public function testParse(?string $header, int $size, ?array $expected): void
    {
        $r = RangeRequest::parse($header, $size);
        if ($expected === null) {
            $this->assertNull($r);
            return;
        }
        $this->assertSame($expected, [$r->start, $r->end]);
        $this->assertSame($expected[1] - $expected[0] + 1, $r->length());
        $this->assertSame("bytes {$expected[0]}-{$expected[1]}/$size", $r->contentRange($size));
    }

    /** @return array<string, array{string, int}> */
    public static function unsatisfiable(): array
    {
        return [
            'start beyond size' => ['bytes=100-', 100],
            'start beyond end' => ['bytes=5-3', 100],
            'empty suffix' => ['bytes=-0', 100],
            'empty file' => ['bytes=0-0', 0],
            'both empty' => ['bytes=-', 100],
        ];
    }

    /** @dataProvider unsatisfiable */
    public function testUnsatisfiableThrows(string $header, int $size): void
    {
        try {
            RangeRequest::parse($header, $size);
            $this->fail('expected RangeNotSatisfiable');
        } catch (RangeNotSatisfiable $e) {
            $this->assertSame($size, $e->size);
        }
    }
}
```

- [ ] **Step 2: Run to verify failure** → class not found.

- [ ] **Step 3: Implement**

`app/inc/snapshot/RangeNotSatisfiable.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use RuntimeException;

/** A syntactically valid Range that no byte of the file can satisfy (HTTP 416). */
final class RangeNotSatisfiable extends RuntimeException
{
    public function __construct(public readonly int $size)
    {
        parent::__construct("Range not satisfiable for size $size");
    }
}
```

`app/inc/snapshot/RangeRequest.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

/**
 * One satisfiable byte range of a file of known size (RFC 9110 §14).
 * Inclusive bounds, 0-based.
 */
final readonly class RangeRequest
{
    public function __construct(public int $start, public int $end)
    {
    }

    /**
     * Parses a Range header against the file size.
     *
     * Returns null when the header is absent, uses another unit, names more
     * than one range, or is malformed: the server may then ignore Range and
     * answer 200 with the whole file. Throws RangeNotSatisfiable (416) for a
     * well-formed single range no byte of the file can satisfy.
     */
    public static function parse(?string $header, int $size): ?self
    {
        if ($header === null || !preg_match('/^\s*bytes\s*=\s*(\d*)\s*-\s*(\d*)\s*$/', $header, $m)) {
            return null;
        }
        [, $a, $b] = $m;
        if ($a === '' && $b === '') {
            throw new RangeNotSatisfiable($size);
        }
        if ($size <= 0) {
            throw new RangeNotSatisfiable($size);
        }
        if ($a === '') {
            // suffix range: last $b bytes
            $n = (int)$b;
            if ($n <= 0) {
                throw new RangeNotSatisfiable($size);
            }
            $n = min($n, $size);
            return new self($size - $n, $size - 1);
        }
        $start = (int)$a;
        if ($start >= $size) {
            throw new RangeNotSatisfiable($size);
        }
        $end = $b === '' ? $size - 1 : min((int)$b, $size - 1);
        if ($start > $end) {
            throw new RangeNotSatisfiable($size);
        }
        return new self($start, $end);
    }

    public function length(): int
    {
        return $this->end - $this->start + 1;
    }

    public function contentRange(int $size): string
    {
        return "bytes {$this->start}-{$this->end}/$size";
    }
}
```

- [ ] **Step 4: Run** → `OK (16 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add app/inc/snapshot/RangeRequest.php app/inc/snapshot/RangeNotSatisfiable.php app/tests/unit/RangeRequestTest.php
git commit -m "feat(snapshot): RangeRequest parser for HTTP byte ranges"
```

---

### Task 6: Authorizer and the relation snapshot read API

**Files:**
- Create: `app/inc/snapshot/SnapshotAuthorizer.php`, `app/api/v4/controllers/RelationSnapshot.php`
- Test: `app/tests/api/RelationSnapshotV4ApiCest.php`

**Interfaces:**
- Consumes: `Snapshot::listPublished/getPublished` (Task 2), `SnapshotStorageFactory::fromApp`, `SnapshotRef`, `SnapshotStorage` (Task 3), `RangeRequest`/`RangeNotSatisfiable` (Task 5), `StreamedResponse` headers + HEAD dispatch (Task 1), `app\models\Authorization::extractHighestPrivilege(array $privileges, string $subUser, ?array $groups): string` and `isOwner(string $subUser, ?array $groups, string $schema): bool`, `Model::getGeometryColumns(string $table, string $field): mixed` with field `privileges`.
- Produces: routes `GET .../snapshots`, `GET .../snapshots/{date}`, `GET|HEAD .../snapshots/{date}/data`, `GET|HEAD .../snapshots/{date}/files/{file}`.

- [ ] **Step 1: Write the failing API test**

```php
<?php

use Codeception\Util\HttpCode;

/**
 * HTTP contract of the relation snapshot read API
 * (app/api/v4/controllers/RelationSnapshot.php): listing, metadata, HEAD/GET
 * of the Parquet file with byte ranges, and the privilege check. Runs the
 * snapshot worker inline (same container) after queuing, against whatever
 * storage app/conf/App.php configures. Ordered/stateful.
 */
class RelationSnapshotV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subUserId;
    private $subToken;
    private $schema;
    private $date;
    private $size;

    public function __construct()
    {
        $this->schema = 'rsnap_' . (new DateTime())->getTimestamp();
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    private function asSub(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
    }

    private function base(): string
    {
        return '/api/v4/schemas/' . $this->schema . '/relations/poi/snapshots';
    }

    public function shouldPrepareUsersTableAndSnapshot(ApiTester $I)
    {
        $ts = (new DateTime())->getTimestamp();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => 'rsnap super ' . $ts, 'email' => 'rsnapsuper' . $ts . '@example.com', 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 'public']));
        $I->seeResponseCodeIs(HttpCode::OK);
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPOST('/api/v2/user', json_encode(['name' => 'rsnap sub ' . $ts, 'email' => 'rsnapsub' . $ts . '@example.com', 'password' => $this->password, 'subuser' => true]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->subUserId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;

        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'poi', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'], ['name' => 'name', 'type' => 'varchar'], ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/sql', json_encode(['q' => "INSERT INTO \"{$this->schema}\".poi(name, the_geom) SELECT 'p'||g, ST_SetSRID(ST_MakePoint(10+g*0.001, 56), 4326) FROM generate_series(1, 50) g"]));
        $I->seeResponseCodeIs(HttpCode::OK);

        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $id = json_decode($I->grabResponse())->id;

        // Run the worker inline for this database (the container has no snapshot cron).
        $out = shell_exec('php -f /var/www/geocloud2/app/scripts/snapshot_worker.php ' . escapeshellarg($this->userId) . ' 2>&1');
        $I->assertStringContainsString('ok=1', (string)$out, "worker output: $out");

        $I->sendGET('/api/v4/snapshots/' . $id);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['status' => 'succeeded']);
        $this->date = gmdate('Y-m-d');
    }

    public function shouldListSnapshotsOfRelation(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertCount(1, $body->snapshots);
        $s = $body->snapshots[0];
        $I->assertEquals($this->date, $s->snapshot_date);
        $I->assertEquals(50, $s->row_count);
        $I->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $s->schema_version);
        $I->assertCount(1, $s->files);
        $I->assertStringEndsWith('.parquet', $s->files[0]->name);
        $I->assertEquals($this->base() . '/' . $this->date . '/files/' . $s->files[0]->name, $s->files[0]->href);
        $this->size = (int)$s->files[0]->size_bytes;
        $I->assertGreaterThan(8, $this->size);
    }

    public function shouldReturnSnapshotMetadata(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date);
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = json_decode($I->grabResponse());
        $I->assertEquals($this->date, $body->snapshot_date);
        $I->assertEquals('EPSG:4326', $body->crs);
        $I->assertEquals('gid', $body->relation_schema[0]->column_name);
        $I->assertEquals($this->base() . '/' . $this->date . '/data', $body->_links->data);

        $I->sendGET($this->base() . '/1999-01-01');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);

        $I->sendGET($this->base() . '/not-a-date');
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldAnswerHeadWithLengthAndAcceptRanges(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendHEAD($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Accept-Ranges', 'bytes');
        $I->seeHttpHeader('Content-Length', (string)$this->size);
        $I->seeHttpHeader('Content-Type', 'application/vnd.apache.parquet');
        $I->assertSame('', $I->grabResponse());
    }

    public function shouldStreamWholeFile(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $body = $I->grabResponse();
        $I->assertSame($this->size, strlen($body));
        $I->assertSame('PAR1', substr($body, 0, 4));
        $I->assertSame('PAR1', substr($body, -4));
    }

    public function shouldServeByteRanges(ApiTester $I)
    {
        $this->asSuper($I);
        $I->haveHttpHeader('Range', 'bytes=0-3');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::PARTIAL_CONTENT);
        $I->seeHttpHeader('Content-Range', 'bytes 0-3/' . $this->size);
        $I->seeHttpHeader('Content-Length', '4');
        $I->assertSame('PAR1', $I->grabResponse());

        $I->haveHttpHeader('Range', 'bytes=-4');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::PARTIAL_CONTENT);
        $I->seeHttpHeader('Content-Range', 'bytes ' . ($this->size - 4) . '-' . ($this->size - 1) . '/' . $this->size);
        $I->assertSame('PAR1', $I->grabResponse());

        $I->haveHttpHeader('Range', 'bytes=999999999-');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::RANGE_NOT_SATISFIABLE);
        $I->seeHttpHeader('Content-Range', 'bytes */' . $this->size);

        $I->haveHttpHeader('Range', 'bytes=0-1,4-5');
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->deleteHeader('Range');
    }

    public function shouldServeMetadataFileByName(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET($this->base() . '/' . $this->date);
        $meta = json_decode($I->grabResponse());
        $metaFile = 'metadata-' . $meta->snapshot_id . '.json';
        $I->sendGET($this->base() . '/' . $this->date . '/files/' . $metaFile);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeHttpHeader('Content-Type', 'application/json');
        $I->assertEquals($this->schema . '.poi', json_decode($I->grabResponse())->source);

        $I->sendGET($this->base() . '/' . $this->date . '/files/nope.parquet');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldEnforcePrivilegesForSubUser(ApiTester $I)
    {
        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);
        $I->seeResponseContainsJson(['errorCode' => 'INSUFFICIENT_PRIVILEGES']);
        $I->sendGET($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::FORBIDDEN);

        $this->asSuper($I);
        $I->sendPATCH('/api/v4/schemas/' . $this->schema . '/tables/poi/privileges', json_encode(['subuser' => $this->subUserId, 'privilege' => 'read']));
        $I->seeResponseCodeIsSuccessful();

        $this->asSub($I);
        $I->sendGET($this->base());
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->sendHEAD($this->base() . '/' . $this->date . '/data');
        $I->seeResponseCodeIs(HttpCode::OK);
    }
}
```

`HttpCode::PARTIAL_CONTENT` (206) and `RANGE_NOT_SATISFIABLE` (416) exist in Codeception's `HttpCode`; if not, use the integers.

- [ ] **Step 2: Run to verify failure**

Run: `... run api RelationSnapshotV4ApiCest.php`
Expected: prepare passes (job API and worker exist), the first read test fails with 404.

- [ ] **Step 3: Implement the authorizer**

`app/inc/snapshot/SnapshotAuthorizer.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Authorization;

/**
 * May the JWT identity read snapshots of a relation? Mirrors the SQL API's
 * read rule: super-users always; sub-users when they own the schema or hold
 * a read/read-write privilege on the relation. Every relation GC2 knows has
 * a privilege row (settings.geometry_columns_view unions non-spatial tables,
 * views and matviews with geometry_columns); a dropped relation has none,
 * so only super-users and schema owners can still read its history.
 */
final class SnapshotAuthorizer
{
    private Authorization $authorization;

    public function __construct(Connection $connection)
    {
        $this->authorization = new Authorization($connection);
    }

    /**
     * @param array<string,mixed> $jwtData the "data" part of the JWT (uid, superUser, userGroup)
     * @throws GC2Exception 403 INSUFFICIENT_PRIVILEGES
     */
    public function assertCanRead(array $jwtData, string $schema, string $relation): void
    {
        if (!empty($jwtData['superUser'])) {
            return;
        }
        $uid = (string)($jwtData['uid'] ?? '');
        $groups = $jwtData['userGroup'] ?? null;
        $groups = is_array($groups) ? $groups : ($groups === null ? null : [$groups]);
        if ($this->authorization->isOwner($uid, $groups, $schema)) {
            return;
        }
        $raw = $this->authorization->getGeometryColumns("$schema.$relation", 'privileges');
        $privileges = is_string($raw) ? (json_decode($raw, true) ?: []) : (is_array($raw) ? $raw : []);
        $privilege = $privileges === [] ? 'none' : $this->authorization->extractHighestPrivilege($privileges, $uid, $groups);
        if ($privilege === 'read' || $privilege === 'read/write' || $privilege === 'write') {
            return;
        }
        throw new GC2Exception("Insufficient privileges to read snapshots of $schema.$relation", 403, null, "INSUFFICIENT_PRIVILEGES");
    }
}
```

Check `Authorization::extractHighestPrivilege` and `isOwner` are public (they are, per `app/models/Authorization.php:145,169`) and what `getGeometryColumns(..., 'privileges')` returns for a relation with no privileges set yet (likely `null`/`''` → treated as none) and for a dropped relation (`null` → none). Non-spatial relations are registered like spatial ones, so no special case is needed.

- [ ] **Step 4: Implement the controller**

`app/api/v4/controllers/RelationSnapshot.php`:

```php
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
        $this->schemaName = (string)$this->route->getParam('schema');
        $this->relationName = (string)$this->route->getParam('relation');
        $this->resource = 'snapshot';
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
```

Notes for the implementer:
- Check how `RedirectResponse` is constructed (`app/api/v4/Responses/RedirectResponse.php`) and that Route2 emits `Location` for 302 (`if ($status == 302) return;` after the status line — verify the Location header is emitted by `Response`/Route2 before that return; if not, emit `header("Location: $url")` in `serve()` before returning).
- `Route2` sets `$this->action` from the route match; `(action)` is `index` when absent. Confirm `$this->route->action` is public (Func.php uses it).
- HEAD dispatch relies on Task 1; `head_data`/`head_files` must be declared on this class (they are).
- The OpenAPI attribute style nests `parameters:`/`responses:` inside `#[OA\Get]`, matching `Ogc.php` and the pattern adopted for `Snapshot.php` yesterday.

- [ ] **Step 5: Run the Cest until green**

Run: `... run api RelationSnapshotV4ApiCest.php`
Expected: `OK (8 tests, ...)`. Typical failures and what they mean: 404 on `/data` → HEAD/GET routing or `(action)` mismatch; `Content-Length` mismatch → output buffering not fully closed or gzip active; 500 on `/files/…` for metadata → `size()` call failed (S3 permissions).

Also re-run `... run api SnapshotV4ApiCest.php` (job API unchanged) → `OK`.

- [ ] **Step 6: Commit**

```bash
git add app/inc/snapshot/SnapshotAuthorizer.php app/api/v4/controllers/RelationSnapshot.php app/tests/api/RelationSnapshotV4ApiCest.php
git commit -m "feat(api): relation snapshot read API with HEAD and byte-range streaming"
```

---

### Task 7: Scheduler integration (optional snapshot after harvest)

**Files:**
- Modify: `app/migration/Sql.php` (`gc2scheduler()` section: `ALTER TABLE jobs ADD COLUMN snapshot BOOL DEFAULT FALSE`)
- Modify: `app/models/Job.php` (`newJob`/`updateJob` include `snapshot`), `app/scripts/get.php` (`cleanUp(1)` path)
- Test: manual (the scheduler has no automated suite); unit coverage comes from `Snapshot::create`/`hasActive` already tested.

**Interfaces:**
- Consumes: `app\models\Snapshot::create(schema, relation, null, username)` and `hasActive`.

- [ ] **Step 1: Migration**

In `Sql::gc2scheduler()` append: `$sqls[] = "ALTER TABLE jobs ADD COLUMN snapshot BOOL DEFAULT FALSE";` and apply: `docker exec postgres psql -U mydb -d gc2scheduler -c "ALTER TABLE jobs ADD COLUMN snapshot BOOL DEFAULT FALSE"`.

- [ ] **Step 2: Job model**

In `newJob` add `snapshot` to the column list and `:snapshot => !empty($data->snapshot)` to the parameters (PDO bool: bind as `(int)` or use `'true'/'false'` consistent with how `active` is passed in the same statement). Same in `updateJob`. Pass the flag to `get.php` in `runJob` as `--snapshot {0|1}`.

- [ ] **Step 3: get.php**

Where `get.php` parses its options, read `--snapshot`. In `cleanUp()` after `$layer->updateLastmodified(...)` inside `if ($success)`:

```php
        if (!empty($snapshotAfterImport)) {
            try {
                $snap = new \app\models\Snapshot(new \app\inc\Connection(database: $db));
                if (!$snap->hasActive($schema, $safeName)) {
                    $snap->create($schema, $safeName, null, $db);
                    print "\nInfo: Snapshot queued for $schema.$safeName";
                }
            } catch (\Throwable $e) {
                print "\nWarning: could not queue snapshot: " . $e->getMessage();
            }
        }
```

(`$snapshotAfterImport` is the parsed `--snapshot` flag, declared `global` in `cleanUp`.)

- [ ] **Step 4: Verify**

`php -l` on the three files; run one scheduler job by hand with `snapshot=true` via the v2/v3 job API in the dev stack if convenient, and check a pending row appears in `settings.snapshots` of that database. If no job is at hand, record that the path was verified by reading only.

- [ ] **Step 5: Commit**

```bash
git add app/migration/Sql.php app/models/Job.php app/scripts/get.php
git commit -m "feat(scheduler): optional snapshot after a successful harvest"
```

---

### Task 8: Verification and DuckDB end-to-end

**Files:** none new.

- [ ] **Step 1: Unit suites**

Run each as its own command: `wfs/StreamedResponseTest.php`, `RangeRequestTest.php`, `LocalSnapshotStorageTest.php`, `S3SnapshotStorageTest.php`, `SnapshotStorageFactoryTest.php`, `SnapshotModelTest.php`, `SnapshotWorkerTest.php`, `ModelDisconnectTest.php`. Expected: all `OK`.

- [ ] **Step 2: API suites**

`RelationSnapshotV4ApiCest.php`, `SnapshotV4ApiCest.php`, `FunctionManagementCest.php`, `KeyvalueV4ApiCest.php`, `OgcApiCest.php` (HEAD/OPTIONS on a public controller unchanged). Expected: all `OK`.

- [ ] **Step 3: DuckDB end-to-end (proxy mode)**

In the container, if `duckdb` is installable (`pip install duckdb` in a venv, or the CLI binary), run against a database that has a published snapshot (the Cest's database and token from its output, or repeat the queue+worker steps by hand):

```sql
CREATE SECRET gc2 (TYPE http, BEARER_TOKEN '<jwt>');
SELECT count(*) FROM read_parquet('http://localhost/api/v4/schemas/<schema>/relations/poi/snapshots/<date>/data');
```

Expected: 50. Record the DuckDB output (and the number of HTTP requests it made, visible in `docker logs docker-dev-1` Apache access log: several 206 responses, no 200 for the whole file). If DuckDB cannot be installed, say so and instead show three `curl -H "Range: bytes=…"` calls (footer length from the last 8 bytes, then the footer, then a row group) returning 206.

- [ ] **Step 4: Redirect mode against S3 (manual)**

Set `download` to `redirect` in the local `App.php`, run `curl -sI -H "Authorization: Bearer <jwt>" http://localhost:8080/api/v4/schemas/<schema>/relations/poi/snapshots/<date>/data` → `HTTP/1.1 302` with a `Location:` on `gc2-parquet.s3…` and `X-Amz-Expires=300`; then `curl -s -r 0-3 "<location>"` → `PAR1`. Set `download` back to `proxy`.

- [ ] **Step 5: Update memory/docs**

Add a line to `geoparquet.md`? No: that file is the user's brief; leave it. The spec already records the deviations.
