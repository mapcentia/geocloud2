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
