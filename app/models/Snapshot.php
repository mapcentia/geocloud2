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
use app\inc\snapshot\SnapshotFormat;
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
     *
     * @param array<int, string> $formats Requested output format ids
     *     (SnapshotFormat::ids()), in the order the worker produces them. The
     *     caller validates them; they are stored as given and become the
     *     per-format results on publish().
     */
    public function create(string $schema, string $relation, ?int $srs, string $username, array $formats): string
    {
        $sql = "INSERT INTO settings.snapshots (schema_name, relation_name, srs, username, formats)
                VALUES (:schema, :relation, :srs, :username, :formats)
                RETURNING uuid";
        $res = $this->prepare($sql);
        $res->bindValue(':schema', $schema);
        $res->bindValue(':relation', $relation);
        $res->bindValue(':srs', $srs, $srs === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $res->bindValue(':username', $username);
        $res->bindValue(':formats', json_encode(array_values($formats)));
        $this->execute($res);
        return $res->fetchColumn();
    }

    /**
     * The relation's first spatial column — geometry or geography — or null
     * when it has neither. Both views cover tables, views and materialized
     * views alike, and geometry columns are preferred over geography ones so
     * the worker's footprint is measured on the same column as before
     * geography was considered at all.
     *
     * A geography relation counts as spatial: ogr2ogr exports it happily, so a
     * format that requires geometry must not be refused or skipped for it.
     *
     * Shared by the worker (which skips a geometry-only format for a relation
     * without one) and the job API (which refuses a request whose *every*
     * format needs geometry), so both answer from the same query.
     *
     * @return array{column:string, geography:bool}|null
     */
    public function spatialColumn(string $schema, string $relation): ?array
    {
        $sql = "SELECT column_name, geography FROM (
                    SELECT f_geometry_column AS column_name, false AS geography FROM geometry_columns
                    WHERE f_table_schema = :schema AND f_table_name = :relation
                    UNION ALL
                    SELECT f_geography_column, true FROM geography_columns
                    WHERE f_table_schema = :schema AND f_table_name = :relation
                ) c
                ORDER BY geography, column_name LIMIT 1";
        $res = $this->prepare($sql);
        $this->execute($res, ['schema' => $schema, 'relation' => $relation]);
        $row = $this->fetchRow($res);
        if ($row === null || ($row['column_name'] ?? null) === null) {
            return null;
        }
        return ['column' => (string)$row['column_name'], 'geography' => filter_var($row['geography'], FILTER_VALIDATE_BOOLEAN)];
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
     * Finalises a claimed row with its outcome. $relationSchema is the column
     * list (name + type) captured at snapshot time; $schemaVersion is its md5.
     *
     * Only a row that is still 'running' is touched: a worker whose row was
     * reclaimed after the stale window (and possibly already published by the
     * winner) must not overwrite the new outcome with its own. A no-op is not
     * an error here; the caller has already logged the failure.
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
                WHERE uuid = :uuid AND status = 'running'";
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
            'data' => $this->withFormats($row),
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
        return array_map($this->withFormats(...), $this->fetchAll($res, 'assoc'));
    }

    /**
     * Marks a claimed run succeeded and visible, in one transaction: any
     * earlier succeeded row for the same relation and date is flipped to
     * 'superseded' first (the partial unique index forbids two succeeded rows
     * per date), then this row gets its catalog data and published = now().
     *
     * @param array<int, array{column_name:string, data_type:string}> $relationSchema
     * @param array<int, array{name:string, size_bytes:int}> $files
     * @param array{0:float, 1:float, 2:float, 3:float}|null $bbox WGS84 footprint, null for a
     *     non-spatial or empty relation. Feeds the snapshot's STAC Item geometry.
     * @param array<int, array<string, mixed>> $formats Per-format outcome of the
     *     run, replacing the requested list stored at create(): one entry per
     *     requested format, `produced` (with file, size_bytes, media_type) or
     *     `skipped` (with a reason). A caller that has no results — the legacy
     *     single-format callers and their tests — gets them derived from $files,
     *     so a published row always describes its formats rather than what was
     *     asked for.
     * @return string|null uuid of the superseded row, so the caller can delete its files
     * @throws GC2Exception 404 NO_SNAPSHOT_ERROR when $uuid is unknown or not currently 'running'
     */
    public function publish(string $uuid, string $snapshotDate, string $location, int $rowCount, string $schemaVersion, array $relationSchema, array $files, ?array $bbox = null, array $formats = []): ?string
    {
        $formats = $formats === [] ? self::formatsFromFiles($files) : array_values($formats);
        return $this->withTransaction(function () use ($uuid, $snapshotDate, $location, $rowCount, $schemaVersion, $relationSchema, $files, $bbox, $formats) {
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
                                       files = :files, size_bytes = :size_bytes, bbox = :bbox, formats = :formats, error = NULL,
                                       published = now(), finished = now()
                                   WHERE uuid = :uuid AND status = 'running'");
            $res->bindValue(':uuid', $uuid);
            $res->bindValue(':date', $snapshotDate);
            $res->bindValue(':location', $location);
            $res->bindValue(':row_count', $rowCount, PDO::PARAM_INT);
            $res->bindValue(':schema_version', $schemaVersion);
            $res->bindValue(':relation_schema', json_encode($relationSchema));
            $res->bindValue(':files', json_encode($files));
            $res->bindValue(':size_bytes', $sizeBytes, PDO::PARAM_INT);
            $res->bindValue(':bbox', $bbox === null ? null : json_encode(array_map(fn($v) => (float)$v, $bbox)), $bbox === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
            $res->bindValue(':formats', json_encode($formats));
            $this->execute($res);
            if ($res->rowCount() === 0) {
                throw new GC2Exception("No snapshot with that id", 404, null, "NO_SNAPSHOT_ERROR");
            }
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
        return array_map($this->withFormats(...), $this->fetchAll($res, 'assoc'));
    }

    /**
     * Every visible snapshot of the database, grouped by relation and newest
     * date first — the input to the static STAC catalog, which is rebuilt in
     * full after each publish. `files` and `bbox` come back decoded.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listAllPublished(): array
    {
        $sql = "SELECT * FROM settings.snapshots
                WHERE status = 'succeeded' AND published IS NOT NULL AND snapshot_date IS NOT NULL
                ORDER BY schema_name, relation_name, snapshot_date DESC";
        $res = $this->prepare($sql);
        $this->execute($res);
        return array_map(function (array $row) {
            $row['files'] = is_string($row['files'] ?? null) ? (json_decode($row['files'], true) ?: []) : ($row['files'] ?? []);
            $bbox = is_string($row['bbox'] ?? null) ? json_decode($row['bbox'], true) : ($row['bbox'] ?? null);
            $row['bbox'] = is_array($bbox) && count($bbox) === 4 ? array_map(fn($v) => (float)$v, array_values($bbox)) : null;
            return $this->withFormats($row);
        }, $this->fetchAll($res, 'assoc'));
    }

    /**
     * Layer metadata (title, abstract, tags) of the given relations, for the
     * STAC collections. Keyed "schema.relation"; a relation without a
     * registered geometry column — a non-spatial table, or one never seen by
     * the GUI — is simply absent, and the caller falls back.
     *
     * Reads settings.geometry_columns_view rather than the
     * settings.geometry_columns_join table, because only the view carries
     * f_table_schema/f_table_name (the table is keyed by _key_). A relation
     * with several geometry columns yields several rows; the first one that
     * has any metadata wins.
     *
     * @param array<int, string> $relations "schema.relation" keys
     * @return array<string, array{title:?string, description:?string, keywords:array}>
     */
    public function relationMeta(array $relations): array
    {
        $relations = array_values(array_unique($relations));
        if ($relations === []) {
            return [];
        }
        $names = [];
        $params = [];
        foreach ($relations as $i => $relation) {
            $names[] = ":r$i";
            $params["r$i"] = $relation;
        }
        $sql = "SELECT f_table_schema, f_table_name, f_table_title, f_table_abstract, tags
                FROM settings.geometry_columns_view
                WHERE f_table_schema || '.' || f_table_name IN (" . implode(', ', $names) . ")";
        $res = $this->prepare($sql);
        $this->execute($res, $params);

        $meta = [];
        foreach ($this->fetchAll($res, 'assoc') as $row) {
            $key = $row['f_table_schema'] . '.' . $row['f_table_name'];
            $tags = is_string($row['tags'] ?? null) ? json_decode($row['tags'], true) : ($row['tags'] ?? null);
            // A blank title or abstract is no metadata: reported as null, so a
            // relation with several geometry columns ends up described by
            // whichever of its rows actually says something.
            $title = trim((string)($row['f_table_title'] ?? ''));
            $abstract = trim((string)($row['f_table_abstract'] ?? ''));
            $entry = [
                'title' => $title === '' ? null : $title,
                'description' => $abstract === '' ? null : $abstract,
                'keywords' => is_array($tags) ? array_values($tags) : [],
            ];
            $existing = $meta[$key] ?? null;
            // Several geometry columns: keep the first row that says anything.
            if ($existing === null || ($existing['title'] === null && $existing['description'] === null && $existing['keywords'] === [])) {
                $meta[$key] = $entry;
            }
        }
        return $meta;
    }

    /** The newest published snapshot of a relation (by snapshot date, then publish time). */
    public function getLatestPublished(string $schema, string $relation): array
    {
        $sql = "SELECT * FROM settings.snapshots
                WHERE schema_name = :schema AND relation_name = :relation
                  AND status = 'succeeded' AND published IS NOT NULL
                ORDER BY snapshot_date DESC, published DESC LIMIT 1";
        $res = $this->prepare($sql);
        $this->execute($res, ['schema' => $schema, 'relation' => $relation]);
        $row = $this->fetchRow($res);
        if (!$row) {
            throw new GC2Exception("No published snapshot of $schema.$relation", 404, null, "NO_SNAPSHOT_ERROR");
        }
        return ['success' => true, 'message' => "Snapshot fetched", 'data' => $this->withFormats($row)];
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
        return ['success' => true, 'message' => "Snapshot fetched", 'data' => $this->withFormats($row)];
    }

    /**
     * The row with its `formats` column decoded — the shape every reader of a
     * row expects, whether the column holds the requested id list, the
     * per-format results, or nothing at all (a row written before the column
     * existed, which reads as an empty list and is described by its files).
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withFormats(array $row): array
    {
        $row['formats'] = self::decodeFormats($row['formats'] ?? null);
        return $row;
    }

    /**
     * The snapshot's formats as the API shows them (spec: one entry per
     * format with a status):
     *
     * - a requested list (strings, written by create()) renders as
     *   `{format, status: requested}` — the row has not run yet;
     * - stored results (objects, written by publish()) are normalised to
     *   `{format, status, file, size_bytes, media_type}` for a produced format
     *   and `{format, status, reason}` for a skipped one — jsonb does not keep
     *   key order, so the presenter fixes it;
     * - nothing stored (a row from before the column) is derived from `files`
     *   by extension, every file a produced format.
     *
     * @param array<string, mixed> $row A row as get()/list() return it.
     * @return array<int, array<string, mixed>>
     */
    public static function presentFormats(array $row): array
    {
        $stored = self::decodeFormats($row['formats'] ?? null);
        if ($stored === []) {
            return self::formatsFromFiles(self::decodeFormats($row['files'] ?? null));
        }
        $presented = [];
        foreach ($stored as $entry) {
            if (is_string($entry)) {
                $presented[] = ['format' => $entry, 'status' => 'requested'];
                continue;
            }
            if (!is_array($entry) || !isset($entry['format'])) {
                continue;
            }
            $id = (string)$entry['format'];
            $status = (string)($entry['status'] ?? 'produced');
            $out = ['format' => $id, 'status' => $status];
            if ($status === 'produced') {
                $out['file'] = (string)($entry['file'] ?? '');
                $out['size_bytes'] = (int)($entry['size_bytes'] ?? 0);
                $out['media_type'] = (string)($entry['media_type'] ?? (SnapshotFormat::has($id) ? SnapshotFormat::get($id)->mediaType : 'application/octet-stream'));
            } elseif (isset($entry['reason'])) {
                $out['reason'] = (string)$entry['reason'];
            }
            $presented[] = $out;
        }
        return $presented;
    }

    /**
     * Per-format results read off a file list: every file whose extension
     * belongs to a format counts as produced, everything else (metadata.json)
     * is not a format. How a snapshot published before the `formats` column
     * describes itself.
     *
     * @param array<int, mixed> $files
     * @return array<int, array<string, mixed>>
     */
    private static function formatsFromFiles(array $files): array
    {
        $results = [];
        foreach ($files as $file) {
            $name = is_array($file) ? (string)($file['name'] ?? '') : '';
            $format = $name === '' ? null : SnapshotFormat::fromExtension($name);
            if ($format === null) {
                continue;
            }
            $results[] = [
                'format' => $format->id,
                'status' => 'produced',
                'file' => $name,
                'size_bytes' => (int)($file['size_bytes'] ?? 0),
                'media_type' => $format->mediaType,
            ];
        }
        return $results;
    }

    /**
     * A JSONB column as a list. It reaches us as a raw string from PDO (and as
     * an array once a caller has decoded it); null, an empty string and
     * anything that is not a JSON array all read as no list at all.
     *
     * @return array<int, mixed>
     */
    private static function decodeFormats(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? array_values($decoded) : [];
        }
        return [];
    }
}
