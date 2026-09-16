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

    /**
     * Change fingerprint of a column list, not a security hash: it only needs
     * to differ when the column shape changes between snapshots. Computed from
     * canonical "name type" lines so it can be recomputed from the schema
     * array regardless of how a JSON store (e.g. jsonb) orders object keys.
     *
     * @param array<int, array{column_name:string, data_type:string}> $columns
     */
    public static function schemaVersion(array $columns): string
    {
        return md5(implode("\n", array_map(fn($c) => $c['column_name'] . ' ' . $c['data_type'], $columns)));
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

    /**
     * Exports the relation to a Parquet file with ogr2ogr. Reprojects only
     * when $srs is given.
     */
    private function export(string $schema, string $relation, ?int $srs, string $tmpFile): void
    {
        $c = $this->connection;
        $pg = "PG:host={$c->host} port={$c->port} user={$c->user} password={$c->password} dbname={$c->database}";
        $q = fn(string $s) => '"' . str_replace('"', '""', $s) . '"';
        $cmd = 'ogr2ogr -mapFieldType Time=String,Binary=String -f Parquet ' . escapeshellarg($tmpFile)
            . ($srs !== null ? ' -t_srs ' . escapeshellarg("EPSG:$srs") : '')
            . ' -preserve_fid '
            . escapeshellarg($pg)
            . ' -sql ' . escapeshellarg("SELECT * FROM {$q($schema)}.{$q($relation)}")
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
     * Row count of the relation. Unlike Model::countRows(), a query failure
     * throws instead of being swallowed into a ['success' => false] array,
     * so the caller can't mistake a failed count for zero rows.
     */
    private function rowCount(string $schema, string $relation): int
    {
        $sql = "SELECT count(*) AS count FROM " . $this->model->doubleQuoteQualifiedName("$schema.$relation");
        $res = $this->model->prepare($sql);
        $this->model->execute($res);
        $row = $this->model->fetchRow($res);
        if ($row === null) {
            throw new RuntimeException("Could not count rows in $schema.$relation");
        }
        return (int)$row['count'];
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
     * Reads pg_attribute directly (rather than information_schema.columns)
     * so this also picks up materialized views, which information_schema
     * does not describe.
     *
     * @return array<int, array{column_name:string, data_type:string}> Covers
     *     tables, views, materialized views and foreign tables.
     */
    private function columns(string $schema, string $relation): array
    {
        $sql = "SELECT a.attname AS column_name, format_type(a.atttypid, a.atttypmod) AS data_type
                FROM pg_attribute a
                JOIN pg_class c ON c.oid = a.attrelid
                JOIN pg_namespace n ON n.oid = c.relnamespace
                WHERE n.nspname = :schema AND c.relname = :relation AND a.attnum > 0 AND NOT a.attisdropped
                ORDER BY a.attnum";
        $res = $this->model->prepare($sql);
        $this->model->execute($res, ['schema' => $schema, 'relation' => $relation]);
        return $this->model->fetchAll($res, 'assoc');
    }
}
