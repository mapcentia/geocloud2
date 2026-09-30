# Tile seeder v4 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Turn tile seeding into a queued job that any GC2 node can start, watch and cancel, and retire v3's `pgrep`/`kill -9` approach behind a shim.

**Architecture:** `POST /api/v4/tileseeder/jobs` validates and writes a `pending` row in the tenant's `settings.seed_jobs`, answering 202. A cron tick (`seed_worker.php`) claims rows with `FOR UPDATE SKIP LOCKED` and spawns a detached `seed_run.php` per job; that process runs `mapcache_seed`, heartbeats, copies a log tail into the row, watches a cancel flag and finalises its own row (also from a shutdown hook). Because every fact lives in the row, status, log and cancel work from any node.

**Tech Stack:** PHP 8.4, PostgreSQL/PostGIS, MapServer MapCache (`mapcache_seed`), Codeception (unit + api suites), OpenAPI attributes (`swagger.php`).

**Spec:** `docs/superpowers/specs/2026-09-30-tileseeder-v4-design.md`

## Global Constraints

- Every v4 controller takes its `Connection` from the constructor and threads it into every model; never `Database::setDb()`, never `\app\conf\Connection::$param` (`AGENTS.md` §4).
- `SeedJob::STALE_RUNNING_INTERVAL = '10 minutes'`; heartbeat interval 5 seconds.
- Status vocabulary, exactly these five strings: `pending`, `running`, `succeeded`, `failed`, `cancelled`.
- Config block `App::$param['tileseeder']` with defaults read in code: `maxConcurrent` 1, `maxThreads` 4, `maxPending` 20, `maxHours` 12, `cancelGraceSeconds` 10, `logTailBytes` 8192, `keepLogHours` 72, `seedBinary` `/usr/local/bin/mapcache_seed`.
- The PostgreSQL password goes in the child's environment as `PGPASSWORD`, never in `argv`.
- Errors are `GC2Exception($message, $httpCode, null, 'ERROR_CODE')` with SCREAMING_SNAKE codes; lists are bare JSON arrays; field names `snake_case`.
- Run tests inside the dev container: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit <Name>` (and `run api <Name>`). One suite per command.
- Commit only when a task says so. Never push.

## Review Focus

Five conditions the spec implies that no task's happy path exercises. Each has its test added to the task that owns the code.

1. **A tileset or grid name carrying shell metacharacters or `../`** — validation must reject it with 400 before any process starts, and `SeedCommand` must never produce it as an unescaped argument. *(Task 2, step 1)*
2. **Two ticks claiming the same pending row at once** — `FOR UPDATE SKIP LOCKED` must hand it to exactly one; the loser claims nothing rather than duplicating the seed. *(Task 1, step 6)*
3. **A `seed_run.php` killed hard (SIGKILL, OOM, node death)** leaves the row `running` forever unless the reaper finalises it as `failed` after the stale window. *(Task 5, step 1)*
4. **Legacy rows** written by the old v3 code and by `MapcacheTileset` have no `status`: they must be reported as `status: null`, never claimed, and never cancelled. *(Task 1, step 9 and Task 3, step 7)*
5. **Cancel arriving between claim and spawn**, i.e. the row is `running` with no child yet — the run must still end as `cancelled` instead of ignoring the flag or hanging. *(Task 4, step 2, `testACancelRequestStopsTheChildAndEndsCancelled`)*

---

## File structure

| File | Responsibility |
|---|---|
| `app/migration/Sql.php` (modify) | The `settings.seed_jobs` columns and index |
| `app/models/SeedJob.php` (create) | The queue: insert, read, list, claim, heartbeat, cancel, finalise, reap, and the API presenter |
| `app/inc/tileseeder/SeedCommand.php` (create) | Validating value object that builds `argv`/`env` for `mapcache_seed` |
| `app/api/v4/controllers/Tileseeder.php` (create) | The v4 resource: POST/GET/DELETE, authorization, OpenAPI |
| `app/scripts/seed_run.php` (create) | Runs one job: pid, heartbeat, log tail, cancel watch, finalise |
| `app/scripts/seed_worker.php` (create) | Cron tick: reap, claim, spawn |
| `app/api/v3/Tileseeder.php` (rewrite) | Shim over the queue, same response shapes |
| `docker/conf/gc2/App.php`, `docker/Dockerfile` (modify) | Config block and the cron line |
| `app/tests/unit/SeedJobTest.php`, `SeedCommandTest.php`, `SeedRunTest.php`, `SeedWorkerTest.php` (create) | Unit coverage |
| `app/tests/api/TileseederV4ApiCest.php`, `TileseederV3ShimApiCest.php` (create) | API coverage |

---

### Task 1: The queue — migration and `SeedJob` model

**Files:**
- Modify: `app/migration/Sql.php` (in `get()`, next to the other `settings.*` ALTERs)
- Create: `app/models/SeedJob.php`
- Test: `app/tests/unit/SeedJobTest.php`

**Interfaces:**
- Consumes: `app\inc\Model` (`prepare`, `execute`, `fetchRow`, `fetchAll`, `withTransaction`), `app\inc\Connection`.
- Produces:
  ```php
  final class SeedJob extends Model {
      public const string STALE_RUNNING_INTERVAL = '10 minutes';
      public function queue(array $data): array;          // returns the inserted row
      public function get(string $uuid): ?array;
      public function list(?string $status = null, ?string $tileset = null, ?string $username = null): array;
      public function claimOne(): ?array;                  // pending -> running, SKIP LOCKED
      public function heartbeat(string $uuid, ?string $logTail = null): void;
      public function setPid(string $uuid, int $pid, ?string $logPath): void;
      public function isCancelRequested(string $uuid): bool;
      public function requestCancel(string $uuid): string; // 'cancelled' | 'cancelling' | 'noop'
      public function finish(string $uuid, string $status, ?string $error, ?string $logTail): void;
      public function reapStale(): int;                    // running w/o heartbeat -> failed
      public static function present(array $row, bool $withLog = false): array;
  }
  ```

- [ ] **Step 1: Write the migration**

In `app/migration/Sql.php`, in `public static function get(): array`, immediately before `include 'Views1.php';`:

```php
        // The tile seeder queue (docs/superpowers/specs/2026-09-30-tileseeder-v4-design.md):
        // a seed is a row a worker claims, so status, log and cancel work from any node.
        // pid and host are only known once a worker has claimed the row.
        $sqls[] = "ALTER TABLE settings.seed_jobs ALTER COLUMN pid DROP NOT NULL";
        $sqls[] = "ALTER TABLE settings.seed_jobs ALTER COLUMN host DROP NOT NULL";
        foreach ([
            'status' => 'VARCHAR(16)', 'username' => 'VARCHAR(255)', 'tileset' => 'VARCHAR(255)',
            'grid' => 'VARCHAR(255)', 'zoom_start' => 'SMALLINT', 'zoom_end' => 'SMALLINT',
            'extent_layer' => 'VARCHAR(255)', 'threads' => 'SMALLINT',
            'started' => 'TIMESTAMPTZ', 'finished' => 'TIMESTAMPTZ', 'heartbeat' => 'TIMESTAMPTZ',
            'cancel_requested' => 'TIMESTAMPTZ', 'error' => 'TEXT', 'log' => 'TEXT', 'log_path' => 'TEXT',
        ] as $col => $type) {
            $sqls[] = "ALTER TABLE settings.seed_jobs ADD COLUMN IF NOT EXISTS $col $type";
        }
        $sqls[] = "CREATE INDEX IF NOT EXISTS seed_jobs_status_created_idx ON settings.seed_jobs (status, created)";
```

- [ ] **Step 2: Apply it and confirm the columns**

Run:
```bash
docker exec -w /var/www/geocloud2/app/migration docker-dev-1 php run.php > /dev/null
docker exec postgres psql -U mydb -d mydb -c "\d settings.seed_jobs"
```
Expected: `status`, `heartbeat`, `cancel_requested`, `log` … present; `pid` and `host` nullable.

- [ ] **Step 3: Write the failing test for queue/get/present**

Create `app/tests/unit/SeedJobTest.php`:

```php
<?php
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * The tile seeder queue. Rows live in the tenant's settings.seed_jobs, and every
 * fact a client needs is in the row, so status and cancel work from any node.
 */
class SeedJobTest extends Unit
{
    protected UnitTester $tester;
    private array $created = [];

    private function model(): SeedJob
    {
        try {
            $m = new SeedJob(connection: new Connection(database: 'mydb'));
            $m->prepare("SELECT 1 FROM settings.seed_jobs LIMIT 1")->execute();
            return $m;
        } catch (Throwable $e) {
            $this->markTestSkipped('mydb not reachable: ' . $e->getMessage());
        }
    }

    private function queue(SeedJob $m, array $extra = []): array
    {
        $row = $m->queue(array_merge([
            'name' => 'test seed ' . uniqid(),
            'tileset' => 'myschema.roads',
            'grid' => 'GoogleMapsCompatible',
            'zoom_start' => 0,
            'zoom_end' => 3,
            'extent_layer' => null,
            'threads' => 1,
            'username' => 'tester',
        ], $extra));
        $this->created[] = $row['uuid'];
        return $row;
    }

    protected function _after(): void
    {
        if ($this->created) {
            $m = new SeedJob(connection: new Connection(database: 'mydb'));
            foreach ($this->created as $uuid) {
                $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
                $m->execute($res, ['u' => $uuid]);
            }
        }
    }

    public function testQueuedRowStartsPendingWithoutPidOrHost(): void
    {
        $m = $this->model();
        $row = $this->queue($m);
        $this->assertSame('pending', $row['status']);
        $this->assertNull($row['pid'], 'a pending job has no process yet');
        $this->assertNull($row['host']);
        $this->assertNull($row['started']);

        $read = $m->get($row['uuid']);
        $this->assertSame($row['uuid'], $read['uuid']);
        $presented = SeedJob::present($read);
        $this->assertSame('pending', $presented['status']);
        $this->assertFalse($presented['stale']);
        $this->assertSame(0, $presented['zoom_start'], 'zooms are integers, not strings');
        $this->assertArrayNotHasKey('log', $presented, 'the list shape carries no log');
        $this->assertArrayHasKey('log', SeedJob::present($read, withLog: true));
    }
}
```

- [ ] **Step 4: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedJobTest`
Expected: FAIL — `Class "app\models\SeedJob" not found`.

- [ ] **Step 5: Write `SeedJob` — queue, get, list, present**

Create `app/models/SeedJob.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\models;

use app\inc\Model;
use PDO;

/**
 * The tile seeder queue in settings.seed_jobs.
 *
 * A seed is a row: the API writes it 'pending', a worker claims it to 'running'
 * and finalises it. Everything a client asks about — status, host, pid, the log
 * tail, whether a cancel was requested — is in the row, which is what makes the
 * API work from any node. Rows written by the pre-v4 code have no status; they are
 * reported as-is and never claimed.
 */
final class SeedJob extends Model
{
    /** Silence for this long means the run or its node is gone; heartbeat is every 5 s. */
    public const string STALE_RUNNING_INTERVAL = '10 minutes';

    private const array STATUSES = ['pending', 'running', 'succeeded', 'failed', 'cancelled'];

    /** @param array<string, mixed> $data @return array<string, mixed> the inserted row */
    public function queue(array $data): array
    {
        $sql = "INSERT INTO settings.seed_jobs
                    (name, status, username, tileset, grid, zoom_start, zoom_end, extent_layer, threads)
                VALUES (:name, 'pending', :username, :tileset, :grid, :zoom_start, :zoom_end, :extent_layer, :threads)
                RETURNING *";
        $res = $this->prepare($sql);
        $this->execute($res, [
            'name' => $data['name'],
            'username' => $data['username'],
            'tileset' => $data['tileset'],
            'grid' => $data['grid'],
            'zoom_start' => $data['zoom_start'],
            'zoom_end' => $data['zoom_end'],
            'extent_layer' => $data['extent_layer'],
            'threads' => $data['threads'],
        ]);
        return $this->fetchRow($res);
    }

    /** @return array<string, mixed>|null */
    public function get(string $uuid): ?array
    {
        $res = $this->prepare("SELECT * FROM settings.seed_jobs WHERE uuid = :uuid");
        $this->execute($res, ['uuid' => $uuid]);
        return $this->fetchRow($res) ?: null;
    }

    /** @return array<int, array<string, mixed>> newest first */
    public function list(?string $status = null, ?string $tileset = null, ?string $username = null): array
    {
        $sql = "SELECT * FROM settings.seed_jobs WHERE 1 = 1";
        $params = [];
        foreach (['status' => $status, 'tileset' => $tileset, 'username' => $username] as $col => $value) {
            if ($value !== null) {
                $sql .= " AND $col = :$col";
                $params[$col] = $value;
            }
        }
        $res = $this->prepare($sql . " ORDER BY created DESC");
        $this->execute($res, $params);
        return $this->fetchAll($res, 'assoc');
    }

    /**
     * The API shape. `stale` is computed, never stored: a running row whose
     * heartbeat has gone quiet past the stale window.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public static function present(array $row, bool $withLog = false): array
    {
        $out = [
            'uuid' => $row['uuid'],
            'name' => $row['name'],
            'status' => $row['status'],
            'stale' => $row['status'] === 'running' && self::isStale($row['heartbeat'] ?? null),
            'username' => $row['username'],
            'tileset' => $row['tileset'],
            'grid' => $row['grid'],
            'zoom_start' => $row['zoom_start'] !== null ? (int)$row['zoom_start'] : null,
            'zoom_end' => $row['zoom_end'] !== null ? (int)$row['zoom_end'] : null,
            'extent_layer' => $row['extent_layer'],
            'threads' => $row['threads'] !== null ? (int)$row['threads'] : null,
            'host' => $row['host'],
            'pid' => $row['pid'] !== null ? (int)$row['pid'] : null,
            'created' => $row['created'],
            'started' => $row['started'],
            'finished' => $row['finished'],
            'heartbeat' => $row['heartbeat'],
            'cancel_requested' => $row['cancel_requested'],
            'error' => $row['error'],
            '_links' => ['self' => '/api/v4/tileseeder/jobs/' . $row['uuid']],
        ];
        if ($withLog) {
            $out['log'] = $row['log'];
        }
        return $out;
    }

    private static function isStale(?string $heartbeat): bool
    {
        if ($heartbeat === null) {
            return true;
        }
        return strtotime($heartbeat) < strtotime('-' . self::STALE_RUNNING_INTERVAL);
    }
}
```

- [ ] **Step 6: Write the failing test for claim, including two claimers**

Append to `SeedJobTest`:

```php
    public function testClaimTakesTheOldestPendingRowAndOnlyOnce(): void
    {
        $m = $this->model();
        $first = $this->queue($m, ['name' => 'claim one ' . uniqid()]);
        $second = $this->queue($m, ['name' => 'claim two ' . uniqid()]);

        $claimed = $m->claimOne();
        $this->assertNotNull($claimed);
        $this->assertSame($first['uuid'], $claimed['uuid'], 'oldest first');
        $this->assertSame('running', $claimed['status']);
        $this->assertNotNull($claimed['started']);
        $this->assertNotNull($claimed['heartbeat'], 'the claim seeds the heartbeat, so the reaper has a clock');

        // A second claimer on its own connection must get the next row, never the same one.
        $other = new SeedJob(connection: new Connection(database: 'mydb'));
        $claimedByOther = $other->claimOne();
        $this->assertNotSame($claimed['uuid'], $claimedByOther['uuid'] ?? null,
            'FOR UPDATE SKIP LOCKED must not hand the same row to two workers');
        $this->assertSame($second['uuid'], $claimedByOther['uuid']);

        $this->assertNull($m->claimOne(), 'nothing left to claim');
    }
```

- [ ] **Step 7: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedJobTest`
Expected: FAIL — `Call to undefined method app\models\SeedJob::claimOne()`.

- [ ] **Step 8: Implement claim, heartbeat, cancel, finish, reap**

Add to `SeedJob`:

```php
    /**
     * Claims the oldest pending row for this node, or null when there is nothing to
     * do. SKIP LOCKED is what lets several nodes run ticks against one database
     * without both taking the same row. Only 'pending' is eligible: a stuck
     * 'running' row is the reaper's business, not a second attempt's, because a
     * seed that is really still running elsewhere must not be started twice.
     *
     * @return array<string, mixed>|null
     */
    public function claimOne(): ?array
    {
        $sql = "UPDATE settings.seed_jobs u
                SET status = 'running', started = now(), heartbeat = now(), host = :host
                FROM (
                    SELECT uuid FROM settings.seed_jobs
                    WHERE status = 'pending'
                    ORDER BY created
                    LIMIT 1
                    FOR UPDATE SKIP LOCKED
                ) sub
                WHERE u.uuid = sub.uuid
                RETURNING u.*";
        $res = $this->prepare($sql);
        $this->execute($res, ['host' => self::host()]);
        return $this->fetchRow($res) ?: null;
    }

    /** Proof of life from the run, plus the current log tail. */
    public function heartbeat(string $uuid, ?string $logTail = null): void
    {
        $res = $this->prepare("UPDATE settings.seed_jobs
                                  SET heartbeat = now(), log = COALESCE(:log, log)
                                WHERE uuid = :uuid AND status = 'running'");
        $this->execute($res, ['uuid' => $uuid, 'log' => $logTail]);
    }

    public function setPid(string $uuid, int $pid, ?string $logPath): void
    {
        $res = $this->prepare("UPDATE settings.seed_jobs SET pid = :pid, log_path = :path WHERE uuid = :uuid");
        $this->execute($res, ['uuid' => $uuid, 'pid' => $pid, 'path' => $logPath]);
    }

    /** True once a cancel has been asked for; the run polls this. */
    public function isCancelRequested(string $uuid): bool
    {
        $res = $this->prepare("SELECT cancel_requested FROM settings.seed_jobs WHERE uuid = :uuid");
        $this->execute($res, ['uuid' => $uuid]);
        return ($this->fetchRow($res)['cancel_requested'] ?? null) !== null;
    }

    /**
     * 'cancelled' — the row was pending and is finished here and now.
     * 'cancelling' — it is running; the flag is set and its worker will act.
     * 'noop' — already finished, or a legacy row with no status: nothing to do.
     */
    public function requestCancel(string $uuid): string
    {
        return $this->withTransaction(function () use ($uuid) {
            $res = $this->prepare("SELECT status FROM settings.seed_jobs WHERE uuid = :uuid FOR UPDATE");
            $this->execute($res, ['uuid' => $uuid]);
            $status = $this->fetchRow($res)['status'] ?? null;
            if ($status === 'pending') {
                $upd = $this->prepare("UPDATE settings.seed_jobs
                                          SET status = 'cancelled', cancel_requested = now(), finished = now()
                                        WHERE uuid = :uuid");
                $this->execute($upd, ['uuid' => $uuid]);
                return 'cancelled';
            }
            if ($status === 'running') {
                $upd = $this->prepare("UPDATE settings.seed_jobs SET cancel_requested = now() WHERE uuid = :uuid");
                $this->execute($upd, ['uuid' => $uuid]);
                return 'cancelling';
            }
            return 'noop';
        });
    }

    /** Finalises a row this process owns. Only a 'running' row is touched. */
    public function finish(string $uuid, string $status, ?string $error, ?string $logTail): void
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException("Unknown seed job status: $status");
        }
        $res = $this->prepare("UPDATE settings.seed_jobs
                                  SET status = :status, error = :error, finished = now(),
                                      log = COALESCE(:log, log)
                                WHERE uuid = :uuid AND status = 'running'");
        $this->execute($res, ['uuid' => $uuid, 'status' => $status, 'error' => $error, 'log' => $logTail]);
    }

    /**
     * Rows whose run went away without finalising — SIGKILL, OOM, a dead node —
     * become 'failed' once the heartbeat has been quiet past the stale window.
     * Without this a row stays 'running' forever and its tileset looks busy.
     *
     * @return int rows reaped
     */
    public function reapStale(): int
    {
        $res = $this->prepare("UPDATE settings.seed_jobs
                                  SET status = 'failed', finished = now(),
                                      error = COALESCE(error, 'stale: no heartbeat from ' || COALESCE(host, 'unknown host'))
                                WHERE status = 'running'
                                  AND heartbeat < now() - interval '" . self::STALE_RUNNING_INTERVAL . "'");
        $this->execute($res);
        return $res->rowCount();
    }

    private static function host(): string
    {
        return gethostname() ?: ($_SERVER['SERVER_ADDR'] ?? 'unknown');
    }
```

- [ ] **Step 9: Write the failing tests for cancel, reap and legacy rows**

Append to `SeedJobTest`:

```php
    public function testCancelIsImmediateWhenPendingAndCooperativeWhenRunning(): void
    {
        $m = $this->model();
        $pending = $this->queue($m, ['name' => 'cancel pending ' . uniqid()]);
        $this->assertSame('cancelled', $m->requestCancel($pending['uuid']));
        $row = $m->get($pending['uuid']);
        $this->assertSame('cancelled', $row['status']);
        $this->assertNotNull($row['finished']);
        $this->assertSame('noop', $m->requestCancel($pending['uuid']), 'cancelling twice is idempotent');

        $running = $this->queue($m, ['name' => 'cancel running ' . uniqid()]);
        $m->claimOne();
        $this->assertSame('cancelling', $m->requestCancel($running['uuid']));
        $this->assertTrue($m->isCancelRequested($running['uuid']));
        $this->assertSame('running', $m->get($running['uuid'])['status'], 'the worker finishes it, not the API');
    }

    public function testStaleRunningRowIsReapedAsFailed(): void
    {
        $m = $this->model();
        $row = $this->queue($m, ['name' => 'stale ' . uniqid()]);
        $m->claimOne();
        // Backdate the heartbeat past the stale window, as if the run were killed.
        $res = $m->prepare("UPDATE settings.seed_jobs
                               SET heartbeat = now() - interval '" . SeedJob::STALE_RUNNING_INTERVAL . "' - interval '1 minute'
                             WHERE uuid = :u");
        $m->execute($res, ['u' => $row['uuid']]);

        $this->assertGreaterThanOrEqual(1, $m->reapStale());
        $reaped = $m->get($row['uuid']);
        $this->assertSame('failed', $reaped['status']);
        $this->assertStringContainsString('stale', (string)$reaped['error']);
        $this->assertTrue(SeedJob::present($reaped)['stale'] === false || $reaped['status'] === 'failed');
    }

    public function testLegacyRowsAreNeverClaimedAndKeepANullStatus(): void
    {
        $m = $this->model();
        // What the pre-v4 code and MapcacheTileset write: no status at all.
        $uuid = null;
        $res = $m->prepare("INSERT INTO settings.seed_jobs (name, pid, host) VALUES ('legacy job', 4242, 'old-node') RETURNING uuid");
        $m->execute($res);
        $uuid = $m->fetchRow($res)['uuid'];
        $this->created[] = $uuid;

        $this->assertNull($m->get($uuid)['status']);
        $this->assertSame('noop', $m->requestCancel($uuid), 'a legacy row cannot be cancelled');
        $presented = SeedJob::present($m->get($uuid));
        $this->assertNull($presented['status']);
        $this->assertFalse($presented['stale'], 'a row with no status is not a stuck run');

        // Claiming must ignore it even when it is the oldest row.
        $fresh = $this->queue($m, ['name' => 'after legacy ' . uniqid()]);
        $this->assertSame($fresh['uuid'], $m->claimOne()['uuid']);
    }
```

- [ ] **Step 10: Run the tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedJobTest`
Expected: PASS, 5 tests. If `testLegacyRowsAreNeverClaimedAndKeepANullStatus` fails on `stale`, make `present()` return `stale => false` whenever `status !== 'running'`, which is what the assertion pins.

- [ ] **Step 11: Commit**

```bash
git add app/migration/Sql.php app/models/SeedJob.php app/tests/unit/SeedJobTest.php
git commit -m "feat(tileseeder): the seed job queue in settings.seed_jobs

A seed becomes a row a worker claims, so status, log and cancel work from any
node instead of from pgrep and kill -9 on whichever node took the request.
Claiming uses FOR UPDATE SKIP LOCKED, a quiet heartbeat past
SeedJob::STALE_RUNNING_INTERVAL is reaped as failed, and rows written by the
pre-v4 code keep a null status and are never claimed."
```

---

### Task 2: `SeedCommand` — validation and a command that cannot be injected

**Files:**
- Create: `app/inc/tileseeder/SeedCommand.php`
- Test: `app/tests/unit/SeedCommandTest.php`

**Interfaces:**
- Consumes: `app\conf\App` (`$param['path']`, `$param['tileseeder']`), `app\controllers\Mapcache::getGrids()`, `app\exceptions\GC2Exception`.
- Produces:
  ```php
  final class SeedCommand {
      public function __construct(string $database, string $tileset, string $grid,
                                  int $zoomStart, int $zoomEnd, ?string $extentLayer, int $threads);
      public static function validate(string $database, string $tileset, string $grid,
                                      int $zoomStart, int $zoomEnd, ?string $extentLayer, int $threads): void;
      public function argv(array $pgParams): array;   // ['/usr/local/bin/mapcache_seed', '-c', …]
      public function env(string $pgPassword): array; // ['PGPASSWORD' => …]
  }
  ```

- [ ] **Step 1: Write the failing test (Review Focus 1 lives here)**

Create `app/tests/unit/SeedCommandTest.php`:

```php
<?php
use app\exceptions\GC2Exception;
use app\inc\tileseeder\SeedCommand;
use Codeception\Test\Unit;

/**
 * The mapcache_seed command line. v3 interpolated the request's layer, grid,
 * extent and zooms into a shell string, so a bearer token was enough to run
 * arbitrary commands, and it put the database password where ps could read it.
 * Both are pinned here.
 */
class SeedCommandTest extends Unit
{
    protected UnitTester $tester;

    private function command(array $over = []): SeedCommand
    {
        return new SeedCommand(
            database: $over['database'] ?? 'mydb',
            tileset: $over['tileset'] ?? 'myschema.roads',
            grid: $over['grid'] ?? 'GoogleMapsCompatible',
            zoomStart: $over['zoomStart'] ?? 0,
            zoomEnd: $over['zoomEnd'] ?? 4,
            extentLayer: $over['extentLayer'] ?? null,
            threads: $over['threads'] ?? 2,
        );
    }

    public function testEveryValueIsItsOwnArgumentAndNothingIsInterpolated(): void
    {
        $argv = $this->command(['tileset' => 'myschema.roads; rm -rf /'])->argv([
            'host' => 'db', 'port' => '5432', 'user' => 'mydb',
        ]);
        // argv is a list, not a string: the shell never parses these.
        $this->assertContains('myschema.roads; rm -rf /', $argv, 'the value travels as one argument');
        $this->assertNotContains('rm', $argv, 'and never as a command of its own');
        $this->assertSame('-t', $argv[array_search('myschema.roads; rm -rf /', $argv, true) - 1]);
    }

    public function testThePasswordIsInTheEnvironmentNotInArgv(): void
    {
        $argv = $this->command()->argv(['host' => 'db', 'port' => '5432', 'user' => 'mydb']);
        $this->assertStringNotContainsString('secret', implode(' ', $argv));
        $this->assertStringNotContainsString('password', strtolower(implode(' ', $argv)));
        $this->assertSame(['PGPASSWORD' => 'secret'], $this->command()->env('secret'));
    }

    public function testValidationRejectsWhatCannotBeSeeded(): void
    {
        // A grid the install does not have.
        $this->expectException(GC2Exception::class);
        SeedCommand::validate('mydb', 'myschema.roads', 'NoSuchGrid', 0, 4, null, 1);
    }

    public function testValidationRejectsZoomAndThreadRanges(): void
    {
        foreach ([[5, 2, 1, 'zoom_start above zoom_end'], [0, 4, 0, 'threads below one'], [0, 4, 99, 'threads above the cap'], [-1, 4, 1, 'negative zoom']] as [$start, $end, $threads, $why]) {
            try {
                SeedCommand::validate('mydb', 'myschema.roads', 'GoogleMapsCompatible', $start, $end, null, $threads);
                $this->fail("accepted $why");
            } catch (GC2Exception $e) {
                $this->assertSame(400, $e->getCode(), $why);
            }
        }
    }

    public function testPathTraversalInTheTilesetIsRejected(): void
    {
        $this->expectException(GC2Exception::class);
        SeedCommand::validate('mydb', '../../etc/passwd', 'GoogleMapsCompatible', 0, 1, null, 1);
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedCommandTest`
Expected: FAIL — `Class "app\inc\tileseeder\SeedCommand" not found`.

- [ ] **Step 3: Implement `SeedCommand`**

Create `app/inc/tileseeder/SeedCommand.php`:

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\tileseeder;

use app\conf\App;
use app\controllers\Mapcache;
use app\exceptions\GC2Exception;

/**
 * The mapcache_seed invocation for one seed job.
 *
 * argv() returns a list, never a string, so the shell never re-parses a value the
 * caller supplied: v3 built one string and a tileset containing `; rm -rf /` ran.
 * The database password is only ever in env(), because a command line is readable
 * with ps by everyone on the node.
 */
final class SeedCommand
{
    public function __construct(
        private readonly string  $database,
        private readonly string  $tileset,
        private readonly string  $grid,
        private readonly int     $zoomStart,
        private readonly int     $zoomEnd,
        private readonly ?string $extentLayer,
        private readonly int     $threads,
    )
    {
    }

    /**
     * Everything that can be known before a process starts. Throws 400 rather than
     * letting mapcache_seed fail minutes later in a log nobody reads.
     *
     * @throws GC2Exception
     */
    public static function validate(string $database, string $tileset, string $grid,
                                    int    $zoomStart, int $zoomEnd, ?string $extentLayer, int $threads): void
    {
        foreach (['tileset' => $tileset, 'grid' => $grid, 'extent_layer' => $extentLayer] as $field => $value) {
            if ($value !== null && (str_contains($value, '..') || !preg_match('/^[A-Za-z0-9_.:\-]+$/', $value))) {
                throw new GC2Exception("Invalid $field", 400, null, 'INVALID_REQUEST');
            }
        }
        $config = self::configPath($database);
        if (!is_file($config)) {
            throw new GC2Exception('No tile cache configuration for this database', 404, null, 'NOT_FOUND');
        }
        if (!str_contains((string)file_get_contents($config), '<tileset name="' . $tileset . '"')) {
            throw new GC2Exception('Tileset not found', 404, null, 'TILESET_NOT_FOUND');
        }
        if (!array_key_exists($grid, Mapcache::getGrids())) {
            throw new GC2Exception('Unknown grid', 400, null, 'UNKNOWN_GRID');
        }
        if ($zoomStart < 0 || $zoomEnd < 0 || $zoomStart > $zoomEnd) {
            throw new GC2Exception('zoom_start must be >= 0 and <= zoom_end', 400, null, 'INVALID_REQUEST');
        }
        $maxThreads = App::$param['tileseeder']['maxThreads'] ?? 4;
        if ($threads < 1 || $threads > $maxThreads) {
            throw new GC2Exception("threads must be between 1 and $maxThreads", 400, null, 'INVALID_REQUEST');
        }
    }

    /**
     * @param array{host:string, port:string, user:string} $pgParams
     * @return list<string>
     */
    public function argv(array $pgParams): array
    {
        $argv = [
            App::$param['tileseeder']['seedBinary'] ?? '/usr/local/bin/mapcache_seed',
            '-c', self::configPath($this->database),
            '-t', $this->tileset,
            '-g', $this->grid,
            '-z', $this->zoomStart . ',' . $this->zoomEnd,
            '-n', (string)$this->threads,
            '-d', sprintf('PG:host=%s port=%s user=%s dbname=%s',
                $pgParams['host'], $pgParams['port'], $pgParams['user'], $this->database),
            '-v',
        ];
        if ($this->extentLayer !== null) {
            $argv[] = '-l';
            $argv[] = $this->extentLayer;
        }
        return $argv;
    }

    /** @return array<string, string> */
    public function env(string $pgPassword): array
    {
        return ['PGPASSWORD' => $pgPassword];
    }

    private static function configPath(string $database): string
    {
        return App::$param['path'] . 'app/wms/mapcache/' . $database . '.xml';
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedCommandTest`
Expected: PASS, 5 tests.

`validate()` looks for `app/wms/mapcache/<database>.xml` and 404s when it is
missing, which would fire before the assertions about grids and ranges. So the test
writes its own config, the way `MapcacheDeleteApiCest` does. Add this to
`SeedCommandTest` and use the throwaway database name in the three `validate()`
tests:

```php
    private string $database = 'seedcmdtest';

    protected function _before(): void
    {
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"myschema.roads\"/>\n</mapcache>\n");
    }

    protected function _after(): void
    {
        @unlink($this->configPath());
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->database . '.xml';
    }
```

- [ ] **Step 5: Commit**

```bash
git add app/inc/tileseeder/SeedCommand.php app/tests/unit/SeedCommandTest.php
git commit -m "feat(tileseeder): validating command builder for mapcache_seed

argv() is a list, so a tileset containing shell metacharacters travels as one
argument instead of running, and the database password is only in env() where
ps cannot read it. Unknown tileset, unknown grid, inverted zooms and thread
counts out of range are refused with 400 before any process starts."
```

---

### Task 3: The v4 resource

**Files:**
- Create: `app/api/v4/controllers/Tileseeder.php`
- Test: `app/tests/api/TileseederV4ApiCest.php`

**Interfaces:**
- Consumes: `SeedJob` (Task 1), `SeedCommand::validate()` (Task 2), `app\api\v4\AbstractApi` helpers (`getResponse`, `deleteResponse`), `app\api\v4\Responses\AcceptedResponse`, `app\models\Authorization`, `app\models\User`.
- Produces: routes `POST|GET /api/v4/tileseeder/jobs`, `GET|DELETE /api/v4/tileseeder/jobs/{uuid[,uuid…]}`; OpenAPI schema `SeedJob`.

- [ ] **Step 1: Write the failing cest for queueing and reading**

Create `app/tests/api/TileseederV4ApiCest.php`:

```php
<?php
use Codeception\Util\HttpCode;

/**
 * GET/POST/DELETE /api/v4/tileseeder/jobs. The seed itself is run by a worker, so
 * these tests only exercise the queue: what the API writes, shows and cancels.
 */
class TileseederV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $subToken;
    private $schema = 'seedtest';
    private $uuid;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepare(ApiTester $I)
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "seed $ts", 'email' => "seed$ts@example.com", 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;

        $this->asSuper($I);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'roads', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'],
            ['name' => 'the_geom', 'type' => 'geometry(MultiLineString,25832)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        // The tileset must exist in the database's mapcache config. The test writes
        // the config itself, exactly as MapcacheDeleteApiCest does: generating it
        // from the layer is a different feature's job, and this suite runs inside
        // the container, so it can write the file.
        file_put_contents($this->configPath(), "<mapcache>\n  <tileset name=\"" . $this->schema . ".roads\"/>\n</mapcache>\n");
    }

    private function configPath(): string
    {
        return \app\conf\App::$param['path'] . 'app/wms/mapcache/' . $this->userId . '.xml';
    }

    public function shouldQueueAJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->stopFollowingRedirects();
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode([
            'name' => 'Seed roads', 'tileset' => $this->schema . '.roads',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 3, 'threads' => 1,
        ]));
        $I->startFollowingRedirects();
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $body = json_decode($I->grabResponse(), true);
        $this->uuid = $body[0]['uuid'] ?? $body['uuid'];
        $I->assertStringContainsString('/api/v4/tileseeder/jobs/' . $this->uuid, $I->grabHttpHeader('Location'));

        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $job = json_decode($I->grabResponse(), true);
        $I->assertSame('pending', $job['status']);
        $I->assertSame($this->schema . '.roads', $job['tileset']);
        $I->assertSame(0, $job['zoom_start']);
        $I->assertSame($this->userId, $job['username']);
        $I->assertNull($job['pid']);
        $I->assertArrayHasKey('log', $job, 'the single read carries the log');

        $I->sendGET('/api/v4/tileseeder/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $list = json_decode($I->grabResponse(), true);
        $I->assertIsArray($list, 'a bare JSON array');
        $I->assertArrayNotHasKey('log', $list[0], 'the list leaves the log out');
    }

    public function shouldRefuseWhatCannotBeSeeded(ApiTester $I)
    {
        $this->asSuper($I);
        foreach ([
            ['tileset' => 'no.such_tileset', 'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1],
            ['tileset' => $this->schema . '.roads', 'grid' => 'NoSuchGrid', 'zoom_start' => 0, 'zoom_end' => 1],
            ['tileset' => $this->schema . '.roads', 'grid' => 'GoogleMapsCompatible', 'zoom_start' => 5, 'zoom_end' => 1],
            ['tileset' => $this->schema . '.roads; whoami', 'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1],
            ['tileset' => $this->schema . '.roads', 'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 99],
        ] as $body) {
            $I->sendPOST('/api/v4/tileseeder/jobs', json_encode($body + ['name' => 'bad']));
            $I->seeResponseCodeIsClientError();
            $I->seeResponseContainsJson(['success' => false]);
        }
    }

    /**
     * tileseeder.maxPending caps the queue per database, so one token cannot fill
     * it. An array POST is checked as a whole: 21 jobs in one request is refused
     * and nothing is queued.
     */
    public function shouldRefuseMoreThanMaxPending(ApiTester $I)
    {
        $this->asSuper($I);
        $before = count(json_decode($I->grabResponse() ?: '[]', true) ?: []);
        $max = 20;   // App::$param['tileseeder']['maxPending'] default
        $batch = array_fill(0, $max + 1, [
            'name' => 'flood', 'tileset' => $this->schema . '.roads', 'grid' => 'GoogleMapsCompatible',
            'zoom_start' => 0, 'zoom_end' => 1, 'threads' => 1,
        ]);
        $I->sendPOST('/api/v4/tileseeder/jobs', json_encode($batch));
        $I->seeResponseCodeIs(HttpCode::TOO_MANY_REQUESTS);
        $I->seeResponseContainsJson(['errorCode' => 'TOO_MANY_PENDING']);

        $I->sendGET('/api/v4/tileseeder/jobs?status=pending');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertLessThanOrEqual($max, count(json_decode($I->grabResponse(), true)),
            'a refused batch queues nothing');
    }

    public function shouldCancelAPendingJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->assertSame('cancelled', json_decode($I->grabResponse(), true)['status']);
        // Idempotent: cancelling a finished job changes nothing and still succeeds.
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
    }

    public function shouldNotLeakJobsAcrossUsers(ApiTester $I)
    {
        // A sub-user of the same database sees only its own jobs.
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/session/start', json_encode(['user' => $this->userId, 'password' => $this->password, 'schema' => 'public']));
        $cookie = $I->capturePHPSESSID();
        $I->haveHttpHeader('Cookie', 'PHPSESSID=' . $cookie);
        $I->sendPOST('/api/v2/user', json_encode(['name' => "seedsub $ts", 'email' => "seedsub$ts@example.com",
            'password' => $this->password, 'subuser' => true]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $sub = json_decode($I->grabResponse())->data->screenname;
        $I->deleteHeader('Cookie');
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $sub,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $this->subToken = json_decode($I->grabResponse())->access_token;

        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->subToken);
        $I->sendGET('/api/v4/tileseeder/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame([], json_decode($I->grabResponse(), true), 'the sub-user has queued nothing');
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendDELETE('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
        @unlink($this->configPath());
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api TileseederV4ApiCest`
Expected: FAIL — the POST answers 404/406, because no controller claims the route.

- [ ] **Step 3: Write the controller**

Create `app/api/v4/controllers/Tileseeder.php`:

```php
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
    schema: 'SeedJob',
    description: 'One tile seeding job. Queued by POST, run by a worker on whichever node claims it.',
    properties: [
        new OA\Property(property: 'uuid', type: 'string', example: 'c4a3797e-ec6b-4dac-9474-ada9083620f3'),
        new OA\Property(property: 'name', type: 'string', example: 'Seed roads'),
        new OA\Property(property: 'status', description: 'pending, running, succeeded, failed or cancelled. Null for a row written before v4.', type: 'string', enum: ['pending', 'running', 'succeeded', 'failed', 'cancelled'], nullable: true),
        new OA\Property(property: 'stale', description: 'Computed: running, but no heartbeat for 10 minutes, so the run or its node is gone.', type: 'boolean'),
        new OA\Property(property: 'username', type: 'string'),
        new OA\Property(property: 'tileset', type: 'string', example: 'myschema.roads'),
        new OA\Property(property: 'grid', type: 'string', example: 'GoogleMapsCompatible'),
        new OA\Property(property: 'zoom_start', type: 'integer', example: 0),
        new OA\Property(property: 'zoom_end', type: 'integer', example: 12),
        new OA\Property(property: 'extent_layer', type: 'string', nullable: true),
        new OA\Property(property: 'threads', type: 'integer', example: 2),
        new OA\Property(property: 'host', description: 'The node that claimed the job.', type: 'string', nullable: true),
        new OA\Property(property: 'pid', type: 'integer', nullable: true),
        new OA\Property(property: 'created', type: 'string', format: 'date-time'),
        new OA\Property(property: 'started', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'finished', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'heartbeat', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'cancel_requested', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'error', type: 'string', nullable: true),
        new OA\Property(property: 'log', description: 'The tail of the seed output. Only on a single-job read.', type: 'string', nullable: true),
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

    #[OA\Post(path: '/api/v4/tileseeder/jobs', operationId: 'postSeedJob', description: "Queue one seed job (object) or several (array of objects). Asynchronous: answers 202 and a worker runs it. Requires write/owner on the tileset's relation.", tags: ['Tileseeder'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(oneOf: [
            new OA\Schema(ref: '#/components/schemas/SeedJob'),
            new OA\Schema(type: 'array', items: new OA\Items(ref: '#/components/schemas/SeedJob'))])),
        responses: [
            new OA\Response(response: 202, description: 'Queued; poll _links.self'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 403, description: 'Insufficient privileges'),
            new OA\Response(response: 404, description: 'Unknown tileset'),
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
        // Validate every job before writing any of them, so a bad list queues nothing.
        foreach ($list as $job) {
            $this->requireWrite((string)$job['tileset']);
            SeedCommand::validate($jwt['database'], (string)$job['tileset'], (string)$job['grid'],
                (int)$job['zoom_start'], (int)$job['zoom_end'], $job['extent_layer'] ?? null, (int)($job['threads'] ?? 1));
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
        responses: [new OA\Response(response: 202, description: 'Stopping'), new OA\Response(response: 204, description: 'Cancelled or already finished'),
            new OA\Response(response: 404, description: 'Not found')])]
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
            $this->validateRequest(collection: self::getAssert(), data: Input::getBody(), method: $method);
        }
        if ($method === 'delete' && empty($this->route->getParam('uuid'))) {
            throw new GC2Exception('A job uuid is required', 400, null, 'INVALID_REQUEST');
        }
    }

    private static function getAssert(): Assert\Collection
    {
        return new Assert\Collection(
            fields: [
                'name' => new Assert\Optional(new Assert\Type('string')),
                'tileset' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'grid' => new Assert\Required([new Assert\NotBlank(), new Assert\Type('string')]),
                'zoom_start' => new Assert\Required(new Assert\Type('integer')),
                'zoom_end' => new Assert\Required(new Assert\Type('integer')),
                'extent_layer' => new Assert\Optional(new Assert\AtLeastOneOf([new Assert\Type('string'), new Assert\IsNull()])),
                'threads' => new Assert\Optional(new Assert\Type('integer')),
            ],
            allowExtraFields: false,
            allowMissingFields: true,
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
```

- [ ] **Step 4: Run the cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api TileseederV4ApiCest`
Expected: PASS, 6 tests.

- [ ] **Step 5: Confirm the OpenAPI document carries the new paths**

Run:
```bash
docker exec docker-dev-1 sh -lc 'curl -s "http://localhost/swagger/api.php?v=4" | python3 -c "
import json,sys
d=json.load(sys.stdin)
print([k for k in d[\"paths\"] if \"tileseeder\" in k])
print(\"SeedJob\" in d[\"components\"][\"schemas\"])
"'
```
Expected: both tileseeder paths and `True`.

- [ ] **Step 6: Add CORS headers if the browser needs new ones**

Read `public/index.php`'s `setHeaders()`. No new request or response header is used by this resource, so nothing changes; note in the commit that it was checked.

- [ ] **Step 7: Write the failing test for legacy rows in the API (Review Focus 4)**

Append to `TileseederV4ApiCest`:

```php
    /**
     * Rows written by the pre-v4 code and by MapcacheTileset have no status. They
     * must be listed as they are, never presented as running, and never cancelled.
     */
    public function shouldShowLegacyRowsWithoutInventingAStatus(ApiTester $I)
    {
        $this->asSuper($I);
        // MapcacheTileset writes exactly this shape for its background delete jobs.
        $I->sendPOST('/api/v4/sql', json_encode(['q' =>
            "INSERT INTO settings.seed_jobs (name, pid, host) VALUES ('delete legacy', 4242, 'old-node')"]));
        $I->seeResponseCodeIsSuccessful();

        $I->sendGET('/api/v4/tileseeder/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $legacy = array_values(array_filter(json_decode($I->grabResponse(), true), fn($j) => $j['name'] === 'delete legacy'));
        $I->assertCount(1, $legacy);
        $I->assertNull($legacy[0]['status'], 'no status is invented');
        $I->assertFalse($legacy[0]['stale'], 'and a row with no status is not a stuck run');
    }
```

Run it, watch it fail or pass, and fix `SeedJob::present()` if `stale` is true for a row whose status is null. Then run the whole cest again.

- [ ] **Step 8: Commit**

```bash
git add app/api/v4/controllers/Tileseeder.php app/tests/api/TileseederV4ApiCest.php
git commit -m "feat(tileseeder): v4 resource for queued seed jobs

POST validates and queues (202), GET reads one with its log tail or lists
without it, DELETE cancels — 204 when the job was still queued, 202 when a
worker has to stop it. Write/owner on the tileset's relation is required, the
same rule MapcacheTileset uses, and a uuid belonging to another user is 404
rather than 403 so a token cannot enumerate other users' jobs. Checked that
no new CORS header is needed."
```

---

### Task 4: `seed_run.php` — the process that owns one job

**Files:**
- Create: `app/scripts/seed_run.php`
- Test: `app/tests/unit/SeedRunTest.php`
- Modify: `docker/conf/gc2/App.php` (the `tileseeder` block, so `seedBinary` can be pointed at a stub)

**Interfaces:**
- Consumes: `SeedJob` (Task 1), `SeedCommand` (Task 2), `app\conf\App`, `app\conf\Connection` for the PG parameters.
- Produces: `php app/scripts/seed_run.php --database=<db> --uuid=<uuid>`; exit code 0 on `succeeded`, 1 on `failed`, 2 on `cancelled`.

- [ ] **Step 1: Add the config block**

In `docker/conf/gc2/App.php`, next to the other blocks:

```php
        // Tile seeder (docs/superpowers/specs/2026-09-30-tileseeder-v4-design.md).
        // maxConcurrent is per node: the cost is CPU and cache writes on that node.
        "tileseeder" => [
            "maxConcurrent" => 1,
            "maxThreads" => 4,
            "maxPending" => 20,
            "maxHours" => 12,
            "cancelGraceSeconds" => 10,
            "logTailBytes" => 8192,
            "keepLogHours" => 72,
            "seedBinary" => "/usr/local/bin/mapcache_seed",
        ],
```

- [ ] **Step 2: Write the failing test with a stub binary**

Create `app/tests/unit/SeedRunTest.php`:

```php
<?php
use app\conf\App;
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * app/scripts/seed_run.php runs one claimed job. It is driven here with a stub
 * instead of mapcache_seed — the recipe the scheduler's stop test uses — so the
 * state machine can be asserted without producing tiles.
 */
class SeedRunTest extends Unit
{
    protected UnitTester $tester;
    private string $stub;
    private array $created = [];

    protected function _before(): void
    {
        $this->stub = sys_get_temp_dir() . '/seed_stub_' . uniqid() . '.sh';
    }

    protected function _after(): void
    {
        @unlink($this->stub);
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        foreach ($this->created as $uuid) {
            $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
            $m->execute($res, ['u' => $uuid]);
        }
    }

    private function writeStub(string $body): void
    {
        file_put_contents($this->stub, "#!/bin/sh\n" . $body . "\n");
        chmod($this->stub, 0755);
    }

    /** Queue a row and claim it, the state seed_run.php expects to find. */
    private function claimed(): array
    {
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $row = $m->queue(['name' => 'run ' . uniqid(), 'username' => 'tester', 'tileset' => 'x.y',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => null, 'threads' => 1]);
        $this->created[] = $row['uuid'];
        return $m->claimOne();
    }

    private function run(string $uuid): int
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(App::$param['path'] . 'app/scripts/seed_run.php')
            . ' --database=mydb --uuid=' . escapeshellarg($uuid)
            . ' --binary=' . escapeshellarg($this->stub) . ' 2>&1';
        exec($cmd, $out, $code);
        return $code;
    }

    public function testASuccessfulSeedEndsSucceededWithItsOutputInTheLog(): void
    {
        $this->writeStub('echo "seeding tile 1"; echo "done"; exit 0');
        $row = $this->claimed();
        $this->assertSame(0, $this->run($row['uuid']));

        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $done = $m->get($row['uuid']);
        $this->assertSame('succeeded', $done['status']);
        $this->assertNotNull($done['finished']);
        $this->assertNotNull($done['pid'], 'the run records the pid it started');
        $this->assertStringContainsString('seeding tile 1', (string)$done['log']);
        $this->assertNull($done['error']);
    }

    public function testANonZeroExitEndsFailedWithTheCodeInTheError(): void
    {
        $this->writeStub('echo "cannot open config"; exit 3');
        $row = $this->claimed();
        $this->assertSame(1, $this->run($row['uuid']));
        $done = new SeedJob(connection: new Connection(database: 'mydb'))->get($row['uuid']);
        $this->assertSame('failed', $done['status']);
        $this->assertStringContainsString('3', (string)$done['error'], 'the exit code is recorded');
        $this->assertStringContainsString('cannot open config', (string)$done['log']);
    }

    public function testACancelRequestStopsTheChildAndEndsCancelled(): void
    {
        $this->writeStub('trap "exit 0" TERM; while true; do echo tick; sleep 1; done');
        $row = $this->claimed();
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        // Ask for cancellation before the run starts: the row is running with no
        // child yet, which is the window between claim and spawn.
        $m->requestCancel($row['uuid']);
        $this->assertSame(2, $this->run($row['uuid']));
        $done = $m->get($row['uuid']);
        $this->assertSame('cancelled', $done['status']);
        $this->assertNotNull($done['finished']);
    }
}
```

- [ ] **Step 3: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedRunTest`
Expected: FAIL — the script does not exist, so `exec` returns 127.

- [ ] **Step 4: Write `seed_run.php`**

Create `app/scripts/seed_run.php`:

```php
<?php
/**
 * Runs one claimed tile seed job and owns its row from claim to outcome.
 *
 * Spawned detached by app/scripts/seed_worker.php, one process per job, because a
 * seed runs for hours and a cron tick must not. Every 5 seconds it writes a
 * heartbeat and the log tail, and re-reads cancel_requested: that flag is how a
 * request on any node stops a seed on this one. A shutdown function and signal
 * handlers finalise the row even when the process is killed, so a row is never
 * left 'running' by a process that is gone — the pattern get.php uses for
 * scheduler runs.
 *
 * Usage: php seed_run.php --database=<db> --uuid=<uuid> [--binary=<path>]
 */

use app\conf\App;
use app\inc\Connection;
use app\inc\tileseeder\SeedCommand;
use app\models\SeedJob;

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");
new App();

$options = getopt("", ["database:", "uuid:", "binary::"]);
$database = $options["database"] ?? null;
$uuid = $options["uuid"] ?? null;
if (!$database || !$uuid) {
    fwrite(STDERR, "--database and --uuid are required\n");
    exit(1);
}

$jobs = new SeedJob(connection: new Connection(database: $database));
$row = $jobs->get($uuid);
if ($row === null || $row['status'] !== 'running') {
    fwrite(STDERR, "job $uuid is not claimed for running\n");
    exit(1);
}

$logDir = App::$param['path'] . "app/tmp/$database/seed";
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logPath = "$logDir/$uuid.log";
$tailBytes = App::$param['tileseeder']['logTailBytes'] ?? 8192;

$finalised = false;
$finalise = function (string $status, ?string $error) use (&$finalised, $jobs, $uuid, $logPath, $tailBytes): void {
    if ($finalised) {
        return;
    }
    $finalised = true;
    $jobs->finish($uuid, $status, $error, tail($logPath, $tailBytes));
};
// A crash, an OOM kill or the worker's `timeout` must still leave a finished row.
register_shutdown_function(function () use (&$finalised, $finalise) {
    if (!$finalised) {
        $err = error_get_last();
        $finalise('failed', $err !== null ? 'terminated: ' . $err['message'] : 'terminated');
    }
});
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $sig) {
        pcntl_signal($sig, function () use ($finalise) {
            $finalise('cancelled', 'stopped by signal');
            exit(2);
        });
    }
}

$command = new SeedCommand(
    database: $database,
    tileset: $row['tileset'],
    grid: $row['grid'],
    zoomStart: (int)$row['zoom_start'],
    zoomEnd: (int)$row['zoom_end'],
    extentLayer: $row['extent_layer'],
    threads: (int)$row['threads'],
);
$argv = $command->argv([
    'host' => \app\conf\Connection::$param['postgishost'],
    'port' => (string)\app\conf\Connection::$param['postgisport'],
    'user' => \app\conf\Connection::$param['postgisuser'],
]);
if (!empty($options['binary'])) {
    $argv[0] = $options['binary'];   // the tests drive a stub instead of mapcache_seed
}

$log = fopen($logPath, 'w');
$process = proc_open($argv, [1 => $log, 2 => $log], $pipes, null,
    $command->env(\app\conf\Connection::$param['postgispw']) + ['PATH' => getenv('PATH')]);
if (!is_resource($process)) {
    $finalise('failed', 'could not start ' . $argv[0]);
    exit(1);
}
$jobs->setPid($uuid, (int)proc_get_status($process)['pid'], $logPath);

$grace = App::$param['tileseeder']['cancelGraceSeconds'] ?? 10;
$cancelling = null;
while (true) {
    $status = proc_get_status($process);
    if (!$status['running']) {
        $exit = $status['exitcode'];
        if ($cancelling !== null) {
            $finalise('cancelled', null);
            exit(2);
        }
        $finalise($exit === 0 ? 'succeeded' : 'failed', $exit === 0 ? null : "mapcache_seed exited with $exit");
        exit($exit === 0 ? 0 : 1);
    }
    $jobs->heartbeat($uuid, tail($logPath, $tailBytes));
    if ($cancelling === null && $jobs->isCancelRequested($uuid)) {
        $cancelling = time();
        proc_terminate($process, SIGTERM);
    } elseif ($cancelling !== null && time() - $cancelling >= $grace) {
        proc_terminate($process, SIGKILL);
    }
    sleep(5);
}

/** The last $bytes of the log, so a client sees what the process last said. */
function tail(string $path, int $bytes): ?string
{
    if (!is_file($path)) {
        return null;
    }
    $size = filesize($path);
    $fh = fopen($path, 'r');
    if ($fh === false) {
        return null;
    }
    if ($size > $bytes) {
        fseek($fh, -$bytes, SEEK_END);
    }
    $out = stream_get_contents($fh);
    fclose($fh);
    return $out === false ? null : $out;
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedRunTest`
Expected: PASS, 3 tests. The cancel test takes about 5 seconds, because the loop polls on that interval.

- [ ] **Step 6: Check the password never reaches the process table**

Run, while a slow stub runs:
```bash
docker exec docker-dev-1 sh -lc 'ps -eo args | grep -c "PGPASSWORD" || true'
```
Expected: `0` — the password is in the environment, not in `argv`.

- [ ] **Step 7: Commit**

```bash
git add app/scripts/seed_run.php app/tests/unit/SeedRunTest.php docker/conf/gc2/App.php
git commit -m "feat(tileseeder): the process that owns one seed job

seed_run.php runs mapcache_seed for one claimed row: it records the pid, writes
a heartbeat and the log tail every five seconds, and re-reads cancel_requested,
which is how a request on any node stops a seed on this one (SIGTERM, then
SIGKILL after the grace period). A shutdown function and SIGINT/SIGTERM
handlers finalise the row even when the process is killed, so no row is left
running by a process that is gone. Driven in tests by a stub binary."
```

---

### Task 5: `seed_worker.php` — the cron tick

**Files:**
- Create: `app/scripts/seed_worker.php`
- Test: `app/tests/unit/SeedWorkerTest.php`
- Modify: `docker/Dockerfile` (the cron line)

**Interfaces:**
- Consumes: `SeedJob::claimOne()`, `SeedJob::reapStale()` (Task 1), `seed_run.php` (Task 4), `app\models\Database::listAllDbs()`.
- Produces: `php app/scripts/seed_worker.php [--database=<db>] [--binary=<path>]`, printing one line per claim.

- [ ] **Step 1: Write the failing test (Review Focus 3 lives here)**

Create `app/tests/unit/SeedWorkerTest.php`:

```php
<?php
use app\conf\App;
use app\inc\Connection;
use app\models\SeedJob;
use Codeception\Test\Unit;

/**
 * The tick: reap what died, claim what waits, spawn a run per job, return at once.
 * It must not run a seed inline — a seed lasts hours and the tick holds flock.
 */
class SeedWorkerTest extends Unit
{
    protected UnitTester $tester;
    private array $created = [];
    private string $stub;

    protected function _before(): void
    {
        $this->stub = sys_get_temp_dir() . '/seed_worker_stub_' . uniqid() . '.sh';
        file_put_contents($this->stub, "#!/bin/sh\nsleep 30\n");
        chmod($this->stub, 0755);
    }

    protected function _after(): void
    {
        @unlink($this->stub);
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        foreach ($this->created as $uuid) {
            $row = $m->get($uuid);
            if (!empty($row['pid'])) {
                exec('kill -9 ' . (int)$row['pid'] . ' 2>/dev/null');
            }
            $res = $m->prepare("DELETE FROM settings.seed_jobs WHERE uuid = :u");
            $m->execute($res, ['u' => $uuid]);
        }
    }

    private function queue(): array
    {
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $row = $m->queue(['name' => 'tick ' . uniqid(), 'username' => 'tester', 'tileset' => 'x.y',
            'grid' => 'GoogleMapsCompatible', 'zoom_start' => 0, 'zoom_end' => 1, 'extent_layer' => null, 'threads' => 1]);
        $this->created[] = $row['uuid'];
        return $row;
    }

    private function tick(): array
    {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(App::$param['path'] . 'app/scripts/seed_worker.php')
            . ' --database=mydb --binary=' . escapeshellarg($this->stub) . ' 2>&1';
        $start = microtime(true);
        exec($cmd, $out);
        return ['seconds' => microtime(true) - $start, 'out' => implode("\n", $out)];
    }

    public function testTheTickClaimsSpawnsAndReturnsImmediately(): void
    {
        $row = $this->queue();
        $result = $this->tick();
        $this->assertLessThan(10, $result['seconds'], 'the tick must not wait for a 30 second seed');
        $this->assertStringContainsString($row['uuid'], $result['out']);

        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $claimed = $m->get($row['uuid']);
        $this->assertSame('running', $claimed['status']);
        $this->assertNotNull($claimed['host'], 'the claim records which node took it');
    }

    public function testTheTickReapsARunWhoseHeartbeatDied(): void
    {
        $row = $this->queue();
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $m->claimOne();
        $res = $m->prepare("UPDATE settings.seed_jobs
                               SET heartbeat = now() - interval '" . SeedJob::STALE_RUNNING_INTERVAL . "' - interval '5 minutes'
                             WHERE uuid = :u");
        $m->execute($res, ['u' => $row['uuid']]);

        $this->tick();
        $reaped = $m->get($row['uuid']);
        $this->assertSame('failed', $reaped['status'], 'a run that stopped heartbeating is finished by the tick');
        $this->assertStringContainsString('stale', (string)$reaped['error']);
    }

    public function testTheTickHonoursMaxConcurrent(): void
    {
        $first = $this->queue();
        $second = $this->queue();
        $this->tick();   // maxConcurrent defaults to 1
        $m = new SeedJob(connection: new Connection(database: 'mydb'));
        $this->assertSame('running', $m->get($first['uuid'])['status']);
        $this->assertSame('pending', $m->get($second['uuid'])['status'], 'the second waits its turn');
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedWorkerTest`
Expected: FAIL — the script does not exist.

- [ ] **Step 3: Write `seed_worker.php`**

Create `app/scripts/seed_worker.php`:

```php
<?php
/**
 * Cron tick for the tile seeder: reap what died, claim what waits, spawn one
 * seed_run.php per job, return.
 *
 * It spawns rather than runs, because a seed lasts hours while a tick runs under
 * flock -n and must let the next tick in. This is what Job::runJob does for
 * scheduler runs, and deliberately not what snapshot_worker.php does inline — that
 * is right for jobs measured in minutes.
 *
 * Usage: php seed_worker.php [--database=<db>] [--binary=<path>]
 */

use app\conf\App;
use app\inc\Connection;
use app\inc\Model;
use app\models\Database;
use app\models\SeedJob;

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");
new App();

$options = getopt("", ["database::", "binary::"]);
$only = $options["database"] ?? null;
$binary = $options["binary"] ?? null;

$maxConcurrent = App::$param['tileseeder']['maxConcurrent'] ?? 1;
$maxHours = App::$param['tileseeder']['maxHours'] ?? 12;
$php = PHP_BINARY;
$runner = App::$param['path'] . 'app/scripts/seed_run.php';

// Live children of this node, whatever database they belong to.
exec('pgrep -f ' . escapeshellarg('seed_run.php') . ' | wc -l', $out);
$live = (int)trim($out[0] ?? '0');

$databases = $only ? [$only] : new Database()->listAllDbs()['data'];
foreach ($databases as $database) {
    $connection = new Connection(database: $database);
    try {
        $jobs = new SeedJob(connection: $connection);
        $reaped = $jobs->reapStale();
        if ($reaped > 0) {
            echo "$database: reaped $reaped stale run(s)\n";
        }
        while ($live < $maxConcurrent) {
            $row = $jobs->claimOne();
            if ($row === null) {
                break;
            }
            $cmd = '/usr/bin/nohup /usr/bin/timeout -s SIGINT -k 60 ' . (int)$maxHours . 'h '
                . escapeshellarg($php) . ' ' . escapeshellarg($runner)
                . ' --database=' . escapeshellarg($database)
                . ' --uuid=' . escapeshellarg($row['uuid']);
            if ($binary !== null) {
                $cmd .= ' --binary=' . escapeshellarg($binary);
            }
            exec($cmd . ' > /dev/null 2>&1 & echo $!');
            $live++;
            echo "$database: started seed {$row['uuid']} ({$row['tileset']})\n";
        }
    } catch (Throwable $e) {
        // A database without settings.seed_jobs, or a transient error: the next
        // tick tries again. Best-effort per tick, like the snapshot worker.
    } finally {
        Model::disconnect($connection);
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedWorkerTest`
Expected: PASS, 3 tests.

- [ ] **Step 5: Add log retention to the tick**

The spec's §5 keeps the full log on the node for `keepLogHours` and has the worker
remove older files. Add to `seed_worker.php`, inside the per-database `try`, after
the reap:

```php
        // The tail lives in the row; the full file is for debugging on this node.
        $keepHours = App::$param['tileseeder']['keepLogHours'] ?? 72;
        $logDir = App::$param['path'] . "app/tmp/$database/seed";
        if (is_dir($logDir)) {
            foreach (glob("$logDir/*.log") ?: [] as $file) {
                if (filemtime($file) < time() - $keepHours * 3600) {
                    @unlink($file);
                }
            }
        }
```

And a test, appended to `SeedWorkerTest`:

```php
    public function testTheTickRemovesLogsPastTheRetentionWindow(): void
    {
        $dir = App::$param['path'] . 'app/tmp/mydb/seed';
        @mkdir($dir, 0777, true);
        $old = "$dir/" . uniqid('old_') . '.log';
        $fresh = "$dir/" . uniqid('fresh_') . '.log';
        file_put_contents($old, 'ancient');
        file_put_contents($fresh, 'recent');
        $keepHours = App::$param['tileseeder']['keepLogHours'] ?? 72;
        touch($old, time() - ($keepHours + 1) * 3600);

        $this->tick();

        $this->assertFileDoesNotExist($old, 'a log past the retention window is removed');
        $this->assertFileExists($fresh, 'a recent log is kept for debugging');
        @unlink($fresh);
    }
```

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SeedWorkerTest`
Expected: PASS, 4 tests.

- [ ] **Step 6: Add the cron line**

In `docker/Dockerfile`, beside the other cron entries, matching their `flock -n` style:

```
RUN echo "* * * * * root /usr/bin/flock -n /tmp/seed_worker.lock /usr/local/bin/php /var/www/geocloud2/app/scripts/seed_worker.php >> /var/log/seed_worker.log 2>&1" >> /etc/crontab
```

Copy the exact form of the neighbouring lines rather than this one if they differ; the point is `flock -n` and one minute.

- [ ] **Step 7: Run the tick by hand against the dev container**

Run:
```bash
docker exec -w /var/www/geocloud2/app/scripts docker-dev-1 php seed_worker.php --database=mydb
```
Expected: no output when nothing is queued, and no error.

- [ ] **Step 8: Commit**

```bash
git add app/scripts/seed_worker.php app/tests/unit/SeedWorkerTest.php docker/Dockerfile
git commit -m "feat(tileseeder): cron tick that claims seeds and spawns a run per job

The tick reaps runs whose heartbeat died, claims pending rows with SKIP LOCKED
so several nodes can share a database, and spawns a detached seed_run.php under
timeout. It returns in milliseconds: running a seed inline would hold the
flock for hours and starve the reaper and the other databases."
```

---

### Task 6: The v3 shim

**Files:**
- Rewrite: `app/api/v3/Tileseeder.php`
- Test: `app/tests/api/TileseederV3ShimApiCest.php`

**Interfaces:**
- Consumes: `SeedJob` (Task 1), `SeedCommand::validate()` (Task 2).
- Produces: the four v3 endpoints, unchanged response shapes.

- [ ] **Step 1: Write the failing cest for the v3 shapes**

Create `app/tests/api/TileseederV3ShimApiCest.php`:

```php
<?php
use Codeception\Util\HttpCode;

/**
 * The v3 tileseeder keeps its shapes but stops shelling out: it queues into the
 * same table the v4 resource reads, so a job started here can be watched and
 * cancelled from any node.
 */
class TileseederV3ShimApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $schema = 'seedv3';
    private $uuid;

    public function shouldPrepare(ApiTester $I)
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "seedv3 $ts", 'email' => "seedv3$ts@example.com", 'password' => $this->password]));
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId,
            'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $this->token = json_decode($I->grabResponse())->access_token;
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendPOST('/api/v4/schemas', json_encode(['name' => $this->schema]));
        $I->sendPOST('/api/v4/schemas/' . $this->schema . '/tables', json_encode(['name' => 'roads', 'columns' => [
            ['name' => 'gid', 'type' => 'serial'],
            ['name' => 'the_geom', 'type' => 'geometry(MultiLineString,25832)'],
        ]]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
    }

    public function shouldKeepTheV3Shapes(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        // v3 field names, mapped on the way in.
        $I->sendPOST('/api/v3/tileseeder', json_encode([
            'name' => 'v3 seed', 'layer' => $this->schema . '.roads', 'grid' => 'GoogleMapsCompatible',
            'start' => 0, 'end' => 2, 'extent' => null, 'threads' => 1,
        ]));
        $I->seeResponseCodeIsSuccessful();
        $body = json_decode($I->grabResponse(), true);
        $I->assertArrayHasKey('uuid', $body);
        $I->assertArrayHasKey('pid', $body);
        $I->assertNull($body['pid'], 'a queued job has no process yet');
        $I->assertArrayNotHasKey('cmd', $body, 'cmd leaked the database password');
        $this->uuid = $body['uuid'];

        // The same job is visible in v4.
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertSame($this->schema . '.roads', json_decode($I->grabResponse(), true)['tileset']);

        $I->sendGET('/api/v3/tileseeder');
        $I->seeResponseCodeIsSuccessful();
        $list = json_decode($I->grabResponse(), true);
        $I->assertTrue($list['success']);
        $I->assertIsArray($list['pids'], 'v3 keeps its {success, pids} shape');

        $I->sendGET('/api/v3/tileseeder/log/' . $this->uuid);
        $I->seeResponseCodeIsSuccessful();
        $I->assertArrayHasKey('data', json_decode($I->grabResponse(), true));

        $I->sendDELETE('/api/v3/tileseeder/' . $this->uuid);
        $I->seeResponseCodeIsSuccessful();
        $I->assertTrue(json_decode($I->grabResponse(), true)['success']);
        $I->sendGET('/api/v4/tileseeder/jobs/' . $this->uuid);
        $I->assertSame('cancelled', json_decode($I->grabResponse(), true)['status']);
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
        $I->sendDELETE('/api/v4/schemas/' . $this->schema);
        $I->seeResponseCodeIsSuccessful();
    }
}
```

- [ ] **Step 2: Run it to verify it fails**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api TileseederV3ShimApiCest`
Expected: FAIL — the v3 POST still answers with `pid` set and a `cmd` field.

- [ ] **Step 3: Rewrite the v3 controller**

Replace the body of `app/api/v3/Tileseeder.php`'s four actions. Keep the class, the namespace and the `@OA` blocks (they document v3), and delete `kill()`, `deleteAll()` and every `exec`:

```php
    public function post_index(): array
    {
        $arr = json_decode(Input::getBody(), true);
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $database = $jwt["database"];
        // v3 field names map onto the v4 columns.
        $tileset = (string)($arr["layer"] ?? '');
        $grid = (string)($arr["grid"] ?? '');
        $zoomStart = (int)($arr["start"] ?? 0);
        $zoomEnd = (int)($arr["end"] ?? 0);
        $extent = $arr["extent"] ?? null;
        $threads = (int)($arr["threads"] ?? 1);

        SeedCommand::validate($database, $tileset, $grid, $zoomStart, $zoomEnd, $extent, $threads);
        $row = new SeedJob(connection: new Connection(database: $database))->queue([
            'name' => $arr["name"] ?? $tileset,
            'username' => $jwt["uid"],
            'tileset' => $tileset,
            'grid' => $grid,
            'zoom_start' => $zoomStart,
            'zoom_end' => $zoomEnd,
            'extent_layer' => $extent,
            'threads' => $threads,
        ]);
        // pid stays null until a worker claims the job; v3 used to return the pid of
        // a process this request had started itself.
        return ["uuid" => $row["uuid"], "pid" => null];
    }

    public function get_index(): array
    {
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $jobs = new SeedJob(connection: new Connection(database: $jwt["database"]));
        $res = [];
        foreach ($jobs->list(status: 'running') as $row) {
            $res[] = ["uuid" => $row["uuid"], "pid" => $row["pid"] !== null ? (int)$row["pid"] : null, "name" => $row["name"]];
        }
        return ["success" => true, "pids" => $res];
    }

    public function delete_index(): array
    {
        $uuid = Route::getParam("uuid");
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $jobs = new SeedJob(connection: new Connection(database: $jwt["database"]));
        if ($uuid == "*") {
            $cancelled = [];
            foreach ($jobs->list(status: 'running', username: $jwt["uid"]) as $row) {
                $jobs->requestCancel($row["uuid"]);
                $cancelled[] = ["uuid" => $row["uuid"], "pid" => $row["pid"] !== null ? (int)$row["pid"] : null, "name" => $row["name"]];
            }
            return ["success" => true, "pids" => $cancelled];
        }
        $row = $jobs->get($uuid);
        if ($row === null) {
            return ["success" => false, "message" => "No job with uuid: " . $uuid];
        }
        $jobs->requestCancel($uuid);
        return ["success" => true, "pid" => ["uuid" => $row["uuid"], "pid" => $row["pid"] !== null ? (int)$row["pid"] : null, "name" => $row["name"]]];
    }

    public function get_log(): array
    {
        $uuid = Route::getParam("uuid");
        if (!$uuid) {
            return ["data" => null];
        }
        $jwt = Jwt::extractPayload(Input::getJwtToken())["data"];
        $row = new SeedJob(connection: new Connection(database: $jwt["database"]))->get($uuid);
        $log = $row["log"] ?? null;
        if ($log === null) {
            return ["data" => null];
        }
        // v3 returned one line: the last thing the process said.
        $lines = preg_split('/[\r\n]+/', trim($log)) ?: [];
        return ["data" => $lines ? end($lines) : null];
    }
```

Add the imports the new body needs (`app\inc\Connection`, `app\inc\tileseeder\SeedCommand`, `app\models\SeedJob`) and remove the now-unused `app\conf\Connection` and `app\inc\Util`.

- [ ] **Step 4: Run the cest to verify it passes**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api TileseederV3ShimApiCest`
Expected: PASS.

- [ ] **Step 5: Prove no shell work is left in v3**

Run:
```bash
grep -nE "exec\(|pgrep|kill " app/api/v3/Tileseeder.php
```
Expected: no output.

- [ ] **Step 6: Run both suites in full**

Run:
```bash
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit
docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api
```
Expected: no failures. The api suite takes about two minutes.

- [ ] **Step 7: Commit**

```bash
git add app/api/v3/Tileseeder.php app/tests/api/TileseederV3ShimApiCest.php
git commit -m "refactor(tileseeder): v3 becomes a shim over the v4 queue

Same four endpoints and the same response shapes, but the job is queued and a
worker runs it: no exec, no pgrep, no kill -9 anywhere in v3. pid is null until
a worker claims the job, DELETE asks for cancellation (* cancels the caller's
running jobs), and the log endpoint returns the last line of the tail in the
row. cmd is gone from the POST response because it contained the database
password."
```

---

## Follow-ups this plan deliberately leaves open

- **Progress** (tiles done/total, rate, ETA) from `mapcache_seed`'s `\r` output — deferred in the spec, decision 6.
- **`MapcacheTileset`'s background delete jobs** still write legacy rows and still shell out. Routing them through this queue would make them cancellable too, and is the natural next change; the tests here pin that such rows are shown honestly (`status: null`) rather than mistaken for seeds.
- **`seed: true` on scheduler jobs**, so an import can warm its own cache, the way `snapshot: true` works.
- **A note in `docs/pages`** that a node-local cache backend (sqlite/disk) only gets warmed on the node whose store the worker can reach; s3/memcache is what makes seeding node-independent.
