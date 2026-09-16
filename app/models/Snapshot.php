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
 * `superseded`: replaced by a newer run for the same date; `published`
 * marks visibility.
 */
class Snapshot extends Model
{
    /**
     * A `running` row whose `started` is older than this is considered dead
     * (the worker process that owned it crashed or was killed) and becomes
     * reclaimable: it no longer counts as active, and claimPending() will
     * pick it up again with a fresh `started`. Interpolated into SQL below;
     * it is a fixed string, not user input.
     */
    public const string STALE_RUNNING_INTERVAL = '2 hours';

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
     * True when a pending snapshot exists for the relation, or a running one
     * that is still within STALE_RUNNING_INTERVAL of its start. A running row
     * older than that is presumed to have died with its worker and no longer
     * counts as active.
     */
    public function hasActive(string $schema, string $relation): bool
    {
        $sql = "SELECT EXISTS (
                    SELECT 1 FROM settings.snapshots
                    WHERE schema_name = :schema AND relation_name = :relation
                      AND (
                          status = 'pending'
                          OR (status = 'running' AND started > now() - interval '" . self::STALE_RUNNING_INTERVAL . "')
                      )
                ) AS active";
        $res = $this->prepare($sql);
        $this->execute($res, ['schema' => $schema, 'relation' => $relation]);
        return (bool)$res->fetchColumn();
    }

    /**
     * Atomically claims up to $limit rows (oldest first), flipping them to
     * 'running' with a fresh started = now(). Eligible rows are 'pending'
     * ones and 'running' ones whose started is older than
     * STALE_RUNNING_INTERVAL (a worker died mid-run and left the row stuck);
     * reclaiming resets started so the new attempt gets its own stale window.
     * SKIP LOCKED keeps concurrent workers from claiming the same row.
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
                       OR (status = 'running' AND started < now() - interval '" . self::STALE_RUNNING_INTERVAL . "')
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
     * Finalises a row with its outcome. $relationSchema is the column list
     * (name + type) captured at snapshot time; $schemaVersion is its md5.
     */
    public function finish(
        string  $uuid,
        string  $status,
        ?string $s3Path,
        ?int    $rowCount,
        ?string $error,
        ?string $schemaVersion = null,
        ?array  $relationSchema = null,
    ): void
    {
        $sql = "UPDATE settings.snapshots
                SET status = :status, s3_path = :s3_path, row_count = :row_count, error = :error,
                    schema_version = :schema_version, relation_schema = :relation_schema, finished = now()
                WHERE uuid = :uuid";
        $res = $this->prepare($sql);
        $res->bindValue(':uuid', $uuid);
        $res->bindValue(':status', $status);
        $res->bindValue(':s3_path', $s3Path, $s3Path === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $res->bindValue(':row_count', $rowCount, $rowCount === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $res->bindValue(':error', $error, $error === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $res->bindValue(':schema_version', $schemaVersion, $schemaVersion === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $res->bindValue(':relation_schema', $relationSchema === null ? null : json_encode($relationSchema), $relationSchema === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
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
}
