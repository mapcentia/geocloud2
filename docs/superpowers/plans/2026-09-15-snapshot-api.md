# Snapshot API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an asynchronous v4 API that exports a table or view to Parquet and uploads it to S3, with a status endpoint for polling.

**Architecture:** A `settings.snapshots` table is the queue. `POST /api/v4/snapshots` inserts a pending row and returns 202. A cron script runs `SnapshotWorker` per database, which claims pending rows with `FOR UPDATE SKIP LOCKED`, runs ogr2ogr to a tmp Parquet file, uploads `data.parquet` and `metadata.json` through Flysystem, and finalises the row. `GET /api/v4/snapshots/{id}` reads the row back.

**Tech Stack:** PHP 8.3, PostgreSQL/PostGIS, ogr2ogr (GDAL Parquet driver), `league/flysystem` + `league/flysystem-aws-s3-v3` (already in `app/vendor`), Codeception (unit + api suites), OpenAPI attributes.

**Spec:** `docs/superpowers/specs/2026-09-15-snapshot-api-design.md`

## Global Constraints

- All tests run inside the dev container `docker-dev-1`, repo mounted at `/var/www/geocloud2`. Unit: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit <File>`. API: `... run api <File>`.
- The API suite hits `http://localhost:80` inside the container. The web server serves the main checkout, so do not use a git worktree.
- Migrations live in `app/migration/Sql.php` (`Sql::get()`), are applied by `php app/migration/run.php` to every database including `template_geocloud`, and each statement is wrapped in try/catch so re-running is safe.
- Status vocabulary is exactly `pending`, `running`, `succeeded`, `failed`.
- Error codes: `INVALID_REQUEST`, `RELATION_NOT_FOUND`, `SNAPSHOT_IN_PROGRESS`, `SNAPSHOT_NOT_CONFIGURED`, `NO_SNAPSHOT_ERROR`.
- S3 partition key: `<prefix>/<database>/schema=<schema>/relation=<relation>/harvest_date=<YYYY-MM-DD>/` (prefix omitted when empty).
- Config: `App::$param['snapshot'] = ['bucket' => ..., 'prefix' => ..., 'region' => ...]`; credentials from `App::$param['s3']['id']` and `['secret']`.
- Commit messages end with the attribution lines the session-reminder specifies.
- Never commit `app/conf/App.php` (gitignored, contains real credentials).

---

## File map

| File | Responsibility |
|---|---|
| `app/migration/Sql.php` | DDL for `settings.snapshots` (append at the end of `get()`) |
| `app/models/Snapshot.php` (new) | Row access: create, hasActive, claimPending, finish, get, list |
| `app/inc/SnapshotWorker.php` (new) | Per-database job runner: ogr2ogr + upload + finalise |
| `app/scripts/snapshot_worker.php` (new) | Cron entrypoint: builds S3 filesystem, loops databases |
| `app/api/v4/controllers/Snapshot.php` (new) | HTTP: POST 202, GET by id, GET list, validation |
| `docker/Dockerfile` | Cron line for the worker |
| `docker/conf/gc2/App.php` | `snapshot` config block in the tracked template |
| `app/models/Sql.php` | Remove prototype S3 block, keep Parquet download |
| `app/tests/unit/SnapshotModelTest.php` (new) | Model behaviour against a provisioned database |
| `app/tests/unit/SnapshotWorkerTest.php` (new) | Worker end to end with a local Flysystem adapter |
| `app/tests/api/SnapshotV4ApiCest.php` (new) | HTTP contract |

---

### Task 1: Migration for `settings.snapshots`

**Files:**
- Modify: `app/migration/Sql.php` (append inside `get()` after the `function_event_queue` block, before the function returns)

**Interfaces:**
- Produces: table `settings.snapshots` with columns `uuid, schema_name, relation_name, srs, status, s3_path, row_count, error, username, created, started, finished`.

- [ ] **Step 1: Find the end of `get()`**

Run: `grep -n "return \$sqls" app/migration/Sql.php | head -1`
Expected: a line number inside `get()` (the first `return $sqls;`).

- [ ] **Step 2: Add the DDL just above that `return $sqls;`**

```php
        // Snapshots: async export of a relation to Parquet on S3 (see
        // app/inc/SnapshotWorker.php). The table is the queue.
        $sqls[] = "CREATE TABLE settings.snapshots
                    (
                      uuid          UUID                      NOT NULL  DEFAULT uuid_generate_v4()  PRIMARY KEY,
                      schema_name   TEXT                      NOT NULL,
                      relation_name TEXT                      NOT NULL,
                      srs           INTEGER,
                      status        CHARACTER VARYING(32)     NOT NULL  DEFAULT 'pending',
                      s3_path       TEXT,
                      row_count     BIGINT,
                      error         TEXT,
                      username      CHARACTER VARYING(255),
                      created       TIMESTAMP WITH TIME ZONE  NOT NULL  DEFAULT now(),
                      started       TIMESTAMP WITH TIME ZONE,
                      finished      TIMESTAMP WITH TIME ZONE,
                      CHECK (status IN ('pending', 'running', 'succeeded', 'failed'))
                    )";
        $sqls[] = "CREATE INDEX snapshots_pending_idx ON settings.snapshots (created) WHERE status = 'pending'";
        $sqls[] = "CREATE INDEX snapshots_relation_idx ON settings.snapshots (schema_name, relation_name)";
```

- [ ] **Step 3: Apply the migration to every database in the dev stack**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php migration/run.php | tail -5`
Expected: one line per database ending with `+++` for the three new statements (a `-` means the statement failed; for a fresh table that is a bug).

- [ ] **Step 4: Verify the template database got the table**

Run: `docker exec postgres psql -U mydb -d template_geocloud -tc "select to_regclass('settings.snapshots')"`
Expected: `settings.snapshots`

- [ ] **Step 5: Commit**

```bash
git add app/migration/Sql.php
git commit -m "feat(snapshot): add settings.snapshots queue table"
```

---

### Task 2: `Snapshot` model

**Files:**
- Create: `app/models/Snapshot.php`
- Test: `app/tests/unit/SnapshotModelTest.php`

**Interfaces:**
- Consumes: `settings.snapshots` from Task 1; `app\inc\Model` helpers `prepare`, `execute(PDOStatement, array)`, `fetchAll($res, 'assoc')`, `fetchRow`.
- Produces (used by Task 3 and Task 5):

```php
namespace app\models;
class Snapshot extends \app\inc\Model {
    public function create(string $schema, string $relation, ?int $srs, string $username): string; // uuid
    public function hasActive(string $schema, string $relation): bool;
    public function claimPending(int $limit = 2): array;   // rows, status flipped to running
    public function finish(string $uuid, string $status, ?string $s3Path, ?int $rowCount, ?string $error): void;
    public function get(string $uuid): array;              // ['success'=>true,'message'=>..,'data'=>row], throws 404
    public function list(?string $schema = null, ?string $relation = null, int $limit = 50): array; // rows newest first
}
```

- [ ] **Step 1: Write the failing test**

The test provisions a fresh super-user database through the v2 user API (same approach as `FunctionCallbackIntegrationTest`), because a fresh database is cloned from `template_geocloud` and therefore has `settings.snapshots`.

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\Connection;
use app\models\Snapshot;
use Codeception\Test\Unit;

/**
 * Integration test for the snapshot queue model. Provisions a fresh database
 * through the v2 user API (needs the in-container web server on localhost)
 * and skips cleanly when it cannot be reached.
 */
class SnapshotModelTest extends Unit
{
    protected UnitTester $tester;

    private const string API = 'http://localhost';
    private const string PASSWORD = 'A1abcabcabc';

    private static ?string $database = null;

    protected function _before(): void
    {
        if (self::$database !== null) {
            return;
        }
        $ts = (string)(new DateTime())->getTimestamp() . bin2hex(random_bytes(3));
        $created = $this->post('/api/v2/user', [
            'name' => 'Snapshot model test ' . $ts,
            'email' => 'snapmodel' . $ts . '@example.com',
            'password' => self::PASSWORD,
        ]);
        if ($created === null || empty($created['data']['screenname'])) {
            $this->markTestSkipped('GC2 API not reachable; skipping snapshot model test.');
        }
        self::$database = $created['data']['screenname'];
    }

    private function model(): Snapshot
    {
        return new Snapshot(new Connection(database: self::$database));
    }

    public function testCreateReturnsUuidAndGetReadsPendingRow(): void
    {
        $m = $this->model();
        $uuid = $m->create('public', 'roads', 25832, self::$database);
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);

        $row = $m->get($uuid)['data'];
        $this->assertSame('public', $row['schema_name']);
        $this->assertSame('roads', $row['relation_name']);
        $this->assertSame(25832, (int)$row['srs']);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['started']);
        $this->assertNull($row['finished']);
    }

    public function testCreateWithoutSrsStoresNull(): void
    {
        $m = $this->model();
        $uuid = $m->create('public', 'nosrs', null, self::$database);
        $this->assertNull($m->get($uuid)['data']['srs']);
    }

    public function testGetUnknownUuidThrows404(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->get('00000000-0000-0000-0000-000000000000');
    }

    public function testGetMalformedUuidThrows404NotDbError(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(404);
        $this->model()->get('not-a-uuid');
    }

    public function testHasActiveSeesPendingAndRunningButNotFinished(): void
    {
        $m = $this->model();
        $this->assertFalse($m->hasActive('public', 'active_' . __LINE__));

        $rel = 'active_rel';
        $uuid = $m->create('public', $rel, null, self::$database);
        $this->assertTrue($m->hasActive('public', $rel));

        $claimed = $m->claimPending(100);
        $this->assertContains($uuid, array_column($claimed, 'uuid'));
        $this->assertTrue($m->hasActive('public', $rel));

        $m->finish($uuid, 'succeeded', 's3://b/p/', 3, null);
        $this->assertFalse($m->hasActive('public', $rel));
    }

    public function testClaimPendingFlipsToRunningSetsStartedAndRespectsLimit(): void
    {
        $m = $this->model();
        // Drain anything left by other tests so the limit assertion is exact.
        $m->claimPending(1000);

        $a = $m->create('public', 'claim_a', null, self::$database);
        $b = $m->create('public', 'claim_b', null, self::$database);

        $first = $m->claimPending(1);
        $this->assertCount(1, $first);
        $this->assertSame($a, $first[0]['uuid'], 'oldest pending row is claimed first');
        $this->assertSame('running', $first[0]['status']);
        $this->assertNotNull($first[0]['started']);

        $second = $m->claimPending(1);
        $this->assertCount(1, $second);
        $this->assertSame($b, $second[0]['uuid']);

        $this->assertCount(0, $m->claimPending(1));
    }

    public function testFinishStoresOutcomeAndFinishedTimestamp(): void
    {
        $m = $this->model();
        $ok = $m->create('public', 'finish_ok', null, self::$database);
        $m->finish($ok, 'succeeded', 's3://bucket/x/', 42, null);
        $row = $m->get($ok)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('s3://bucket/x/', $row['s3_path']);
        $this->assertSame(42, (int)$row['row_count']);
        $this->assertNull($row['error']);
        $this->assertNotNull($row['finished']);

        $bad = $m->create('public', 'finish_bad', null, self::$database);
        $m->finish($bad, 'failed', null, null, 'ogr2ogr exploded');
        $row = $m->get($bad)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertSame('ogr2ogr exploded', $row['error']);
        $this->assertNull($row['s3_path']);
    }

    public function testListIsNewestFirstAndFilters(): void
    {
        $m = $this->model();
        $m->create('lst', 'one', null, self::$database);
        $newest = $m->create('lst', 'two', null, self::$database);

        $all = $m->list();
        $this->assertSame($newest, $all[0]['uuid']);

        $filtered = $m->list('lst', 'one');
        $this->assertCount(1, $filtered);
        $this->assertSame('one', $filtered[0]['relation_name']);

        $bySchema = $m->list('lst');
        $this->assertCount(2, $bySchema);

        $this->assertCount(1, $m->list(null, null, 1));
    }

    private function post(string $path, array $body): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($body),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents(self::API . $path, false, $ctx);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotModelTest.php`
Expected: FAIL/ERROR with `Class "app\models\Snapshot" not found`.

- [ ] **Step 3: Write the model**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\exceptions\GC2Exception;
use app\inc\Model;
use PDO;

/**
 * Queue and status records for asynchronous Parquet snapshots in
 * settings.snapshots. The table is the queue: POST inserts a 'pending' row,
 * the worker claims rows (FOR UPDATE SKIP LOCKED) and finalises them.
 */
class Snapshot extends Model
{
    /**
     * Inserts a pending snapshot request and returns its uuid.
     */
    public function create(string $schema, string $relation, ?int $srs, string $username): string
    {
        $sql = "INSERT INTO settings.snapshots (schema_name, relation_name, srs, username)
                VALUES (:schema, :relation, :srs, :username)
                RETURNING uuid";
        $res = $this->prepare($sql);
        $res->bindValue(':schema', $schema);
        $res->bindValue(':relation', $relation);
        $res->bindValue(':srs', $srs, $srs === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $res->bindValue(':username', $username);
        $this->execute($res);
        return $res->fetchColumn();
    }

    /**
     * True when a pending or running snapshot exists for the relation.
     */
    public function hasActive(string $schema, string $relation): bool
    {
        $sql = "SELECT EXISTS (
                    SELECT 1 FROM settings.snapshots
                    WHERE schema_name = :schema AND relation_name = :relation
                      AND status IN ('pending', 'running')
                ) AS active";
        $res = $this->prepare($sql);
        $this->execute($res, ['schema' => $schema, 'relation' => $relation]);
        return (bool)$res->fetchColumn();
    }

    /**
     * Atomically claims up to $limit pending rows (oldest first), flipping them
     * to 'running' with started = now(). SKIP LOCKED keeps concurrent workers
     * from claiming the same row.
     *
     * @return array<int, array<string, mixed>> The claimed rows.
     */
    public function claimPending(int $limit = 2): array
    {
        $sql = "UPDATE settings.snapshots u
                SET status = 'running', started = now()
                FROM (
                    SELECT uuid FROM settings.snapshots
                    WHERE status = 'pending'
                    ORDER BY created
                    LIMIT :limit
                    FOR UPDATE SKIP LOCKED
                ) sub
                WHERE u.uuid = sub.uuid
                RETURNING u.*";
        $res = $this->prepare($sql);
        $res->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $this->execute($res);
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * Finalises a row with its outcome.
     */
    public function finish(string $uuid, string $status, ?string $s3Path, ?int $rowCount, ?string $error): void
    {
        $sql = "UPDATE settings.snapshots
                SET status = :status, s3_path = :s3_path, row_count = :row_count, error = :error, finished = now()
                WHERE uuid = :uuid";
        $res = $this->prepare($sql);
        $res->bindValue(':uuid', $uuid);
        $res->bindValue(':status', $status);
        $res->bindValue(':s3_path', $s3Path, $s3Path === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $res->bindValue(':row_count', $rowCount, $rowCount === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $res->bindValue(':error', $error, $error === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $this->execute($res);
    }

    /**
     * Fetches one row. Compares on uuid::text so a malformed id is a 404, not
     * a database error.
     *
     * @throws GC2Exception 404 NO_SNAPSHOT_ERROR when the uuid is unknown.
     */
    public function get(string $uuid): array
    {
        $sql = "SELECT * FROM settings.snapshots WHERE uuid::text = :uuid";
        $res = $this->prepare($sql);
        $this->execute($res, ['uuid' => $uuid]);
        $row = $this->fetchRow($res);
        if (!$row) {
            throw new GC2Exception("No snapshot with that id", 404, null, "NO_SNAPSHOT_ERROR");
        }
        return [
            'success' => true,
            'message' => "Snapshot fetched",
            'data' => $row,
        ];
    }

    /**
     * Lists rows newest first, optionally narrowed to a schema and relation.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(?string $schema = null, ?string $relation = null, int $limit = 50): array
    {
        $where = [];
        $params = [];
        if ($schema !== null) {
            $where[] = "schema_name = :schema";
            $params['schema'] = $schema;
        }
        if ($relation !== null) {
            $where[] = "relation_name = :relation";
            $params['relation'] = $relation;
        }
        $sql = "SELECT * FROM settings.snapshots"
            . ($where ? " WHERE " . implode(" AND ", $where) : "")
            . " ORDER BY created DESC LIMIT :limit";
        $res = $this->prepare($sql);
        foreach ($params as $k => $v) {
            $res->bindValue(':' . $k, $v);
        }
        $res->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $this->execute($res);
        return $this->fetchAll($res, 'assoc');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotModelTest.php`
Expected: `OK (8 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add app/models/Snapshot.php app/tests/unit/SnapshotModelTest.php
git commit -m "feat(snapshot): add Snapshot queue model"
```

---

### Task 3: `SnapshotWorker`

**Files:**
- Create: `app/inc/SnapshotWorker.php`
- Test: `app/tests/unit/SnapshotWorkerTest.php`

**Interfaces:**
- Consumes: `app\models\Snapshot` (Task 2); `app\inc\Model::doesRelationExists(string)`, `Model::countRows(schema, table)`; `League\Flysystem\Filesystem`.
- Produces (used by Task 4):

```php
namespace app\inc;
class SnapshotWorker {
    public function __construct(Connection $connection, Filesystem $filesystem, string $bucket, string $prefix, string $tmpDir);
    public function processPending(int $limit = 2): array; // ['processed'=>int,'succeeded'=>int,'failed'=>int]
    public static function partitionKey(string $prefix, string $database, string $schema, string $relation, string $harvestDate): string;
}
```

- [ ] **Step 1: Write the failing test**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\Model;
use app\inc\SnapshotWorker;
use app\models\Snapshot;
use Codeception\Test\Unit;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

/**
 * Runs the snapshot worker end to end against a provisioned database with a
 * local Flysystem adapter standing in for S3. Needs ogr2ogr with the Parquet
 * driver and the in-container web server (both true in the dev container).
 */
class SnapshotWorkerTest extends Unit
{
    protected UnitTester $tester;

    private const string API = 'http://localhost';
    private const string PASSWORD = 'A1abcabcabc';

    private static ?string $database = null;
    private string $storeDir;
    private string $tmpDir;

    protected function _before(): void
    {
        if (self::$database === null) {
            $ts = (string)(new DateTime())->getTimestamp() . bin2hex(random_bytes(3));
            $created = $this->post('/api/v2/user', [
                'name' => 'Snapshot worker test ' . $ts,
                'email' => 'snapworker' . $ts . '@example.com',
                'password' => self::PASSWORD,
            ]);
            if ($created === null || empty($created['data']['screenname'])) {
                $this->markTestSkipped('GC2 API not reachable; skipping snapshot worker test.');
            }
            self::$database = $created['data']['screenname'];

            $m = new Model(new Connection(database: self::$database));
            $m->execQuery("CREATE SCHEMA snap", "PDO", "transaction");
            $m->execQuery("CREATE TABLE snap.points (gid serial PRIMARY KEY, name text, the_geom geometry(Point, 25832))", "PDO", "transaction");
            $m->execQuery("INSERT INTO snap.points (name, the_geom) VALUES
                ('a', ST_SetSRID(ST_MakePoint(500000, 6200000), 25832)),
                ('b', ST_SetSRID(ST_MakePoint(500100, 6200100), 25832)),
                ('c', ST_SetSRID(ST_MakePoint(500200, 6200200), 25832))", "PDO", "transaction");
            $m->execQuery("CREATE VIEW snap.points_view AS SELECT * FROM snap.points WHERE name <> 'c'", "PDO", "transaction");
        }
        $base = sys_get_temp_dir() . '/snapshot_worker_test_' . bin2hex(random_bytes(4));
        $this->storeDir = $base . '/store';
        $this->tmpDir = $base . '/tmp';
        mkdir($this->storeDir, 0777, true);
    }

    protected function _after(): void
    {
        $this->rmrf(dirname($this->storeDir));
    }

    private function worker(string $prefix = 'unit'): SnapshotWorker
    {
        return new SnapshotWorker(
            new Connection(database: self::$database),
            new Filesystem(new LocalFilesystemAdapter($this->storeDir)),
            'test-bucket',
            $prefix,
            $this->tmpDir,
        );
    }

    private function snapshot(): Snapshot
    {
        return new Snapshot(new Connection(database: self::$database));
    }

    public function testPartitionKeyLayout(): void
    {
        $this->assertSame(
            'prod/mydb/schema=geodanmark/relation=bygning/harvest_date=2026-09-15/',
            SnapshotWorker::partitionKey('prod', 'mydb', 'geodanmark', 'bygning', '2026-09-15')
        );
        $this->assertSame(
            'mydb/schema=s/relation=r/harvest_date=2026-09-15/',
            SnapshotWorker::partitionKey('', 'mydb', 's', 'r', '2026-09-15'),
            'empty prefix is omitted'
        );
        $this->assertSame(
            'p/mydb/schema=s/relation=r/harvest_date=2026-09-15/',
            SnapshotWorker::partitionKey('/p/', 'mydb', 's', 'r', '2026-09-15'),
            'prefix slashes are normalised'
        );
    }

    public function testTableSnapshotSucceedsAndWritesParquetAndMetadata(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points', null, self::$database);
        $summary = $this->worker()->processPending(5);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=points/harvest_date=' . gmdate('Y-m-d') . '/';
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data.parquet');
        $this->assertGreaterThan(0, filesize($this->storeDir . '/' . $partition . 'data.parquet'));
        $this->assertFileExists($this->storeDir . '/' . $partition . 'metadata.json');

        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata.json'), true);
        $this->assertSame($uuid, $meta['snapshot_id']);
        $this->assertSame(self::$database, $meta['database']);
        $this->assertSame('snap.points', $meta['source']);
        $this->assertSame(3, $meta['row_count']);
        $this->assertSame('EPSG:25832', $meta['crs'], 'native SRID used when no srs is requested');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $meta['schema_version']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $meta['harvested_at']);

        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('s3://test-bucket/' . $partition, $row['s3_path']);
        $this->assertSame(3, (int)$row['row_count']);
        $this->assertNotNull($row['finished']);

        $this->assertFileDoesNotExist($this->tmpDir . '/' . $uuid . '.parquet', 'tmp file is removed');
    }

    public function testViewSnapshotWithRequestedSrsReportsThatCrs(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points_view', 4326, self::$database);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=points_view/harvest_date=' . gmdate('Y-m-d') . '/';
        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata.json'), true);
        $this->assertSame(2, $meta['row_count']);
        $this->assertSame('EPSG:4326', $meta['crs']);
    }

    public function testMissingRelationFailsRowWithError(): void
    {
        $uuid = $this->snapshot()->create('snap', 'does_not_exist', null, self::$database);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['failed']);

        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertStringContainsString('does_not_exist', $row['error']);
        $this->assertNull($row['s3_path']);
        $this->assertNotNull($row['finished']);
        $this->assertFileDoesNotExist($this->tmpDir . '/' . $uuid . '.parquet');
    }

    public function testNothingPendingIsANoop(): void
    {
        $this->worker()->processPending(5); // drain
        $this->assertSame(['processed' => 0, 'succeeded' => 0, 'failed' => 0], $this->worker()->processPending(5));
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    private function post(string $path, array $body): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($body),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents(self::API . $path, false, $ctx);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotWorkerTest.php`
Expected: ERROR with `Class "app\inc\SnapshotWorker" not found`.

- [ ] **Step 3: Write the worker**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use app\models\Snapshot as SnapshotModel;
use League\Flysystem\Filesystem;
use RuntimeException;
use Throwable;

/**
 * Runs queued snapshots for one database: claims pending rows in
 * settings.snapshots, exports each relation to Parquet with ogr2ogr, uploads
 * data.parquet + metadata.json through Flysystem, and finalises the row.
 *
 * The Filesystem is injected so production can use S3 and tests a local
 * directory. Relation lookups use uncached catalog queries so a relation
 * created moments ago is seen.
 */
class SnapshotWorker
{
    private SnapshotModel $snapshot;
    private Model $model;

    public function __construct(
        private readonly Connection $connection,
        private readonly Filesystem $filesystem,
        private readonly string     $bucket,
        private readonly string     $prefix,
        private readonly string     $tmpDir,
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

    /**
     * S3 key prefix for one snapshot partition (always ends with '/').
     */
    public static function partitionKey(string $prefix, string $database, string $schema, string $relation, string $harvestDate): string
    {
        $prefix = trim($prefix, '/');
        return ($prefix !== '' ? $prefix . '/' : '')
            . "$database/schema=$schema/relation=$relation/harvest_date=$harvestDate/";
    }

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
        try {
            if (!$this->model->doesRelationExists("$schema.$relation")) {
                throw new RuntimeException("Relation $schema.$relation does not exist");
            }
            $crs = $srs ?? $this->nativeSrid($schema, $relation);
            $schemaVersion = md5(json_encode($this->columns($schema, $relation)));

            if (!is_dir($this->tmpDir) && !mkdir($this->tmpDir, 0775, true) && !is_dir($this->tmpDir)) {
                throw new RuntimeException("Could not create tmp dir {$this->tmpDir}");
            }
            $this->export($schema, $relation, $srs, $tmpFile);

            $rowCount = (int)$this->model->countRows($schema, $relation)['data'];
            $partition = self::partitionKey($this->prefix, $this->connection->database, $schema, $relation, gmdate('Y-m-d'));

            $stream = fopen($tmpFile, 'rb');
            if ($stream === false) {
                throw new RuntimeException("Could not open $tmpFile");
            }
            try {
                $this->filesystem->writeStream($partition . 'data.parquet', $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }
            $this->filesystem->write($partition . 'metadata.json', json_encode([
                'snapshot_id' => $uuid,
                'harvested_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'database' => $this->connection->database,
                'source' => "$schema.$relation",
                'row_count' => $rowCount,
                'schema_version' => $schemaVersion,
                'crs' => $crs !== null ? "EPSG:$crs" : null,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $this->snapshot->finish($uuid, 'succeeded', "s3://{$this->bucket}/$partition", $rowCount, null);
            return 'succeeded';
        } catch (Throwable $e) {
            $this->snapshot->finish($uuid, 'failed', null, null, $e->getMessage());
            return 'failed';
        } finally {
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    /**
     * Exports the relation to a Parquet file with ogr2ogr. Reprojects only
     * when $srs is given.
     */
    private function export(string $schema, string $relation, ?int $srs, string $tmpFile): void
    {
        $c = $this->connection;
        $pg = "PG:host={$c->host} port={$c->port} user={$c->user} password={$c->password} dbname={$c->database}";
        $cmd = 'ogr2ogr -mapFieldType Time=String,Binary=String -f Parquet ' . escapeshellarg($tmpFile)
            . ($srs !== null ? ' -t_srs ' . escapeshellarg("EPSG:$srs") : '')
            . ' -preserve_fid '
            . escapeshellarg($pg)
            . ' -sql ' . escapeshellarg("SELECT * FROM \"$schema\".\"$relation\"")
            . ' 2>&1';
        $out = [];
        $code = 0;
        exec($cmd, $out, $code);
        if ($code !== 0 || preg_grep('/ERROR/', $out)) {
            throw new RuntimeException("ogr2ogr failed: " . implode("\n", $out));
        }
        if (!file_exists($tmpFile)) {
            throw new RuntimeException("ogr2ogr produced no output file");
        }
    }

    /**
     * SRID of the relation's first geometry column, or null when it has none.
     */
    private function nativeSrid(string $schema, string $relation): ?int
    {
        $sql = "SELECT srid FROM geometry_columns
                WHERE f_table_schema = :schema AND f_table_name = :relation
                ORDER BY f_geometry_column LIMIT 1";
        $res = $this->model->prepare($sql);
        $this->model->execute($res, ['schema' => $schema, 'relation' => $relation]);
        $srid = $res->fetchColumn();
        return $srid === false || $srid === null ? null : (int)$srid;
    }

    /**
     * Column names and types in ordinal order, the input to schema_version.
     *
     * @return array<int, array{column_name:string, udt_name:string}>
     */
    private function columns(string $schema, string $relation): array
    {
        $sql = "SELECT column_name, udt_name FROM information_schema.columns
                WHERE table_schema = :schema AND table_name = :relation
                ORDER BY ordinal_position";
        $res = $this->model->prepare($sql);
        $this->model->execute($res, ['schema' => $schema, 'relation' => $relation]);
        return $this->model->fetchAll($res, 'assoc');
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotWorkerTest.php`
Expected: `OK (5 tests, ...)`. If the table test fails with an ogr2ogr message, print it (the assertion includes `error:`), fix the command, rerun.

- [ ] **Step 5: Commit**

```bash
git add app/inc/SnapshotWorker.php app/tests/unit/SnapshotWorkerTest.php
git commit -m "feat(snapshot): add SnapshotWorker (ogr2ogr Parquet export + Flysystem upload)"
```

---

### Task 4: Worker script, cron line, config template

**Files:**
- Create: `app/scripts/snapshot_worker.php`
- Modify: `docker/Dockerfile` (crontab block, after the `function_worker.php` line at about line 362)
- Modify: `docker/conf/gc2/App.php` (add `snapshot` block after `appCache`)
- Modify (local only, not committed): `app/conf/App.php` (same block)

**Interfaces:**
- Consumes: `SnapshotWorker` (Task 3), `App::$param['snapshot']`, `App::$param['s3']`.

- [ ] **Step 1: Add the config block to the tracked template**

In `docker/conf/gc2/App.php`, directly after the `"appCache" => [ ... ],` block:

```php
        // Parquet snapshots to S3 (POST /api/v4/snapshots). Credentials are read
        // from the "s3" block (id/secret). Leave "bucket" empty to disable.
        "snapshot" => [
            "bucket" => "",
            "prefix" => "",
            "region" => "eu-west-1",
        ],
```

Add the same block to the local `app/conf/App.php` with the real bucket (`gc2-parquet`) and a prefix such as `dev`. Do not commit that file.

- [ ] **Step 2: Write the script**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Background worker for Parquet snapshots. Drains pending rows in
 * settings.snapshots across all databases, exporting each relation with
 * ogr2ogr and uploading to S3.
 *
 *   * * * * * php -f /var/www/geocloud2/app/scripts/snapshot_worker.php
 *
 * Optional first argument limits the run to one database.
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\SnapshotWorker;
use app\models\Database;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

new App();
Cache::setInstance();

$bucket = App::$param['snapshot']['bucket'] ?? '';
if ($bucket === '') {
    echo "SNAPSHOT WORKER: snapshot.bucket is not configured, nothing to do\n";
    exit(0);
}
$prefix = App::$param['snapshot']['prefix'] ?? '';
$region = App::$param['snapshot']['region'] ?? 'eu-west-1';

$batchPerDb = (int)(getenv('GC2_SNAPSHOT_BATCH') ?: 2);
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template', 'template_geocloud', 'mapcentia', 'gc2scheduler'];
$only = $argv[1] ?? null;

$filesystem = new Filesystem(new AwsS3V3Adapter(new S3Client([
    'credentials' => [
        'key' => App::$param['s3']['id'],
        'secret' => App::$param['s3']['secret'],
    ],
    'region' => $region,
    'version' => 'latest',
]), $bucket));

$dbs = $only ? [$only] : new Database()->listAllDbs()['data'];
$totals = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

echo "SNAPSHOT WORKER START: " . date('Y-m-d H:i:s') . "\n";

foreach ($dbs as $db) {
    if (in_array($db, $skip, true)) {
        continue;
    }
    try {
        $tmpDir = App::$param['path'] . "app/tmp/$db/__snapshots";
        $summary = new SnapshotWorker(new Connection(database: $db), $filesystem, $bucket, $prefix, $tmpDir)
            ->processPending($batchPerDb);
        if ($summary['processed'] > 0) {
            echo "$db: processed={$summary['processed']} ok={$summary['succeeded']} failed={$summary['failed']}\n";
            foreach ($totals as $k => $v) {
                $totals[$k] += $summary[$k];
            }
        }
    } catch (Throwable $e) {
        // Databases without settings.snapshots (or transient errors) are
        // skipped; this worker is best-effort per run.
        echo "$db: skipped ({$e->getMessage()})\n";
    }
}

echo "TOTAL: processed={$totals['processed']} ok={$totals['succeeded']} failed={$totals['failed']}\n";
```

- [ ] **Step 3: Add the cron line to the Dockerfile**

Directly after the line that installs `function_worker.php` in crontab (search for `function_worker.php` in `docker/Dockerfile`):

```dockerfile
# Parquet snapshots: export queued relations to S3 (see app/scripts/snapshot_worker.php).
RUN crontab -l 2>/dev/null | { cat; echo "* * * * * sudo -u www-data php -f /var/www/geocloud2/app/scripts/snapshot_worker.php > /proc/1/fd/1 2>&1"; } | crontab
```

- [ ] **Step 4: Smoke-run the script inside the dev container**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 sudo -u www-data php -f scripts/snapshot_worker.php mydb`
Expected: either `SNAPSHOT WORKER: snapshot.bucket is not configured, nothing to do` (if the local App.php has no bucket) or `SNAPSHOT WORKER START: ...` followed by `TOTAL: processed=0 ok=0 failed=0`. No PHP fatal.

- [ ] **Step 5: Commit**

```bash
git add app/scripts/snapshot_worker.php docker/Dockerfile docker/conf/gc2/App.php
git commit -m "feat(snapshot): add snapshot_worker cron script and config"
```

---

### Task 5: `Snapshot` v4 controller

**Files:**
- Create: `app/api/v4/controllers/Snapshot.php`
- Test: `app/tests/api/SnapshotV4ApiCest.php`

**Interfaces:**
- Consumes: `app\models\Snapshot` (Task 2), `Model::doesRelationExists`, `App::$param['snapshot']['bucket']`, `AcceptedResponse`, `AbstractApi::getResponse`, `validateRequest`.
- Produces: routes `POST /api/v4/snapshots`, `GET /api/v4/snapshots`, `GET /api/v4/snapshots/{id}`.

- [ ] **Step 1: Write the failing API test**

```php
<?php

use Codeception\Util\HttpCode;

/**
 * HTTP contract of the v4 Snapshot API (app/api/v4/controllers/Snapshot.php).
 * Queues snapshots and reads their status; never waits for the worker.
 * Ordered/stateful: the prepare test provisions users and a table.
 */
class SnapshotV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subToken;
    private $schema;
    private $snapshotId;

    public function __construct()
    {
        $this->schema = 'snap_' . (new DateTime())->getTimestamp();
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUsersAndTable(ApiTester $I)
    {
        $ts = (new DateTime())->getTimestamp();

        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'snapshot super ' . $ts,
            'email' => 'snapsuper' . $ts . '@example.com',
            'password' => $this->password,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        // Sub user via the super user's session
        $I->sendPOST('/api/v2/session/start', json_encode([
            'user' => $this->userId, 'password' => $this->password, 'schema' => 'public',
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sessionCookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $sessionCookie);
        $I->sendPOST('/api/v2/user', json_encode([
            'name' => 'snapshot sub ' . $ts,
            'email' => 'snapsub' . $ts . '@example.com',
            'password' => $this->password,
            'subuser' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $subUserId = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');

        $I->sendPOST('/api/v4/oauth', json_encode([
            'grant_type' => 'password', 'username' => $subUserId, 'password' => $this->password,
            'database' => $this->userId, 'client_id' => 'gc2-cli',
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->subToken = json_decode($I->grabResponse())->access_token;

        // Schema + table to snapshot
        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode([
            'name' => 'poi',
            'columns' => [
                ['name' => 'gid', 'type' => 'serial'],
                ['name' => 'name', 'type' => 'varchar'],
                ['name' => 'the_geom', 'type' => 'geometry(Point,4326)'],
            ],
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function shouldQueueSnapshotAndReturn202(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi', 'srs' => 25832]));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'pending']);
        $body = json_decode($I->grabResponse());
        $this->snapshotId = $body->id;
        $I->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $this->snapshotId);
        $I->assertEquals('/api/v4/snapshots/' . $this->snapshotId, $body->_links->self);
    }

    public function shouldReadSnapshotById(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots/' . $this->snapshotId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => $this->snapshotId,
            'schema' => $this->schema,
            'relation' => 'poi',
            'srs' => 25832,
            'username' => $this->userId,
        ]);
        $body = json_decode($I->grabResponse());
        $I->assertContains($body->status, ['pending', 'running', 'succeeded', 'failed']);
        $I->assertTrue(property_exists($body, 's3_path'));
        $I->assertTrue(property_exists($body, 'row_count'));
        $I->assertTrue(property_exists($body, 'error'));
        $I->assertTrue(property_exists($body, 'created'));
        $I->assertTrue(property_exists($body, 'started'));
        $I->assertTrue(property_exists($body, 'finished'));
    }

    public function shouldListSnapshotsWithFilter(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->snapshotId]]);

        $I->sendGET('/api/v4/snapshots?schema=' . $this->schema . '&relation=poi');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->snapshotId]]);

        $I->sendGET('/api/v4/snapshots?schema=' . $this->schema . '&relation=nothing_here');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals([], json_decode($I->grabResponse()));
    }

    public function shouldRejectSecondSnapshotWhileFirstIsActive(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        // The worker may already have finished the first one in a dev stack with cron; accept both outcomes.
        $code = $I->grabResponse();
        $status = json_decode($code);
        if (isset($status->status) && $status->status === 'pending') {
            $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        } else {
            $I->seeResponseCodeIs(HttpCode::CONFLICT);
            $I->seeResponseContainsJson(['errorCode' => 'SNAPSHOT_IN_PROGRESS']);
        }
    }

    public function shouldReturn404ForUnknownRelation(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'nope']));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'RELATION_NOT_FOUND']);
    }

    public function shouldReturn400OnBadBody(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi', 'srs' => 'abc']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);

        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'po"i']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldReturn404ForUnknownSnapshotId(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/snapshots/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'NO_SNAPSHOT_ERROR']);

        $I->sendGET('/api/v4/snapshots/not-a-uuid');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldRejectPostWithResourceId(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/snapshots/' . $this->snapshotId, json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        $I->seeResponseCodeIs(HttpCode::NOT_ACCEPTABLE);
    }

    public function shouldRejectSubUser(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
        $I->sendPOST('/api/v4/snapshots', json_encode(['schema' => $this->schema, 'relation' => 'poi']));
        $I->seeResponseCodeIsClientError();
        $I->sendGET('/api/v4/snapshots');
        $I->seeResponseCodeIsClientError();
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SnapshotV4ApiCest.php`
Expected: prepare test passes; `shouldQueueSnapshotAndReturn202` fails with 404 (no route).

- [ ] **Step 3: Write the controller**

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
        new OA\Property(property: "status", type: "string", enum: ["pending", "running", "succeeded", "failed"]),
        new OA\Property(property: "s3_path", type: "string", example: "s3://gc2-parquet/prod/mydb/schema=geodanmark/relation=bygning/harvest_date=2026-09-15/", nullable: true),
        new OA\Property(property: "row_count", type: "integer", example: 123456, nullable: true),
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
            's3_path' => $row['s3_path'],
            'row_count' => $row['row_count'] !== null ? (int)$row['row_count'] : null,
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
    #[OA\Get(path: '/api/v4/snapshots/{id}', operationId: 'getSnapshot', description: "Get a snapshot job, or list the newest jobs.", tags: ['Snapshots'])]
    #[OA\Parameter(name: 'id', description: 'Snapshot id', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'schema', description: 'List filter: schema', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'relation', description: 'List filter: relation', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/Snapshot"))]
    #[OA\Response(response: 404, description: 'Not found')]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $id = $this->route->getParam('id');
        if (!empty($id)) {
            $row = $this->snapshot->get($id)['data'];
            return $this->getResponse([$this->present($row)], single: true);
        }
        $schema = isset($_GET['schema']) && $_GET['schema'] !== '' ? (string)$_GET['schema'] : null;
        $relation = isset($_GET['relation']) && $_GET['relation'] !== '' ? (string)$_GET['relation'] : null;
        $rows = $this->snapshot->list($schema, $relation);
        return $this->getResponse(array_map(fn($r) => $this->present($r), $rows));
    }

    /**
     * @throws GC2Exception
     */
    #[OA\Post(path: '/api/v4/snapshots', operationId: 'postSnapshot', description: "Queue a Parquet snapshot of a table or view to S3. Returns 202; poll the returned link for status.", tags: ['Snapshots'])]
    #[OA\RequestBody(description: 'Relation to snapshot.', required: true, content: new OA\JsonContent(ref: "#/components/schemas/SnapshotRequest"))]
    #[OA\Response(response: 202, description: 'Accepted; poll _links.self for status')]
    #[OA\Response(response: 400, description: 'Bad request')]
    #[OA\Response(response: 404, description: 'Relation not found')]
    #[OA\Response(response: 409, description: 'A snapshot of this relation is already pending or running')]
    #[OA\Response(response: 501, description: 'Snapshot storage is not configured')]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        if (empty(App::$param['snapshot']['bucket'])) {
            throw new GC2Exception("Snapshot storage is not configured on this server", 501, null, "SNAPSHOT_NOT_CONFIGURED");
        }
        $body = json_decode(Input::getBody(), true);
        $schema = (string)$body['schema'];
        $relation = (string)$body['relation'];
        $srs = isset($body['srs']) ? (int)$body['srs'] : null;
        $uid = $this->route->jwt["data"]["uid"];

        if (!new Model($this->connection)->doesRelationExists("$schema.$relation")) {
            throw new GC2Exception("Relation $schema.$relation does not exist", 404, null, "RELATION_NOT_FOUND");
        }
        if ($this->snapshot->hasActive($schema, $relation)) {
            throw new GC2Exception("A snapshot of $schema.$relation is already pending or running", 409, null, "SNAPSHOT_IN_PROGRESS");
        }
        $id = $this->snapshot->create($schema, $relation, $srs, $uid);
        return new AcceptedResponse([
            'id' => $id,
            'status' => 'pending',
            '_links' => ['self' => "/api/v4/snapshots/$id"],
        ]);
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
            $this->validateRequest(self::getAssert(), Input::getBody(), $method);
        }
    }

    /**
     * Names must be safe for the S3 key and the quoted SQL identifier: no
     * quotes, slashes or whitespace.
     */
    static public function getAssert(): Assert\Collection
    {
        $name = [new Assert\Type('string'), new Assert\NotBlank(), new Assert\Regex('/^[^"\/\\\\\s]+$/')];
        return new Assert\Collection([
            'schema' => new Assert\Required($name),
            'relation' => new Assert\Required($name),
            'srs' => new Assert\Optional([new Assert\Type('integer'), new Assert\Positive()]),
        ]);
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SnapshotV4ApiCest.php`
Expected: `OK (10 tests, ...)`. If the POST returns 501, the local `app/conf/App.php` is missing `snapshot.bucket` (Task 4 step 1). If the 400 test fails on the `srs: "abc"` case, check that `validateRequest` decodes JSON strings as PHP strings (it does) so `Assert\Type('integer')` rejects them.

- [ ] **Step 5: Commit**

```bash
git add app/api/v4/controllers/Snapshot.php app/tests/api/SnapshotV4ApiCest.php
git commit -m "feat(api): add async POST/GET /api/v4/snapshots"
```

---

### Task 6: Remove the prototype from `Sql.php`

**Files:**
- Modify: `app/models/Sql.php` (imports at the top; the `NO_ZIP_FORMATS` branch around lines 167-210)

**Interfaces:**
- Consumes nothing new. Keeps `ogr/Parquet` as a single-file download in the SQL API.

- [ ] **Step 1: Remove the S3 block**

Replace everything from `if (in_array($format, self::NO_ZIP_FORMATS)) {` through the `readfile($path);` of that branch with:

```php
            if (in_array($format, self::NO_ZIP_FORMATS)) {
                $contentType = $format == "ogr/GPX" ? "application/gpx, application/octet-stream" : "application/octet-stream";
                header("Content-type: $contentType");
                header("Content-Disposition: attachment; filename=\"$fileOrFolder\"");
                readfile($path);
            } else {
```

(The `} else {` is the existing zip branch; keep its body unchanged.)

- [ ] **Step 2: Remove the prototype constants and imports**

Delete these lines near the top of the file:

```php
use app\api\v4\controllers\Column;
use League\Flysystem\FilesystemException;
use Throwable;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

const S3_FOLDER = "test";
```

And change the docblock of `sql()` back to:

```php
     * @throws Exception
     * @throws GC2Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
```

Keep `private const array NO_ZIP_FORMATS = ['ogr/GPX', 'ogr/Parquet'];` and the reformatted parameter list.

- [ ] **Step 3: Verify nothing else in the file referenced the removed imports**

Run: `grep -n "S3Client\|AwsS3V3Adapter\|Filesystem\|S3_FOLDER\|Column::\|Throwable" app/models/Sql.php`
Expected: no output.

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php -l models/Sql.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Run the SQL API suite to confirm nothing regressed**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SqlJsonFormatApiCest.php`
Expected: all tests pass.

- [ ] **Step 5: Commit**

```bash
git add app/models/Sql.php
git commit -m "refactor(sql): drop S3 snapshot prototype, keep ogr/Parquet as plain download"
```

---

### Task 7: Full verification

**Files:** none new.

- [ ] **Step 1: Run the new unit tests together**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotModelTest.php && docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SnapshotWorkerTest.php`
Expected: both `OK`.

- [ ] **Step 2: Run the new API suite together with the function suite (shares the migration file)**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SnapshotV4ApiCest.php && docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api FunctionManagementCest.php`
Expected: both `OK`.

- [ ] **Step 3: End-to-end against real S3 (only if the local App.php has a bucket)**

Get a super-user token for a database that has a table you can export (replace DB, PW, SCHEMA, RELATION), queue a snapshot, run the worker once, and read the status:

```bash
TOKEN=$(curl -s -X POST http://localhost:8080/api/v4/oauth -H 'Content-Type: application/json' \
  -d '{"grant_type":"password","username":"DB","password":"PW","database":"DB","client_id":"gc2-cli"}' | jq -r .access_token)
ID=$(curl -s -X POST http://localhost:8080/api/v4/snapshots -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"schema":"SCHEMA","relation":"RELATION"}' | jq -r .id)
docker exec -w /var/www/geocloud2/app docker-dev-1 sudo -u www-data php -f scripts/snapshot_worker.php DB
curl -s http://localhost:8080/api/v4/snapshots/$ID -H "Authorization: Bearer $TOKEN" | jq
```

Note: the running dev container was built before the cron line existed, so nothing runs the worker automatically until the image is rebuilt. That is why the API tests never wait for the worker.

Expected: `<database>: processed=1 ok=1 failed=0`, and `GET /api/v4/snapshots/<id>` shows `succeeded` with an `s3_path`. If the run reports `failed`, the `error` field in the GET response carries the ogr2ogr or S3 message.

- [ ] **Step 4: Review the diff once as a whole**

Run: `git log --oneline master@{u}..HEAD 2>/dev/null || git log --oneline -7`
Expected: the six feature commits from Tasks 1 to 6, plus the spec commit.
