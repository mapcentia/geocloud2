# Scheduler Advisory-Lock Locking Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the scheduler's file-based locks with Postgres advisory locks on the `gc2scheduler` database, turn `started_jobs` into a truthful run registry, and expose job management and runs through a v4 API.

**Architecture:** A new `app\inc\SchedulerLock` owns one dedicated PDO session to `gc2scheduler` (not the `Model` connection cache, so the session is never shared with the job's data work) and exposes the job lock, the run slots, the reaper and the registry bookkeeping. `get.php` uses it instead of lock files, records every run in `started_jobs`, and finalises the row from `cleanUp()`, a shutdown function and a SIGINT handler. `Job::runJob` and the v3 scheduler API read the registry instead of `pgrep`. `purge_locks.php` goes away.

**Tech Stack:** PHP 8.4 (`pcntl`, `posix` available in the image), PostgreSQL session advisory locks (`pg_try_advisory_lock(classid, objid)`, `pg_locks`), Codeception unit + api suites.

**Spec:** `docs/superpowers/specs/2026-09-16-scheduler-locking-design.md`

## Global Constraints

- Lock classes: job lock `42001` keyed by job id; run slots `42002` keyed by slot `1..N`; `N = App::$param['gc2scheduler']['maxJobs'] ?? 20`.
- Statuses in `started_jobs.status`: `running`, `succeeded`, `failed`, `skipped`, `lost`.
- The lock session is a dedicated PDO connection to database `gc2scheduler`, opened once per get.php run, never inside a transaction, released only by process exit (or `SchedulerLock::release()` at the very end).
- A second start of a running job exits with code 0 after recording a `skipped` row.
- The reaper uses `pg_locks` (never `pg_try_advisory_lock`) to find `running` rows whose job lock is not held, and marks them `lost`.
- Tests run inside `docker-dev-1` (repo at `/var/www/geocloud2`); one suite per command: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit <File>` / `run api <File>`. The `gc2scheduler` database exists in the dev stack (`docker exec postgres psql -U mydb -d gc2scheduler`).
- Migrations: append to `Sql::gc2scheduler()` in `app/migration/Sql.php`; apply in dev with psql against `gc2scheduler`.
- Commit messages end with the attribution lines the session-reminder specifies. Never stage `docker/docker-compose.yml` (unrelated local edit) or `app/conf/App.php`.

---

## File map

| File | Responsibility |
|---|---|
| `app/migration/Sql.php` | `started_jobs` registry columns (in `gc2scheduler()`) |
| `app/inc/SchedulerLock.php` (new) | lock session, job lock, slots, reaper, registry rows |
| `app/tests/unit/SchedulerLockTest.php` (new) | locks/slots/reaper/registry against the real db |
| `app/scripts/get.php` | use SchedulerLock; drop lock files; shutdown + SIGINT bookkeeping; heartbeat |
| `app/models/Job.php` | `runJob` waits on the registry; `getAllStartedJobs` reads it; SIGINT-first `kill` |
| `app/api/v3/Scheduler.php`, `public/index.php` | GET returns registry; new DELETE `/api/v3/scheduler/{uuid}` |
| `app/tests/api/SchedulerApiCest.php` (new) | double start → one skipped; listing shape |
| `app/scripts/scheduler.php` | run the reaper each tick |
| `app/scripts/purge_locks.php`, `docker/Dockerfile` | delete script and cron line |
| `docker/conf/gc2/App.php` | `gc2scheduler.maxJobs` |
| `app/api/v4/controllers/SchedulerJob.php` (new), `app/tests/api/SchedulerJobV4ApiCest.php` (new) | v4 job CRUD |
| `app/api/v4/controllers/SchedulerRun.php` (new), `app/tests/api/SchedulerRunV4ApiCest.php` (new) | v4 runs: start/list/inspect/stop |

---

### Task 1: Registry migration and `SchedulerLock`

**Files:**
- Modify: `app/migration/Sql.php` (append inside `gc2scheduler()`)
- Create: `app/inc/SchedulerLock.php`
- Test: `app/tests/unit/SchedulerLockTest.php`

**Interfaces:**
- Produces:

```php
namespace app\inc;
final class SchedulerLock {
    public const int JOB_LOCK_CLASS = 42001;
    public const int SLOT_LOCK_CLASS = 42002;
    public const int DEFAULT_MAX_JOBS = 20;
    public function __construct(?Connection $connection = null);          // dedicated PDO session to gc2scheduler
    public function tryJobLock(int $jobId): bool;
    public function trySlot(int $slot): bool;
    public function acquireSlot(int $maxJobs, ?callable $onWait = null, int $sleepSeconds = 10): int; // blocks until a slot is free
    public function reap(): int;                                           // rows marked lost
    public function startRun(int $jobId, string $db, ?string $name, int $pid, int $slot, string $host): string; // uuid
    public function recordSkipped(int $jobId, string $db, ?string $name, int $pid, string $host, string $reason): string;
    public function finishRun(string $uuid, string $status, ?string $reason = null): void;
    public function heartbeat(string $uuid): void;
    public function runningRun(int $jobId): ?array;                       // the running row for a job, or null
    public function runsFor(string $db, int $finishedLimit = 50): array;  // running rows + newest finished rows, newest first
    public function latestRunForPid(int $pid, string $host): ?array;
    public function release(): void;                                       // closes the session (releases every lock)
}
```

- [ ] **Step 1: Migration**

Append to `Sql::gc2scheduler()`:

```php
        // Scheduler run registry: started_jobs gains lifecycle columns; locks
        // themselves are Postgres advisory locks (see app/inc/SchedulerLock.php).
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN started_at TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT now()";
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN heartbeat TIMESTAMP WITH TIME ZONE";
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN finished_at TIMESTAMP WITH TIME ZONE";
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'running'";
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN host VARCHAR(255)";
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN slot INTEGER";
        $sqls[] = "ALTER TABLE started_jobs ADD COLUMN exit_reason TEXT";
        $sqls[] = "ALTER TABLE started_jobs ADD CONSTRAINT started_jobs_status_check CHECK (status IN ('running', 'succeeded', 'failed', 'skipped', 'lost'))";
        $sqls[] = "CREATE INDEX started_jobs_running_idx ON started_jobs (id) WHERE status = 'running'";
```

Apply in dev:

```bash
docker exec postgres psql -U mydb -d gc2scheduler -c "ALTER TABLE started_jobs ADD COLUMN started_at TIMESTAMPTZ NOT NULL DEFAULT now(), ADD COLUMN heartbeat TIMESTAMPTZ, ADD COLUMN finished_at TIMESTAMPTZ, ADD COLUMN status VARCHAR(16) NOT NULL DEFAULT 'running', ADD COLUMN host VARCHAR(255), ADD COLUMN slot INTEGER, ADD COLUMN exit_reason TEXT; ALTER TABLE started_jobs ADD CONSTRAINT started_jobs_status_check CHECK (status IN ('running','succeeded','failed','skipped','lost')); CREATE INDEX started_jobs_running_idx ON started_jobs (id) WHERE status = 'running'"
```

Expected: `ALTER TABLE`, `ALTER TABLE`, `CREATE INDEX`.

- [ ] **Step 2: Write the failing test**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\SchedulerLock;
use Codeception\Test\Unit;

/**
 * Advisory-lock based scheduler locking against the real gc2scheduler
 * database. Every SchedulerLock instance is its own Postgres session, so two
 * instances behave like two get.php processes.
 */
class SchedulerLockTest extends Unit
{
    protected UnitTester $tester;

    /** @var SchedulerLock[] */
    private array $sessions = [];
    private int $jobId;

    protected function _before(): void
    {
        // A job id nobody else uses: negative ids never occur in the jobs table.
        $this->jobId = -random_int(1000, 999999);
    }

    protected function _after(): void
    {
        foreach ($this->sessions as $s) {
            $s->release();
        }
        $this->sessions = [];
        $pdo = new PDO("pgsql:dbname=gc2scheduler;host=" . getenv('POSTGRES_HOST') . ";port=" . (getenv('POSTGRES_PORT') ?: 5432), getenv('POSTGRES_USER'), getenv('POSTGRES_PASSWORD'));
        $pdo->exec("DELETE FROM started_jobs WHERE id < 0 OR db = 'schedlocktest'");
    }

    private function session(): SchedulerLock
    {
        try {
            $s = new SchedulerLock(new Connection(database: 'gc2scheduler'));
        } catch (Throwable $e) {
            $this->markTestSkipped('gc2scheduler not reachable: ' . $e->getMessage());
        }
        $this->sessions[] = $s;
        return $s;
    }

    public function testJobLockIsExclusivePerJobAndReleasedWithTheSession(): void
    {
        $a = $this->session();
        $b = $this->session();
        $this->assertTrue($a->tryJobLock($this->jobId));
        $this->assertFalse($b->tryJobLock($this->jobId), 'second session must not get the same job lock');
        $this->assertTrue($b->tryJobLock($this->jobId - 1), 'a different job is independent');

        $a->release();
        array_shift($this->sessions);
        $this->assertTrue($b->tryJobLock($this->jobId), 'closing the holder releases the lock');
    }

    public function testSlotsAreLimitedAndReusable(): void
    {
        $a = $this->session();
        $b = $this->session();
        $c = $this->session();
        $this->assertSame(1, $a->acquireSlot(2));
        $this->assertSame(2, $b->acquireSlot(2));
        $this->assertFalse($c->trySlot(1));
        $this->assertFalse($c->trySlot(2));

        $waits = 0;
        // With no free slot acquireSlot waits; release A from the wait callback so the loop ends.
        $slot = $c->acquireSlot(2, function () use (&$waits, $a) {
            $waits++;
            $a->release();
        }, 1);
        array_shift($this->sessions);
        $this->assertSame(1, $slot);
        $this->assertGreaterThanOrEqual(1, $waits);
    }

    public function testRegistryLifecycle(): void
    {
        $s = $this->session();
        $this->assertTrue($s->tryJobLock($this->jobId));
        $uuid = $s->startRun($this->jobId, 'schedlocktest', 'lock test', 4242, 1, 'unit-host');
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $uuid);

        $run = $s->runningRun($this->jobId);
        $this->assertSame($uuid, $run['uuid']);
        $this->assertSame('running', $run['status']);
        $this->assertSame('unit-host', $run['host']);
        $this->assertSame(1, (int)$run['slot']);
        $this->assertNull($run['heartbeat']);

        $s->heartbeat($uuid);
        $this->assertNotNull($s->runningRun($this->jobId)['heartbeat']);

        $this->assertContains($uuid, array_column($s->runsFor('schedlocktest'), 'uuid'), 'runsFor lists the running row');
        $this->assertSame($uuid, $s->latestRunForPid(4242, 'unit-host')['uuid']);

        $s->finishRun($uuid, 'failed', 'boom');
        $this->assertNull($s->runningRun($this->jobId));
        $finished = array_values(array_filter($s->runsFor('schedlocktest'), fn($r) => $r['uuid'] === $uuid))[0];
        $this->assertSame('failed', $finished['status']);
        $this->assertSame('boom', $finished['exit_reason']);
        $this->assertNotNull($finished['finished_at']);
    }

    public function testSkippedRowIsRecordedAndNotRunning(): void
    {
        $s = $this->session();
        $uuid = $s->recordSkipped($this->jobId, 'schedlocktest', 'lock test', 4243, 'unit-host', 'already running');
        $this->assertNull($s->runningRun($this->jobId));
        $row = array_values(array_filter($s->runsFor('schedlocktest'), fn($r) => $r['uuid'] === $uuid))[0];
        $this->assertSame('skipped', $row['status']);
        $this->assertNotNull($row['finished_at']);
    }

    public function testReaperMarksUnlockedRunningRowsLostAndLeavesLockedOnes(): void
    {
        $holder = $this->session();
        $this->assertTrue($holder->tryJobLock($this->jobId));
        $live = $holder->startRun($this->jobId, 'schedlocktest', 'live', 1, 1, 'unit-host');

        $deadJob = $this->jobId - 7;
        $dead = $holder->startRun($deadJob, 'schedlocktest', 'dead', 2, 2, 'unit-host'); // no lock for this id

        $other = $this->session();
        $lost = $other->reap();
        $this->assertGreaterThanOrEqual(1, $lost);
        $this->assertSame('running', $holder->runningRun($this->jobId)['status'], 'a row whose lock is held stays running');
        $this->assertNull($holder->runningRun($deadJob), 'a row without a lock is lost');
        $deadRow = array_values(array_filter($holder->runsFor('schedlocktest'), fn($r) => $r['uuid'] === $dead))[0];
        $this->assertSame('lost', $deadRow['status']);
        $this->assertSame($live, $holder->runningRun($this->jobId)['uuid']);
    }
}
```

- [ ] **Step 3: Run to verify failure**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SchedulerLockTest.php`
Expected: `Class "app\inc\SchedulerLock" not found`.

- [ ] **Step 4: Implement `SchedulerLock`**

```php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\inc;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Scheduler job locking on Postgres session advisory locks, plus the run
 * registry in gc2scheduler.started_jobs.
 *
 * One instance is one dedicated Postgres session (its own PDO, deliberately
 * outside Model's per-connect-string cache): the locks it takes live exactly
 * as long as the process, so a crashed or killed job never leaves a stale
 * lock behind. Two kinds of locks:
 *   - job lock  (JOB_LOCK_CLASS, jobId): at most one run per job;
 *   - run slot  (SLOT_LOCK_CLASS, 1..N): at most N runs at a time.
 *
 * The session must talk to Postgres directly or through a session-mode
 * pooler; transaction pooling would drop the locks between statements.
 */
final class SchedulerLock
{
    public const int JOB_LOCK_CLASS = 42001;
    public const int SLOT_LOCK_CLASS = 42002;
    public const int DEFAULT_MAX_JOBS = 20;

    private PDO $pdo;

    public function __construct(?Connection $connection = null)
    {
        $c = $connection ?? new Connection(database: 'gc2scheduler', pgbouncer: false);
        $dsn = "pgsql:dbname={$c->database};host={$c->host};port={$c->port};client_encoding=UTF8";
        $this->pdo = new PDO($dsn, $c->user, $c->password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => true,
        ]);
        // The session may live for hours while the job imports; nothing here runs in a transaction.
        $this->pdo->exec("SET statement_timeout = 0");
        $this->pdo->exec("SET idle_in_transaction_session_timeout = 0");
    }

    public function tryJobLock(int $jobId): bool
    {
        return $this->tryLock(self::JOB_LOCK_CLASS, $jobId);
    }

    public function trySlot(int $slot): bool
    {
        return $this->tryLock(self::SLOT_LOCK_CLASS, $slot);
    }

    /**
     * Takes the first free run slot, waiting while all are busy. $onWait is
     * called before every sleep (for logging/reporting).
     */
    public function acquireSlot(int $maxJobs, ?callable $onWait = null, int $sleepSeconds = 10): int
    {
        $maxJobs = max(1, $maxJobs);
        while (true) {
            for ($slot = 1; $slot <= $maxJobs; $slot++) {
                if ($this->trySlot($slot)) {
                    return $slot;
                }
            }
            if ($onWait !== null) {
                $onWait($maxJobs, $sleepSeconds);
            }
            sleep($sleepSeconds);
        }
    }

    /**
     * Marks running rows whose job lock nobody holds as lost. Reads pg_locks
     * only; it never takes a lock itself.
     *
     * @return int rows marked lost
     */
    public function reap(): int
    {
        $sql = "UPDATE started_jobs s SET status = 'lost', finished_at = now(), exit_reason = 'lock not held; process gone'
                WHERE s.status = 'running'
                  AND NOT EXISTS (
                      SELECT 1 FROM pg_locks l
                      WHERE l.locktype = 'advisory' AND l.classid = :class AND l.objid = s.id::oid AND l.objsubid = 2 AND l.granted
                  )";
        $st = $this->pdo->prepare($sql);
        $st->execute(['class' => self::JOB_LOCK_CLASS]);
        return $st->rowCount();
    }

    public function startRun(int $jobId, string $db, ?string $name, int $pid, int $slot, string $host): string
    {
        $st = $this->pdo->prepare("INSERT INTO started_jobs (id, db, name, pid, slot, host, status) VALUES (:id, :db, :name, :pid, :slot, :host, 'running') RETURNING uuid");
        $st->execute(['id' => $jobId, 'db' => $db, 'name' => $name, 'pid' => $pid, 'slot' => $slot, 'host' => $host]);
        return $st->fetchColumn();
    }

    public function recordSkipped(int $jobId, string $db, ?string $name, int $pid, string $host, string $reason): string
    {
        $st = $this->pdo->prepare("INSERT INTO started_jobs (id, db, name, pid, host, status, finished_at, exit_reason) VALUES (:id, :db, :name, :pid, :host, 'skipped', now(), :reason) RETURNING uuid");
        $st->execute(['id' => $jobId, 'db' => $db, 'name' => $name, 'pid' => $pid, 'host' => $host, 'reason' => $reason]);
        return $st->fetchColumn();
    }

    public function finishRun(string $uuid, string $status, ?string $reason = null): void
    {
        if (!in_array($status, ['succeeded', 'failed', 'lost'], true)) {
            throw new RuntimeException("Not a final status: $status");
        }
        $st = $this->pdo->prepare("UPDATE started_jobs SET status = :status, finished_at = now(), exit_reason = :reason WHERE uuid = :uuid AND status = 'running'");
        $st->execute(['status' => $status, 'reason' => $reason, 'uuid' => $uuid]);
    }

    public function heartbeat(string $uuid): void
    {
        $st = $this->pdo->prepare("UPDATE started_jobs SET heartbeat = now() WHERE uuid = :uuid AND status = 'running'");
        $st->execute(['uuid' => $uuid]);
    }

    public function runningRun(int $jobId): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM started_jobs WHERE id = :id AND status = 'running' ORDER BY started_at DESC LIMIT 1");
        $st->execute(['id' => $jobId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Running rows first, then the newest finished rows, for one database.
     *
     * @return array<int, array<string, mixed>>
     */
    public function runsFor(string $db, int $finishedLimit = 50): array
    {
        $st = $this->pdo->prepare("(SELECT * FROM started_jobs WHERE db = :db AND status = 'running' ORDER BY started_at DESC)
                                   UNION ALL
                                   (SELECT * FROM started_jobs WHERE db = :db2 AND status <> 'running' ORDER BY started_at DESC LIMIT :lim)");
        $st->bindValue('db', $db);
        $st->bindValue('db2', $db);
        $st->bindValue('lim', max(1, $finishedLimit), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function latestRunForPid(int $pid, string $host): ?array
    {
        $st = $this->pdo->prepare("SELECT * FROM started_jobs WHERE pid = :pid AND host = :host ORDER BY started_at DESC LIMIT 1");
        $st->execute(['pid' => $pid, 'host' => $host]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Ends the session, which releases every lock it holds. */
    public function release(): void
    {
        unset($this->pdo);
    }

    private function tryLock(int $class, int $key): bool
    {
        try {
            $st = $this->pdo->prepare("SELECT pg_try_advisory_lock(:class, :key)");
            $st->bindValue('class', $class, PDO::PARAM_INT);
            $st->bindValue('key', $key, PDO::PARAM_INT);
            $st->execute();
            return (bool)$st->fetchColumn();
        } catch (PDOException $e) {
            throw new RuntimeException("Advisory lock failed: " . $e->getMessage(), 0, $e);
        }
    }
}
```

Notes: the two-argument advisory lock shows up in `pg_locks` with `classid`/`objid` as unsigned `oid` values and `objsubid = 2`; `s.id::oid` reinterprets the int4 job id the same way Postgres does, so negative test ids also match. `unset($this->pdo)` on a typed property makes later use throw, which is intended.

- [ ] **Step 5: Run the test until green**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit SchedulerLockTest.php`
Expected: `OK (5 tests, ...)`. If the reaper test fails, inspect `SELECT classid, objid, objsubid, granted FROM pg_locks WHERE locktype = 'advisory'` while a session holds a lock, and adjust the join (cast) accordingly.

- [ ] **Step 6: Commit**

```bash
git add app/migration/Sql.php app/inc/SchedulerLock.php app/tests/unit/SchedulerLockTest.php
git commit -m "feat(scheduler): SchedulerLock on Postgres advisory locks and a run registry in started_jobs"
```

---

### Task 2: get.php uses SchedulerLock

**Files:**
- Modify: `app/scripts/get.php`

**Interfaces:**
- Consumes: `SchedulerLock` (Task 1).
- Produces: get.php exits 0 with a `skipped` row when the job is already running; writes/updates its `started_jobs` row itself; no lock files.

- [ ] **Step 1: Replace the lock-file setup**

Replace the block that defines `$lockDir`/`$lockFile` and touches the file (lines ~89-101) with:

```php
$tmpDir = "/var/www/geocloud2/app/tmp/";

// Locking and run registry live in gc2scheduler (Postgres advisory locks),
// on a dedicated session that lasts for the whole run. See app/inc/SchedulerLock.php.
$runHost = gethostname() ?: 'unknown';
$runPid = getmypid();
$schedulerLock = new SchedulerLock();
$schedulerLock->reap();
if (!$schedulerLock->tryJobLock((int)$jobId)) {
    $running = $schedulerLock->runningRun((int)$jobId);
    $reason = "already running" . ($running ? " (run {$running['uuid']}, started {$running['started_at']}, host {$running['host']})" : "");
    $schedulerLock->recordSkipped((int)$jobId, $db, $safeName, $runPid, $runHost, $reason);
    print "\nInfo: Job {$jobId} is {$reason}. Exiting.";
    exit(0);
}
$runUuid = null; // set once a slot is held and the run is registered
```

Add `use app\inc\SchedulerLock;` next to the other imports. Delete the `$lockDir`/`$lockFile`/`touch` lines and both `$lockDir = …` definitions.

- [ ] **Step 2: Replace `poll()`**

Replace the `poll()` function and its call with:

```php
// Wait for a run slot, register the run, then download
// ======================================================
$maxJobs = (int)(App::$param['gc2scheduler']['maxJobs'] ?? SchedulerLock::DEFAULT_MAX_JOBS);
$slot = $schedulerLock->acquireSlot($maxJobs, function (int $max, int $sleep) use (&$report) {
    print "\nInfo: All {$max} run slots are busy. Waiting {$sleep} seconds...";
    $report[SLEEP] += $sleep;
});
$runUuid = $schedulerLock->startRun((int)$jobId, $db, $safeName, $runPid, $slot, $runHost);
print "\nInfo: Run {$runUuid} registered on slot {$slot}";
$getFunction();
```

(`$report[SLEEP]` must exist before this: check that `$report[SLEEP] = 0` is initialised where `$report = []` is, or add it.)

- [ ] **Step 3: Finalise the run from `cleanUp()`, a shutdown function and SIGINT**

In `cleanUp(int $success = 0)`: remove `unlink($lockFile);` and `$lockFile` from the `global` list; add `$schedulerLock, $runUuid, $lastError` to the globals; at the very end of `cleanUp()` (after the snapshot enqueue) add:

```php
    if ($runUuid !== null) {
        $schedulerLock->finishRun($runUuid, $success ? 'succeeded' : 'failed', $success ? null : ($lastError ?? 'see job log'));
    }
```

Right after the lock setup block from Step 1, register the safety nets:

```php
// Bookkeeping when the process dies without reaching cleanUp(): the locks
// are released by Postgres regardless; this only keeps the registry honest.
register_shutdown_function(function () use (&$schedulerLock, &$runUuid) {
    if ($runUuid === null) {
        return;
    }
    $err = error_get_last();
    $reason = $err !== null ? "terminated: " . $err['message'] : "terminated";
    try {
        $schedulerLock->finishRun($runUuid, 'failed', $reason); // no-op if cleanUp() already finalised
    } catch (Throwable) {
    }
});
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () use (&$schedulerLock, &$runUuid) {
        print "\nError: Terminated by SIGINT (timeout).";
        if ($runUuid !== null) {
            try {
                $schedulerLock->finishRun($runUuid, 'failed', 'timeout');
            } catch (Throwable) {
            }
        }
        exit(130);
    });
    pcntl_signal(SIGTERM, function () use (&$schedulerLock, &$runUuid) {
        if ($runUuid !== null) {
            try {
                $schedulerLock->finishRun($runUuid, 'failed', 'terminated');
            } catch (Throwable) {
            }
        }
        exit(143);
    });
}
```

Track `$lastError`: where get.php prints `"Error: "` before `cleanUp(); exit(1);` (the ogr2ogr failure branches), set `$lastError = <the message string>` first; a global `$lastError = null;` near `$report = [];`. Keep it minimal: the ogr2ogr output check (`if ($err)…` near "Check output") and the PDO `catch` blocks that print `$e->getMessage()`.

- [ ] **Step 4: Heartbeat**

In `fetchPart()` (top, after `$out = [];`): `global $schedulerLock, $runUuid; if ($runUuid !== null) { $schedulerLock->heartbeat($runUuid); }`. In `getCmd()`, `getCmdFile()` and `getCmdZip()` add the same two lines at their start. That gives a heartbeat per page/cell and at least one per run.

- [ ] **Step 5: Release at the end**

Before the final `exit(0);` at the bottom of get.php: `$schedulerLock->release();` (after `cleanUp(1)`).

- [ ] **Step 6: Verify by hand**

Lint: `docker exec -w /var/www/geocloud2 docker-dev-1 php -l app/scripts/get.php`.

Run two copies of the same fake job concurrently against a tiny source (a GeoJSON file dropped into `public/`), then read the registry:

```bash
docker exec docker-dev-1 sh -c 'printf "{\"type\":\"FeatureCollection\",\"features\":[{\"type\":\"Feature\",\"properties\":{\"id\":1},\"geometry\":{\"type\":\"Point\",\"coordinates\":[10,56]}}]}" > /var/www/geocloud2/public/locktest.geojson'
DB=<a gc2 database that has run a job before, e.g. mydb>
for j in 1 2; do docker exec -d -w /var/www/geocloud2/app docker-dev-1 sudo -u www-data php -f scripts/get.php -- --db $DB --schema public --safeName locktest --url "http://localhost/locktest.geojson" --srid 4326 --type AUTO --encoding UTF8 --jobId 999990 --deleteAppend 0 --extra null --preSql null --postSql null --downloadSchema 0 --snapshot 0; done
sleep 20
docker exec postgres psql -U mydb -d gc2scheduler -c "SELECT id, status, slot, host, exit_reason, started_at, finished_at FROM started_jobs WHERE id = 999990 ORDER BY started_at"
```

Expected: one `succeeded` row (with slot and host) and one `skipped` row whose `exit_reason` names the running run. Then the kill test:

```bash
docker exec -d -w /var/www/geocloud2/app docker-dev-1 sudo -u www-data php -f scripts/get.php -- … --jobId 999991 …   # same args, a slow source or just kill quickly
docker exec docker-dev-1 sh -c 'sleep 1; pkill -9 -f "jobId 999991"'
docker exec -w /var/www/geocloud2/app docker-dev-1 php -r 'include "conf/App.php"; include "vendor/autoload.php"; new app\conf\App(); $l = new app\inc\SchedulerLock(); echo "lost=" . $l->reap() . "\n"; var_dump($l->tryJobLock(999991));'
```

Expected: `lost=1` (or 0 if the shutdown function ran first and marked it failed) and `bool(true)` — the job can be started again immediately. Remove `public/locktest.geojson` afterwards.

- [ ] **Step 7: Commit**

```bash
git add app/scripts/get.php
git commit -m "feat(scheduler): get.php locks jobs and run slots in Postgres and keeps the run registry"
```

---

### Task 3: `Job::runJob` and the v3 scheduler API read the registry

**Files:**
- Modify: `app/models/Job.php`, `app/api/v3/Scheduler.php`, `public/index.php`
- Test: `app/tests/api/SchedulerApiCest.php`

**Interfaces:**
- Consumes: `SchedulerLock::runsFor`, `latestRunForPid`, `reap`.
- Produces: `GET /api/v3/scheduler` → `{"jobs": [{uuid, id, name, pid, host, slot, status, started_at, heartbeat, finished_at, exit_reason, stale}]}`; `DELETE /api/v3/scheduler/{uuid}` → `{"success": true, "signal": "SIGINT"|"SIGKILL"}` or 409 when the run is on another host / 404 unknown.

- [ ] **Step 1: Write the failing API test**

```php
<?php

use Codeception\Util\HttpCode;

/**
 * Scheduler run registry through the v3 API. Runs get.php twice for the same
 * fake job id inside the container and expects one real run and one skipped
 * run to be listed. Ordered.
 */
class SchedulerApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $jobId;

    public function __construct()
    {
        $this->jobId = 900000 + (int)substr((string)time(), -5);
    }

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUserAndSource(ApiTester $I)
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => 'sched super ' . $ts, 'email' => 'schedsuper' . $ts . '@example.com', 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;
        file_put_contents('/var/www/geocloud2/public/schedtest.geojson', '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"id":1},"geometry":{"type":"Point","coordinates":[10,56]}}]}');
    }

    public function shouldListOneRunAndOneSkippedAfterADoubleStart(ApiTester $I)
    {
        $args = '--db ' . escapeshellarg($this->userId) . ' --schema public --safeName schedtest --url "http://localhost/schedtest.geojson" --srid 4326 --type AUTO --encoding UTF8 --jobId ' . $this->jobId
            . ' --deleteAppend 0 --extra null --preSql null --postSql null --downloadSchema 0 --snapshot 0';
        $cmd = 'php -f /var/www/geocloud2/app/scripts/get.php -- ' . $args . ' > /dev/null 2>&1 &';
        shell_exec($cmd);
        shell_exec($cmd);
        sleep(15);

        $this->asSuper($I);
        $I->sendGET('/api/v3/scheduler');
        $I->seeResponseCodeIs(HttpCode::OK);
        $jobs = array_values(array_filter(json_decode($I->grabResponse())->jobs, fn($j) => (int)$j->id === $this->jobId));
        $I->assertCount(2, $jobs, 'one real run and one skipped run');
        $statuses = array_map(fn($j) => $j->status, $jobs);
        sort($statuses);
        $I->assertContains('skipped', $statuses);
        $I->assertTrue(in_array('succeeded', $statuses, true) || in_array('running', $statuses, true) || in_array('failed', $statuses, true), 'the other run is real: ' . json_encode($statuses));
        foreach ($jobs as $j) {
            $I->assertTrue(property_exists($j, 'host'));
            $I->assertTrue(property_exists($j, 'started_at'));
            $I->assertTrue(property_exists($j, 'exit_reason'));
            $I->assertTrue(property_exists($j, 'stale'));
        }
    }

    public function shouldAnswer404ForUnknownRunOnDelete(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v3/scheduler/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        @unlink('/var/www/geocloud2/public/schedtest.geojson');
    }
}
```

- [ ] **Step 2: Run to verify failure**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SchedulerApiCest.php`
Expected: the listing test fails on the new fields (`host`), the delete test fails with 404/405 from routing (no route yet) — note what it says.

- [ ] **Step 3: `Job.php`**

Replace the body of `runJob()` after `$cmd` is built:

```php
        if ($cmd) {
            $pid = (int)exec($cmd . " > " . __DIR__ . "/../../public/logs/{$job["id"]}_scheduler.log  </dev/null & echo $!");
            if (!$async) {
                // get.php registers itself in started_jobs (see SchedulerLock); wait until that run is over.
                $lock = new SchedulerLock();
                $host = gethostname() ?: 'unknown';
                $waited = 0;
                do {
                    sleep(1);
                    $waited++;
                    $run = $lock->latestRunForPid($this->childPidOf($pid) ?? $pid, $host);
                    if ($run !== null && $run['status'] !== 'running') {
                        break;
                    }
                    if ($run === null && $waited > 30 && !$this->isAlive($pid)) {
                        break; // died before registering
                    }
                } while (true);
                $lock->release();
            }
        }
        return true;
```

The `pid` returned by `exec(... & echo $!)` is the `nohup timeout` wrapper's pid, while get.php registers its own pid (`getmypid()`), i.e. the child of `timeout`. Add:

```php
    /** The pid of the php process under a `timeout` wrapper pid, or null. */
    private function childPidOf(int $wrapperPid): ?int
    {
        $out = [];
        exec("pgrep -P " . (int)$wrapperPid, $out);
        return isset($out[0]) && ctype_digit($out[0]) ? (int)$out[0] : null;
    }

    private function isAlive(int $pid): bool
    {
        return function_exists('posix_kill') ? posix_kill($pid, 0) : file_exists("/proc/$pid");
    }
```

Remove `insert()` (no caller left) and change `getAllStartedJobs()`:

```php
    /**
     * Runs of this database: running first, then the newest finished ones.
     */
    public function getAllStartedJobs(string $db): array
    {
        $lock = new SchedulerLock();
        $lock->reap();
        $rows = $lock->runsFor($db);
        $lock->release();
        return $rows;
    }
```

`kill()`: SIGINT first, SIGKILL after 30 s:

```php
    private function kill(int $pid): void
    {
        exec("/bin/kill -INT $pid");
        for ($i = 0; $i < 30 && $this->isAlive($pid); $i++) {
            sleep(1);
        }
        if ($this->isAlive($pid)) {
            exec("/bin/kill -9 $pid");
        }
    }
```

Make `kill()` `public` (the API needs it) and add `use app\inc\SchedulerLock;`.

- [ ] **Step 4: `app/api/v3/Scheduler.php`**

`get_index()`:

```php
    public function get_index(): array
    {
        $res = [];
        foreach ($this->job->getAllStartedJobs($this->db) as $r) {
            $stale = $r['status'] === 'running'
                && $r['heartbeat'] !== null
                && (time() - strtotime($r['heartbeat'])) > 300;
            $res[] = [
                "uuid" => $r["uuid"], "id" => (int)$r["id"], "name" => $r["name"], "pid" => (int)$r["pid"],
                "host" => $r["host"], "slot" => $r["slot"] !== null ? (int)$r["slot"] : null,
                "status" => $r["status"], "started_at" => $r["started_at"], "heartbeat" => $r["heartbeat"],
                "finished_at" => $r["finished_at"], "exit_reason" => $r["exit_reason"], "stale" => $stale,
            ];
        }
        return ["jobs" => $res];
    }
```

Add:

```php
    /**
     * Stops a running run on this host: SIGINT first (so get.php records
     * "terminated"), SIGKILL after 30 s.
     */
    #[OA\Delete(path: '/api/v3/scheduler/{uuid}', operationId: 'stopSchedulerRun', tags: ['Scheduler'])]
    #[OA\Parameter(name: 'uuid', description: 'Run uuid from GET /api/v3/scheduler', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Signal sent')]
    #[OA\Response(response: 404, description: 'No running run with that uuid')]
    #[OA\Response(response: 409, description: 'The run is on another host')]
    public function delete_index(): array
    {
        $uuid = Route::getParam("uuid");
        $run = null;
        foreach ($this->job->getAllStartedJobs($this->db) as $r) {
            if ($r['uuid'] === $uuid && $r['status'] === 'running') {
                $run = $r;
            }
        }
        if ($run === null) {
            throw new GC2Exception("No running run with uuid $uuid", 404, null, "NO_RUN");
        }
        $host = gethostname() ?: 'unknown';
        if ($run['host'] !== $host) {
            throw new GC2Exception("Run $uuid is on host {$run['host']}, not $host", 409, null, "RUN_ON_OTHER_HOST");
        }
        $this->job->kill((int)$run['pid']);
        return ["success" => true, "uuid" => $uuid, "signal" => "SIGINT"];
    }
```

Update the OpenAPI response schema of `get_index` to the new fields. Confirm how a v3 controller's thrown `GC2Exception` maps to the HTTP status (other v3 controllers throw the same way).

- [ ] **Step 5: Route**

In `public/index.php`, next to the existing `Route::add("api/v3/scheduler", …)`, add the same closure for `"api/v3/scheduler/{uuid}"` (copy the block, change only the path). Order: add the `{uuid}` route before the bare one if `Route::add` matches prefixes first — check how `api/v3/tileseeder/{action}/{uuid}` is ordered relative to `api/v3/tileseeder/` (the parameterised one comes first there) and mirror it.

- [ ] **Step 6: Run the Cest and a regression**

Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run api SchedulerApiCest.php` → `OK (3 tests, ...)`.
Run: `docker exec -w /var/www/geocloud2/app docker-dev-1 php vendor/bin/codecept run unit JobSnapshotFlagTest.php` → `OK` (Job.php still constructs and binds correctly).

- [ ] **Step 7: Commit**

```bash
git add app/models/Job.php app/api/v3/Scheduler.php public/index.php app/tests/api/SchedulerApiCest.php
git commit -m "feat(scheduler): run registry in the v3 scheduler API and a stop endpoint; runJob waits on the registry"
```

---

### Task 4: Reaper in scheduler.php, remove purge_locks, config template

**Files:**
- Modify: `app/scripts/scheduler.php`, `docker/Dockerfile`, `docker/conf/gc2/App.php`
- Delete: `app/scripts/purge_locks.php`

- [ ] **Step 1: scheduler.php**

After `new App();` and `Database::setDb("gc2scheduler");` add:

```php
// Mark runs whose process died without bookkeeping as lost (their advisory
// lock is gone), so the API and the UI never show phantom running jobs.
try {
    $lost = (new \app\inc\SchedulerLock())->reap();
    if ($lost > 0) {
        echo "Marked $lost lost run(s)\n";
    }
} catch (Throwable $e) {
    error_log("scheduler: reaper failed: " . $e->getMessage());
}
```

- [ ] **Step 2: Remove purge_locks**

`git rm app/scripts/purge_locks.php`; delete the `purge_locks.php` crontab line from `docker/Dockerfile` (search for it). Grep the repo for `scheduler_locks` and `purge_locks` — no references may remain except in docs/specs.

- [ ] **Step 3: Config template**

In `docker/conf/gc2/App.php`, find the `"gc2scheduler"` block (it maps databases to `true`); add the key with a comment:

```php
            // Maximum number of scheduler jobs importing at the same time (advisory-lock run slots).
            "maxJobs" => 20,
```

Add the same key to the local gitignored `app/conf/App.php`. Do not commit that file.

- [ ] **Step 4: Verify**

`php -l` on scheduler.php; run it once by hand inside the container: `docker exec -w /var/www/geocloud2/app docker-dev-1 sudo -u www-data php -f scripts/scheduler.php | head -5` (expect no error; maybe a "Marked N lost run(s)" line the first time, since pre-migration rows default to `running`).

- [ ] **Step 5: Commit**

```bash
git add app/scripts/scheduler.php docker/Dockerfile docker/conf/gc2/App.php
git rm -q app/scripts/purge_locks.php
git commit -m "chore(scheduler): reap lost runs each tick, drop purge_locks, add gc2scheduler.maxJobs"
```

---

### Task 5: v4 job management API (`SchedulerJob`)

**Files:**
- Modify: `app/models/Job.php` (add `getById`, `createJob`, `patchJob`, `deleteJobById`)
- Create: `app/api/v4/controllers/SchedulerJob.php`
- Test: `app/tests/api/SchedulerJobV4ApiCest.php`

**Interfaces:**
- Consumes: `SchedulerLock::runningRun(int)` (Task 1) for the 409 on delete; `Cron\CronExpression` (installed, used by `Job::validateCronExpression`).
- Produces (used by Task 6):

```php
public function getById(int $id, string $db): ?array;                 // row or null
public function createJob(array $fields, string $db): int;            // new id; $fields = resource keys (schedule split into the five columns)
public function patchJob(int $id, string $db, array $fields): void;   // only the given keys; throws 404 GC2Exception when not owned
public function deleteJobById(int $id, string $db): void;             // throws 404 when not owned
```

- [ ] **Step 1: Write the failing Cest**

```php
<?php

use Codeception\Util\HttpCode;

/**
 * v4 scheduler job CRUD (app/api/v4/controllers/SchedulerJob.php). Super-user
 * only; jobs are scoped to the JWT's database. Ordered/stateful.
 */
class SchedulerJobV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $otherToken;
    private $jobId;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUsers(ApiTester $I)
    {
        $ts = time();
        foreach (['a', 'b'] as $k) {
            $I->haveHttpHeader('Content-Type', 'application/json');
            $I->sendPOST('/api/v2/user', json_encode(['name' => "schedjob $k $ts", 'email' => "schedjob$k$ts@example.com", 'password' => $this->password]));
            $I->seeResponseCodeIs(HttpCode::OK);
            $uid = json_decode($I->grabResponse())->data->screenname;
            $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $uid, 'password' => $this->password, 'database' => $uid, 'client_id' => 'gc2-cli']));
            $I->seeResponseCodeIs(HttpCode::CREATED);
            $tok = json_decode($I->grabResponse())->access_token;
            if ($k === 'a') { $this->userId = $uid; $this->token = $tok; } else { $this->otherToken = $tok; }
        }
    }

    public function shouldCreateJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode([
            'name' => 'My Job', 'schema' => 'public', 'url' => 'https://example.com/data.zip', 'schedule' => '15 3 * * 1-5',
            'epsg' => 25832, 'snapshot' => true,
        ]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $loc = $I->grabHttpHeader('Location');
        $I->assertMatchesRegularExpression('#/api/v4/scheduler/jobs/\d+$#', $loc);
        $this->jobId = (int)basename($loc);

        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([
            'id' => $this->jobId, 'name' => 'my_job', 'schema' => 'public', 'schedule' => '15 3 * * 1-5',
            'epsg' => 25832, 'type' => 'AUTO', 'encoding' => 'UTF8', 'delete_append' => false, 'download_schema' => true,
            'active' => true, 'snapshot' => true,
        ]);
    }

    public function shouldValidateOnCreate(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'x', 'schema' => 'public', 'url' => 'https://e.com/a', 'schedule' => 'every day']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'x', 'schema' => 'public', 'schedule' => '* * * * *']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'x', 'schema' => 'public', 'url' => 'https://e.com/a', 'schedule' => '* * * * *', 'epsg' => 'abc']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldListAndPatch(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/scheduler/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson([['id' => $this->jobId]]);

        $I->sendPATCH('/api/v4/scheduler/jobs/' . $this->jobId, json_encode(['active' => false, 'schedule' => '0 4 * * *', 'presql' => 'SELECT 1']));
        $I->seeResponseCodeIs(HttpCode::SEE_OTHER);
        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseContainsJson(['active' => false, 'schedule' => '0 4 * * *', 'presql' => 'SELECT 1', 'snapshot' => true]);

        $I->sendPATCH('/api/v4/scheduler/jobs/' . $this->jobId, json_encode(['schedule' => 'nope']));
        $I->seeResponseCodeIs(HttpCode::BAD_REQUEST);
    }

    public function shouldScopeJobsToTheCallersDatabase(ApiTester $I)
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->otherToken);
        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'JOB_NOT_FOUND']);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->sendGET('/api/v4/scheduler/jobs');
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->assertEquals([], json_decode($I->grabResponse()));
    }

    public function shouldDeleteJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NO_CONTENT);
        $I->sendGET('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
    }
}
```

- [ ] **Step 2: Run to verify failure** — expected 404 on POST (no route).

- [ ] **Step 3: Model methods (append to `app\models\Job`)**

```php
    private const array WRITABLE = ['name', 'schema', 'url', 'schedule', 'epsg', 'type', 'encoding', 'extra',
        'delete_append', 'download_schema', 'presql', 'postsql', 'active', 'snapshot'];
    private const array BOOLS = ['delete_append', 'download_schema', 'active', 'snapshot'];

    public function getById(int $id, string $db): ?array
    {
        $res = $this->prepare("SELECT * FROM jobs WHERE id = :id AND db = :db");
        $this->execute($res, ['id' => $id, 'db' => $db]);
        $row = $this->fetchRow($res);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $fields resource keys (see SchedulerJob); schedule is "min hour dom mon dow"
     * @throws GC2Exception 400 on an invalid schedule
     */
    public function createJob(array $fields, string $db): int
    {
        $cols = $this->toColumns($fields + ['epsg' => 4326, 'type' => 'AUTO', 'encoding' => 'UTF8', 'delete_append' => false, 'download_schema' => true, 'active' => true, 'snapshot' => false]);
        $cols['db'] = $db;
        $names = array_keys($cols);
        $sql = "INSERT INTO jobs (" . implode(', ', $names) . ") VALUES (:" . implode(', :', $names) . ") RETURNING id";
        $res = $this->prepare($sql);
        $this->execute($res, $cols);
        return (int)$res->fetchColumn();
    }

    /** @throws GC2Exception 404 when the job is not in $db, 400 on an invalid schedule */
    public function patchJob(int $id, string $db, array $fields): void
    {
        if ($this->getById($id, $db) === null) {
            throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
        }
        $cols = $this->toColumns($fields);
        if ($cols === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($cols)));
        $res = $this->prepare("UPDATE jobs SET $sets WHERE id = :id AND db = :db");
        $this->execute($res, $cols + ['id' => $id, 'db' => $db]);
    }

    /** @throws GC2Exception 404 when the job is not in $db */
    public function deleteJobById(int $id, string $db): void
    {
        $res = $this->prepare("DELETE FROM jobs WHERE id = :id AND db = :db");
        $this->execute($res, ['id' => $id, 'db' => $db]);
        if ($res->rowCount() === 0) {
            throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
        }
    }

    /**
     * Resource keys -> jobs columns. Splits schedule into the five cron columns
     * (and mirrors it into the legacy cron column), normalises the name like
     * v2, and binds booleans as 0/1.
     */
    private function toColumns(array $fields): array
    {
        $cols = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, self::WRITABLE, true)) {
                continue;
            }
            if ($k === 'schedule') {
                $parts = preg_split('/\s+/', trim((string)$v));
                if (count($parts) !== 5) {
                    throw new GC2Exception("schedule must have five cron fields", 400, null, "INVALID_CRON_FIELD");
                }
                try {
                    new CronExpression(implode(' ', $parts));
                } catch (InvalidArgumentException $e) {
                    throw new GC2Exception($e->getMessage(), 400, null, "INVALID_CRON_FIELD");
                }
                [$cols['min'], $cols['hour'], $cols['dayofmonth'], $cols['month'], $cols['dayofweek']] = $parts;
                $cols['cron'] = implode(' ', $parts);
            } elseif ($k === 'name') {
                $cols['name'] = Model::toAscii((string)$v, null, "_");
            } elseif (in_array($k, self::BOOLS, true)) {
                $cols[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            } elseif ($k === 'epsg') {
                $cols['epsg'] = (string)(int)$v;
            } else {
                $cols[$k] = $v;
            }
        }
        return $cols;
    }
```

(`Model::toAscii` is what `newJob` already uses; `CronExpression`/`InvalidArgumentException` are already imported in Job.php.)

- [ ] **Step 4: Controller**

`app/api/v4/controllers/SchedulerJob.php`, modelled on `Keyvalue.php`/`Snapshot.php`:

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
```

Note: `Job` is constructed on a `gc2scheduler` connection explicitly, because Route2 hands v4 controllers a connection to the caller's database. Check how `Job::getAll` reads (`SELECT * FROM jobs WHERE db=:db`) — it must run against gc2scheduler; the explicit connection guarantees that. `validateRequest` is expected to reject `epsg: "abc"` (string vs integer) — the existing helper JSON-decodes the body.

- [ ] **Step 5: Run the Cest until green** — `OK (6 tests, ...)`. Also re-run `JobSnapshotFlagTest.php` (Job.php changed).

- [ ] **Step 6: Commit**

```bash
git add app/models/Job.php app/api/v4/controllers/SchedulerJob.php app/tests/api/SchedulerJobV4ApiCest.php
git commit -m "feat(api): v4 scheduler job management (GET/POST/PATCH/DELETE /api/v4/scheduler/jobs)"
```

---

### Task 6: v4 run API (`SchedulerRun`)

**Files:**
- Create: `app/api/v4/controllers/SchedulerRun.php`
- Test: `app/tests/api/SchedulerRunV4ApiCest.php`

**Interfaces:**
- Consumes: `SchedulerLock` (Task 1), `Job::runJob` / `Job::kill` / `getById` (Tasks 3, 5).

- [ ] **Step 1: Write the failing Cest**

```php
<?php

use Codeception\Util\HttpCode;

/**
 * v4 scheduler runs (app/api/v4/controllers/SchedulerRun.php): start a job,
 * list/inspect runs, stop a run. Uses a tiny GeoJSON served by the container
 * as the job source. Ordered/stateful.
 */
class SchedulerRunV4ApiCest
{
    private $password = 'A1abcabcabc';
    private $userId;
    private $token;
    private $jobId;
    private $runUuid;

    private function asSuper(ApiTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('Accept', 'application/json');
        $I->haveHttpHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldPrepareUserSourceAndJob(ApiTester $I)
    {
        $ts = time();
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPOST('/api/v2/user', json_encode(['name' => "schedrun $ts", 'email' => "schedrun$ts@example.com", 'password' => $this->password]));
        $I->seeResponseCodeIs(HttpCode::OK);
        $this->userId = json_decode($I->grabResponse())->data->screenname;
        $I->sendPOST('/api/v4/oauth', json_encode(['grant_type' => 'password', 'username' => $this->userId, 'password' => $this->password, 'database' => $this->userId, 'client_id' => 'gc2-cli']));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->token = json_decode($I->grabResponse())->access_token;
        file_put_contents('/var/www/geocloud2/public/schedrun.geojson', '{"type":"FeatureCollection","features":[{"type":"Feature","properties":{"id":1},"geometry":{"type":"Point","coordinates":[10,56]}}]}');

        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/jobs', json_encode(['name' => 'run test', 'schema' => 'public', 'url' => 'http://localhost/schedrun.geojson', 'schedule' => '0 0 1 1 *', 'active' => false]));
        $I->seeResponseCodeIs(HttpCode::CREATED);
        $this->jobId = (int)basename($I->grabHttpHeader('Location'));
    }

    public function shouldStartAJobAndListItsRun(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendPOST('/api/v4/scheduler/runs', json_encode(['job' => $this->jobId]));
        $I->seeResponseCodeIs(HttpCode::ACCEPTED);
        $I->seeResponseContainsJson(['job' => $this->jobId, 'status' => 'starting']);
        sleep(15);

        $I->sendGET('/api/v4/scheduler/runs?job=' . $this->jobId);
        $I->seeResponseCodeIs(HttpCode::OK);
        $runs = json_decode($I->grabResponse());
        $I->assertGreaterThanOrEqual(1, count($runs));
        $run = $runs[0];
        $I->assertEquals($this->jobId, $run->job);
        $I->assertContains($run->status, ['succeeded', 'failed', 'running'], 'run status: ' . json_encode($run));
        $I->assertTrue(property_exists($run, 'host') && property_exists($run, 'stale') && property_exists($run, 'exit_reason'));
        $this->runUuid = $run->uuid;

        $I->sendGET('/api/v4/scheduler/runs/' . $this->runUuid);
        $I->seeResponseCodeIs(HttpCode::OK);
        $I->seeResponseContainsJson(['uuid' => $this->runUuid, 'job' => $this->jobId]);
    }

    public function shouldReject404AndUnknownJob(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendGET('/api/v4/scheduler/runs/00000000-0000-0000-0000-000000000000');
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'RUN_NOT_FOUND']);
        $I->sendPOST('/api/v4/scheduler/runs', json_encode(['job' => 987654321]));
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND);
        $I->seeResponseContainsJson(['errorCode' => 'JOB_NOT_FOUND']);
        $I->sendDELETE('/api/v4/scheduler/runs/' . $this->runUuid);
        $I->seeResponseCodeIs(HttpCode::NOT_FOUND); // finished runs cannot be stopped
    }

    public function shouldCleanUp(ApiTester $I)
    {
        $this->asSuper($I);
        $I->sendDELETE('/api/v4/scheduler/jobs/' . $this->jobId);
        $I->seeResponseCodeIsSuccessful();
        @unlink('/var/www/geocloud2/public/schedrun.geojson');
    }
}
```

(Stopping a running run is verified manually in Task 7; a job fast enough for a Cest is finished before DELETE can hit it.)

- [ ] **Step 2: Run to verify failure** — 404 on POST /runs.

- [ ] **Step 3: Controller**

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
use app\api\v4\Responses\GetResponse;
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
 * v4 scheduler runs: start a job now, list and inspect runs (the
 * started_jobs registry kept by get.php), stop a running run.
 */
#[OA\OpenApi(openapi: OpenApi::VERSION_3_1_0, security: [['bearerAuth' => []]])]
#[OA\Info(version: '1.0.0', title: 'GC2 API', contact: new OA\Contact(email: 'mh@mapcentia.com'))]
#[OA\Schema(schema: "SchedulerRun", description: "One run of a scheduler job.", properties: [
    new OA\Property(property: "uuid", type: "string"), new OA\Property(property: "job", type: "integer"), new OA\Property(property: "name", type: "string", nullable: true),
    new OA\Property(property: "pid", type: "integer"), new OA\Property(property: "host", type: "string", nullable: true), new OA\Property(property: "slot", type: "integer", nullable: true),
    new OA\Property(property: "status", type: "string", enum: ["running", "succeeded", "failed", "skipped", "lost"]), new OA\Property(property: "stale", type: "boolean"),
    new OA\Property(property: "started_at", type: "string", format: "date-time"), new OA\Property(property: "heartbeat", type: "string", format: "date-time", nullable: true),
    new OA\Property(property: "finished_at", type: "string", format: "date-time", nullable: true), new OA\Property(property: "exit_reason", type: "string", nullable: true),
], type: "object")]
#[AcceptableMethods(['GET', 'POST', 'DELETE', 'HEAD', 'OPTIONS'])]
#[Controller(route: 'api/v4/scheduler/runs/[uuid]', scope: Scope::SUPER_USER_ONLY)]
class SchedulerRun extends AbstractApi
{
    private const array STATUSES = ['running', 'succeeded', 'failed', 'skipped', 'lost'];
    private Job $job;
    private string $db;

    public function __construct(public readonly Route2 $route, Connection $connection)
    {
        parent::__construct($connection);
        $this->job = new Job(new Connection(database: 'gc2scheduler'));
        $this->db = (string)$this->route->jwt['data']['database'];
        $this->resource = 'scheduler-run';
    }

    private function present(array $r): array
    {
        return [
            'uuid' => $r['uuid'], 'job' => (int)$r['id'], 'name' => $r['name'], 'pid' => (int)$r['pid'], 'host' => $r['host'],
            'slot' => $r['slot'] !== null ? (int)$r['slot'] : null, 'status' => $r['status'],
            'stale' => $r['status'] === 'running' && $r['heartbeat'] !== null && (time() - strtotime($r['heartbeat'])) > 300,
            'started_at' => $r['started_at'], 'heartbeat' => $r['heartbeat'], 'finished_at' => $r['finished_at'], 'exit_reason' => $r['exit_reason'],
        ];
    }

    /** @return array<int, array<string,mixed>> runs of the caller's database after reaping */
    private function runs(): array
    {
        $lock = new SchedulerLock();
        $lock->reap();
        $rows = $lock->runsFor($this->db);
        $lock->release();
        return $rows;
    }

    #[OA\Get(path: '/api/v4/scheduler/runs/{uuid}', operationId: 'getSchedulerRun', description: "Get one run, or list runs (running first, then the newest finished). Filters: ?job=, ?status=.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string')), new OA\Parameter(name: 'job', in: 'query', required: false, schema: new OA\Schema(type: 'integer')), new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Ok', content: new OA\JsonContent(ref: "#/components/schemas/SchedulerRun")), new OA\Response(response: 404, description: 'Not found')])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function get_index(): Response
    {
        $uuid = $this->route->getParam('uuid');
        $rows = $this->runs();
        if (!empty($uuid)) {
            foreach ($rows as $r) {
                if ($r['uuid'] === $uuid) {
                    return $this->getResponse([$this->present($r)], single: true);
                }
            }
            throw new GC2Exception("Run $uuid not found", 404, null, "RUN_NOT_FOUND");
        }
        $job = isset($_GET['job']) && ctype_digit((string)$_GET['job']) ? (int)$_GET['job'] : null;
        $status = isset($_GET['status']) && in_array($_GET['status'], self::STATUSES, true) ? $_GET['status'] : null;
        $rows = array_values(array_filter($rows, fn($r) => ($job === null || (int)$r['id'] === $job) && ($status === null || $r['status'] === $status)));
        return $this->getResponse(array_map(fn($r) => $this->present($r), $rows));
    }

    #[OA\Post(path: '/api/v4/scheduler/runs', operationId: 'postSchedulerRun', description: "Start a job now. Asynchronous: poll the runs list for the new run.", tags: ['Scheduler'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: "job", type: "integer"), new OA\Property(property: "force", description: "Ignore delete_append and overwrite", type: "boolean")], type: "object")),
        responses: [new OA\Response(response: 202, description: 'Starting'), new OA\Response(response: 404, description: 'Job not found'), new OA\Response(response: 409, description: 'A run of the job is already running')])]
    #[AcceptableContentTypes(['application/json'])]
    #[AcceptableAccepts(['application/json', '*/*'])]
    #[Override]
    public function post_index(): Response
    {
        $body = json_decode(Input::getBody(), true);
        $jobId = (int)$body['job'];
        if ($this->job->getById($jobId, $this->db) === null) {
            throw new GC2Exception("Job $jobId not found", 404, null, "JOB_NOT_FOUND");
        }
        $lock = new SchedulerLock();
        $lock->reap();
        $running = $lock->runningRun($jobId);
        $lock->release();
        if ($running !== null) {
            throw new GC2Exception("Job $jobId is already running (run {$running['uuid']})", 409, null, "JOB_RUNNING");
        }
        $this->job->runJob($jobId, $this->db, 'Started via API v4 by ' . $this->route->jwt['data']['uid'], !empty($body['force']), null, true);
        return new AcceptedResponse(['job' => $jobId, 'status' => 'starting', '_links' => ['runs' => "/api/v4/scheduler/runs?job=$jobId"]]);
    }

    #[OA\Delete(path: '/api/v4/scheduler/runs/{uuid}', operationId: 'deleteSchedulerRun', description: "Stop a running run: SIGINT, then SIGKILL after 30 s.", tags: ['Scheduler'],
        parameters: [new OA\Parameter(name: 'uuid', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Signal sent'), new OA\Response(response: 404, description: 'No running run with that uuid'), new OA\Response(response: 409, description: 'The run is on another host')])]
    #[Override]
    public function delete_index(): Response
    {
        $uuid = (string)$this->route->getParam('uuid');
        $run = null;
        foreach ($this->runs() as $r) {
            if ($r['uuid'] === $uuid && $r['status'] === 'running') {
                $run = $r;
            }
        }
        if ($run === null) {
            throw new GC2Exception("No running run with uuid $uuid", 404, null, "RUN_NOT_FOUND");
        }
        $host = gethostname() ?: 'unknown';
        if ($run['host'] !== $host) {
            throw new GC2Exception("Run $uuid is on host {$run['host']}, not $host", 409, null, "RUN_ON_OTHER_HOST");
        }
        $this->job->kill((int)$run['pid']);
        return new GetResponse(data: ['uuid' => $uuid, 'signal' => 'SIGINT']);
    }

    public function put_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    public function patch_index(): Response
    {
        throw new GC2Exception("Method not allowed", 405, null, "METHOD_NOT_ALLOWED");
    }

    #[Override]
    public function validate(): void
    {
        $uuid = $this->route->getParam('uuid');
        $method = Input::getMethod();
        if ($method === 'post') {
            if (!empty($uuid)) {
                $this->postWithResource();
            }
            $this->validateRequest(new Assert\Collection([
                'job' => new Assert\Required([new Assert\Type('integer'), new Assert\Positive()]),
                'force' => new Assert\Optional(new Assert\Type('bool')),
            ]), Input::getBody(), $method);
        }
        if ($method === 'delete' && empty($uuid)) {
            throw new GC2Exception("A run uuid is required", 400, null, "INVALID_REQUEST");
        }
    }
}
```

`Job::kill` must be public (Task 3 made it so). Note `runJob(..., $async = true)` returns immediately; the 202 says `starting` because the run row is written by get.php.

- [ ] **Step 4: Run the Cest until green** — `OK (4 tests, ...)`; re-run `SchedulerJobV4ApiCest.php`.

- [ ] **Step 5: Commit**

```bash
git add app/api/v4/controllers/SchedulerRun.php app/tests/api/SchedulerRunV4ApiCest.php
git commit -m "feat(api): v4 scheduler runs (start, list, inspect, stop) on the run registry"
```

---

### Task 7: Verification

- [ ] **Step 1:** unit `SchedulerLockTest.php`, `JobSnapshotFlagTest.php`, `WfsPagingTest.php`; api `SchedulerApiCest.php`, `SchedulerJobV4ApiCest.php`, `SchedulerRunV4ApiCest.php`, `FunctionManagementCest.php` — all green, each its own command.
- [ ] **Step 2:** repeat Task 2 Step 6's double-start and kill -9 checks on the final code; record output.
- [ ] **Step 3:** `DELETE /api/v4/scheduler/runs/{uuid}` (and the v3 twin) by hand on a running job (start a job against a slow source, e.g. the WFS stub from the previous feature with a `sleep` added, or simply a job whose ogr2ogr step is long); expect `{"success":true,...}`, the run row `failed` with `exit_reason = timeout` or `terminated`, and the slot free (`SELECT count(*) FROM pg_locks WHERE classid = 42002`).
- [ ] **Step 4:** `grep -rn "scheduler_locks\|purge_locks\|pgrep timeout" app public docker --exclude-dir=vendor` → only historical docs.
