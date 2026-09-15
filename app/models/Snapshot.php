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
