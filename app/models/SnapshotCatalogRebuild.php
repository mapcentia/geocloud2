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
 * Queue and status records for full rewrites of a database's static STAC
 * catalog, in settings.snapshot_catalog_rebuilds. Like settings.snapshots the
 * table is the queue: POST inserts a 'pending' row, snapshot_worker.php claims
 * it (FOR UPDATE SKIP LOCKED), rewrites every catalog document and records the
 * outcome.
 */
class SnapshotCatalogRebuild extends Model
{
    /**
     * Inserts a pending rebuild request and returns its uuid.
     */
    public function create(string $username): string
    {
        $res = $this->prepare("INSERT INTO settings.snapshot_catalog_rebuilds (username) VALUES (:username) RETURNING uuid");
        $this->execute($res, ['username' => $username]);
        return $res->fetchColumn();
    }

    /**
     * The uuid of a rebuild that is pending, or running within the stale
     * window, or null. One rebuild rewrites everything, so a second one
     * queued behind it would only repeat the work.
     */
    public function activeId(): ?string
    {
        $sql = "SELECT uuid FROM settings.snapshot_catalog_rebuilds
                WHERE status = 'pending'
                   OR (status = 'running' AND started > now() - interval '" . Snapshot::STALE_RUNNING_INTERVAL . "')
                ORDER BY created LIMIT 1";
        $res = $this->prepare($sql);
        $this->execute($res);
        $uuid = $res->fetchColumn();
        return $uuid === false || $uuid === null ? null : (string)$uuid;
    }

    /**
     * Atomically claims up to $limit rows (oldest first): pending ones, and
     * running ones older than the stale window (their worker died).
     *
     * @return array<int, array<string, mixed>> The claimed rows.
     */
    public function claimPending(int $limit = 1): array
    {
        $sql = "UPDATE settings.snapshot_catalog_rebuilds u
                SET status = 'running', started = now()
                FROM (
                    SELECT uuid FROM settings.snapshot_catalog_rebuilds
                    WHERE status = 'pending'
                       OR (status = 'running' AND started < now() - interval '" . Snapshot::STALE_RUNNING_INTERVAL . "')
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
     * Records the outcome of a claimed rebuild. Only a row that is still
     * 'running' is touched, so a worker whose row was reclaimed does not
     * overwrite the newer outcome.
     */
    public function finish(string $uuid, string $status, ?int $documents, ?int $failedDocuments, ?string $error): void
    {
        $sql = "UPDATE settings.snapshot_catalog_rebuilds
                SET status = :status, documents = :documents, failed_documents = :failed, error = :error, finished = now()
                WHERE uuid = :uuid AND status = 'running'";
        $res = $this->prepare($sql);
        $res->bindValue(':status', $status);
        $res->bindValue(':documents', $documents, $documents === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $res->bindValue(':failed', $failedDocuments, $failedDocuments === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $res->bindValue(':error', $error, $error === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $res->bindValue(':uuid', $uuid);
        $this->execute($res);
    }

    /**
     * Fetches one row. Compares on uuid::text so a malformed id is a 404, not
     * a database error.
     *
     * @return array<string, mixed>
     * @throws GC2Exception 404 CATALOG_REBUILD_NOT_FOUND when the uuid is unknown.
     */
    public function get(string $uuid): array
    {
        $res = $this->prepare("SELECT * FROM settings.snapshot_catalog_rebuilds WHERE uuid::text = :uuid");
        $this->execute($res, ['uuid' => $uuid]);
        $row = $this->fetchRow($res);
        if (!$row) {
            throw new GC2Exception("No catalog rebuild with that id", 404, null, "CATALOG_REBUILD_NOT_FOUND");
        }
        return $row;
    }

    /**
     * Lists rows newest first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(int $limit = 50): array
    {
        $res = $this->prepare("SELECT * FROM settings.snapshot_catalog_rebuilds ORDER BY created DESC LIMIT :limit");
        $res->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $this->execute($res);
        return $this->fetchAll($res, 'assoc');
    }
}
