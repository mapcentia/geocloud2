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
        $this->execute($res, ['host' => self::currentHost()]);
        return $this->fetchRow($res) ?: null;
    }

    /**
     * Proof of life from the run, plus the current log tail.
     *
     * Returns the number of rows it updated, which is 0 once the row is no
     * longer 'running' — reaped as stale after a long PDO stall, or finalised by
     * a tick whose `posix_kill($pid, 0)` probe returned a false negative. That is
     * the only signal the run gets that someone else has taken ownership of its
     * row, and without it the run kept seeding with nothing able to observe or
     * stop it (requestCancel() answers 'noop' for a non-running row,
     * countRunningOnHost() no longer counts it, so the next tick claimed another
     * seed on top) until the 12-hour timeout. seed_run.php stops its child and
     * exits on a 0 rather than writing a status the other writer owns.
     *
     * @return int rows updated: 1 while the row is still ours, 0 when it is not
     */
    public function heartbeat(string $uuid, ?string $logTail = null): int
    {
        $res = $this->prepare("UPDATE settings.seed_jobs
                                  SET heartbeat = now(), log = COALESCE(:log, log)
                                WHERE uuid = :uuid AND status = 'running'");
        $this->execute($res, ['uuid' => $uuid, 'log' => $logTail]);
        return $res->rowCount();
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
     * How many rows this node is already running, per the database — the same
     * source of truth `claimOne()` writes and the API reads. Counting live
     * processes instead (`pgrep`) double-counts a run (its `timeout` wrapper and
     * the `php` child are two processes for one job) and fails open to 0, hence
     * unbounded claiming, when the process table can't be read at all.
     */
    public function countRunningOnHost(string $host): int
    {
        $res = $this->prepare("SELECT count(*) AS n FROM settings.seed_jobs WHERE status = 'running' AND host = :host");
        $this->execute($res, ['host' => $host]);
        return (int)($this->fetchRow($res)['n'] ?? 0);
    }

    /** Cheap existence check, not a count: lets a caller decide whether a database
     *  is worth a second visit without fetching or counting its pending rows. */
    public function hasPending(): bool
    {
        $res = $this->prepare("SELECT EXISTS (SELECT 1 FROM settings.seed_jobs WHERE status = 'pending') AS e");
        $this->execute($res);
        return (bool)($this->fetchRow($res)['e'] ?? false);
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

    /** The node identity `claimOne()` stamps a claimed row with; public so a
     *  caller (the worker tick) can count this node's own running rows with it. */
    public static function currentHost(): string
    {
        return gethostname() ?: ($_SERVER['SERVER_ADDR'] ?? 'unknown');
    }
}
