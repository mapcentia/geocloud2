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

    private ?PDO $pdo;

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
        $this->assertSessionSticky();
    }

    /**
     * The whole mechanism assumes this connection is one Postgres session:
     * behind a transaction-pooled PgBouncer every advisory lock would be
     * dropped at the end of its own statement, every lock would appear free
     * and the reaper would mark every live run lost — silently. Prove it
     * instead of trusting the `pgbouncer: false` flag (which selects no other
     * host today): take a throwaway lock and re-read it in a *separate*
     * statement.
     */
    private function assertSessionSticky(): void
    {
        $this->pdo->exec("SELECT pg_advisory_lock(" . self::JOB_LOCK_CLASS . ", 0)");
        $ok = $this->pdo->query("SELECT count(*) FROM pg_locks WHERE locktype = 'advisory'
            AND pid = pg_backend_pid() AND classid = " . self::JOB_LOCK_CLASS . " AND objid = 0::oid AND objsubid = 2")->fetchColumn();
        $this->pdo->exec("SELECT pg_advisory_unlock(" . self::JOB_LOCK_CLASS . ", 0)");
        if (!$ok) {
            throw new RuntimeException("gc2scheduler connection is not session-sticky (transaction-pooled PgBouncer?); scheduler locking cannot work");
        }
    }

    /** @throws RuntimeException when the session has been released */
    private function pdo(): PDO
    {
        if ($this->pdo === null) {
            throw new RuntimeException("lock session released");
        }
        return $this->pdo;
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
     * called before every sleep (for logging/reporting). When $maxWaitSeconds
     * is not null and the total time spent waiting reaches it, throws
     * RuntimeException instead of waiting further; null (the default) waits
     * forever, which is what get.php wants.
     */
    public function acquireSlot(int $maxJobs, ?callable $onWait = null, int $sleepSeconds = 10, ?int $maxWaitSeconds = null): int
    {
        $maxJobs = max(1, $maxJobs);
        $start = microtime(true);
        while (true) {
            for ($slot = 1; $slot <= $maxJobs; $slot++) {
                if ($this->trySlot($slot)) {
                    return $slot;
                }
            }
            if ($maxWaitSeconds !== null && (microtime(true) - $start) >= $maxWaitSeconds) {
                throw new RuntimeException("No free run slot after {$maxWaitSeconds}s");
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
                        AND l.database = (SELECT oid FROM pg_database WHERE datname = current_database())
                  )";
        $st = $this->pdo()->prepare($sql);
        $st->execute(['class' => self::JOB_LOCK_CLASS]);
        return $st->rowCount();
    }

    /**
     * Registers the run. $slot may be null: get.php inserts the row right
     * after the job lock, before it waits for a slot, so a lock-holding run
     * is never invisible to the API; assignSlot() fills the slot in later.
     */
    public function startRun(int $jobId, string $db, ?string $name, int $pid, ?int $slot, string $host): string
    {
        $st = $this->pdo()->prepare("INSERT INTO started_jobs (id, db, name, pid, slot, host, status) VALUES (:id, :db, :name, :pid, :slot, :host, 'running') RETURNING uuid");
        $st->execute(['id' => $jobId, 'db' => $db, 'name' => $name, 'pid' => $pid, 'slot' => $slot, 'host' => $host]);
        return $st->fetchColumn();
    }

    /** Fills in the run slot once acquireSlot() has handed one out. */
    public function assignSlot(string $uuid, int $slot): void
    {
        $st = $this->pdo()->prepare("UPDATE started_jobs SET slot = :slot WHERE uuid = :uuid");
        $st->execute(['slot' => $slot, 'uuid' => $uuid]);
    }

    public function recordSkipped(int $jobId, string $db, ?string $name, int $pid, string $host, string $reason): string
    {
        $st = $this->pdo()->prepare("INSERT INTO started_jobs (id, db, name, pid, host, status, finished_at, exit_reason) VALUES (:id, :db, :name, :pid, :host, 'skipped', now(), :reason) RETURNING uuid");
        $st->execute(['id' => $jobId, 'db' => $db, 'name' => $name, 'pid' => $pid, 'host' => $host, 'reason' => $reason]);
        return $st->fetchColumn();
    }

    public function finishRun(string $uuid, string $status, ?string $reason = null): void
    {
        if (!in_array($status, ['succeeded', 'failed', 'lost'], true)) {
            throw new RuntimeException("Not a final status: $status");
        }
        $st = $this->pdo()->prepare("UPDATE started_jobs SET status = :status, finished_at = now(), exit_reason = :reason WHERE uuid = :uuid AND status = 'running'");
        $st->execute(['status' => $status, 'reason' => $reason, 'uuid' => $uuid]);
    }

    /**
     * Best effort: the advisory lock, not the heartbeat, is the liveness
     * truth, so a transient failure here (e.g. the session is gone) must
     * never interrupt the import it is reporting on.
     */
    public function heartbeat(string $uuid): void
    {
        try {
            $st = $this->pdo()->prepare("UPDATE started_jobs SET heartbeat = now() WHERE uuid = :uuid AND status = 'running'");
            $st->execute(['uuid' => $uuid]);
        } catch (\Throwable $e) {
            error_log("scheduler heartbeat failed: " . $e->getMessage());
        }
    }

    public function runningRun(int $jobId): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM started_jobs WHERE id = :id AND status = 'running' ORDER BY started_at DESC LIMIT 1");
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
        $st = $this->pdo()->prepare("(SELECT * FROM started_jobs WHERE db = :db AND status = 'running' ORDER BY started_at DESC)
                                   UNION ALL
                                   (SELECT * FROM started_jobs WHERE db = :db2 AND status <> 'running' ORDER BY started_at DESC LIMIT :lim)");
        $st->bindValue('db', $db);
        $st->bindValue('db2', $db);
        $st->bindValue('lim', max(1, $finishedLimit), PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** The job's newest real run (skipped rows excluded), or null. */
    public function latestRun(int $jobId): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM started_jobs WHERE id = :id AND status IN ('running', 'succeeded', 'failed', 'lost') ORDER BY started_at DESC LIMIT 1");
        $st->execute(['id' => $jobId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * $since (anything strtotime/Postgres understands, e.g. date('c')) bounds
     * the lookup below: pids are reused, so without it a caller waiting on a
     * freshly spawned run can match an old finished row on the same pid and
     * return before the new run has even registered.
     */
    public function latestRunForPid(int $pid, string $host, ?string $since = null): ?array
    {
        $sql = "SELECT * FROM started_jobs WHERE pid = :pid AND host = :host";
        $params = ['pid' => $pid, 'host' => $host];
        if ($since !== null) {
            $sql .= " AND started_at >= :since";
            $params['since'] = $since;
        }
        $st = $this->pdo()->prepare($sql . " ORDER BY started_at DESC LIMIT 1");
        $st->execute($params);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** One run by uuid, scoped to one database, or null. */
    public function run(string $uuid, string $db): ?array
    {
        $st = $this->pdo()->prepare("SELECT * FROM started_jobs WHERE uuid::text = :uuid AND db = :db");
        $st->execute(['uuid' => $uuid, 'db' => $db]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /** Ends the session, which releases every lock it holds. */
    public function release(): void
    {
        $this->pdo = null;
    }

    private function tryLock(int $class, int $key): bool
    {
        try {
            $st = $this->pdo()->prepare("SELECT pg_try_advisory_lock(:class, :key)");
            $st->bindValue('class', $class, PDO::PARAM_INT);
            $st->bindValue('key', $key, PDO::PARAM_INT);
            $st->execute();
            return (bool)$st->fetchColumn();
        } catch (PDOException $e) {
            throw new RuntimeException("Advisory lock failed: " . $e->getMessage(), 0, $e);
        }
    }
}
