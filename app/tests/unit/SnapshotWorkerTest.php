<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\Model;
use app\inc\snapshot\LocalSnapshotStorage;
use app\inc\snapshot\SnapshotRef;
use app\inc\SnapshotWorker;
use app\models\Snapshot;
use Codeception\Test\Unit;

/**
 * Runs the snapshot worker end to end against a provisioned database with a
 * local Flysystem adapter standing in for S3. Needs ogr2ogr with the Parquet
 * driver and the in-container web server (both true in the dev container).
 */
class SnapshotWorkerTest extends Unit
{
    protected UnitTester $tester;

    private const string API = 'http://localhost';
    private const string PASSWORD = 'A1abcabcabc';

    private static ?string $database = null;
    private string $storeDir;
    private string $tmpDir;

    protected function _before(): void
    {
        if (self::$database === null) {
            $ts = (string)(new DateTime())->getTimestamp() . bin2hex(random_bytes(3));
            $created = $this->post('/api/v2/user', [
                'name' => 'Snapshot worker test ' . $ts,
                'email' => 'snapworker' . $ts . '@example.com',
                'password' => self::PASSWORD,
            ]);
            if ($created === null || empty($created['data']['screenname'])) {
                $this->markTestSkipped('GC2 API not reachable; skipping snapshot worker test.');
            }
            self::$database = $created['data']['screenname'];

            $m = new Model(new Connection(database: self::$database));
            $m->execQuery("CREATE SCHEMA snap", "PDO", "transaction");
            $m->execQuery("CREATE TABLE snap.points (gid serial PRIMARY KEY, name text, the_geom geometry(Point, 25832))", "PDO", "transaction");
            $m->execQuery("INSERT INTO snap.points (name, the_geom) VALUES
                ('a', ST_SetSRID(ST_MakePoint(500000, 6200000), 25832)),
                ('b', ST_SetSRID(ST_MakePoint(500100, 6200100), 25832)),
                ('c', ST_SetSRID(ST_MakePoint(500200, 6200200), 25832))", "PDO", "transaction");
            $m->execQuery("CREATE VIEW snap.points_view AS SELECT * FROM snap.points WHERE name <> 'c'", "PDO", "transaction");
            $m->execQuery("CREATE MATERIALIZED VIEW snap.points_mv AS SELECT * FROM snap.points", "PDO", "transaction");
            $m->execQuery("CREATE TABLE snap.plain (id serial PRIMARY KEY, label text)", "PDO", "transaction");
            $m->execQuery("INSERT INTO snap.plain (label) VALUES ('a'), ('b')", "PDO", "transaction");
        }
        $base = sys_get_temp_dir() . '/snapshot_worker_test_' . bin2hex(random_bytes(4));
        $this->storeDir = $base . '/store';
        $this->tmpDir = $base . '/tmp';
        mkdir($this->storeDir, 0777, true);
    }

    protected function _after(): void
    {
        $this->rmrf(dirname($this->storeDir));
    }

    private function worker(string $prefix = 'unit'): SnapshotWorker
    {
        return new SnapshotWorker(
            new Connection(database: self::$database),
            new LocalSnapshotStorage($this->storeDir, $prefix),
            $this->tmpDir,
        );
    }

    private function snapshot(): Snapshot
    {
        return new Snapshot(new Connection(database: self::$database));
    }

    public function testTableSnapshotSucceedsAndWritesParquetAndMetadata(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points', null, self::$database, ['parquet']);
        $day = gmdate('Y-m-d'); // captured before the run so a midnight UTC rollover can't flake this
        $summary = $this->worker()->processPending(5);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=points/_gc2_snapshot_date=' . $day . '/';
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data-' . $uuid . '.parquet');
        $this->assertGreaterThan(0, filesize($this->storeDir . '/' . $partition . 'data-' . $uuid . '.parquet'));
        $this->assertFileExists($this->storeDir . '/' . $partition . 'metadata-' . $uuid . '.json');

        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata-' . $uuid . '.json'), true);
        $this->assertSame($uuid, $meta['snapshot_id']);
        $this->assertSame(self::$database, $meta['database']);
        $this->assertSame('snap.points', $meta['source']);
        $this->assertSame(3, $meta['row_count']);
        $this->assertSame('EPSG:25832', $meta['crs'], 'native SRID used when no srs is requested');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $meta['schema_version']);
        $this->assertSame(
            [
                ['column_name' => 'gid', 'data_type' => 'integer'],
                ['column_name' => 'name', 'data_type' => 'text'],
                ['column_name' => 'the_geom', 'data_type' => 'geometry(Point,25832)'],
            ],
            $meta['schema'],
            'metadata carries the column list in ordinal order'
        );
        $this->assertSame(SnapshotWorker::schemaVersion($meta['schema']), $meta['schema_version'], 'fingerprint is recomputable from the schema array');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $meta['created_at']);

        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame(3, (int)$row['row_count']);
        $this->assertSame($meta['schema_version'], $row['schema_version'], 'row carries the same fingerprint as metadata.json');
        // assertEquals: jsonb reorders object keys, the content must match.
        $this->assertEquals($meta['schema'], json_decode($row['relation_schema'], true), 'row carries the same column list as metadata.json');
        $this->assertNotNull($row['finished']);

        $this->assertSame($day, $meta['snapshot_date']);
        $this->assertEquals([['name' => 'data-' . $uuid . '.parquet', 'size_bytes' => filesize($this->storeDir . '/' . $partition . 'data-' . $uuid . '.parquet')]], $meta['files']);
        $this->assertSame($day, $row['snapshot_date']);
        $this->assertNotNull($row['published']);
        $this->assertEquals($meta['files'], json_decode($row['files'], true));
        $this->assertSame((int)$meta['files'][0]['size_bytes'], (int)$row['size_bytes']);
        $this->assertSame('file://' . $this->storeDir . '/' . $partition, $row['s3_path']);

        $this->assertFileDoesNotExist($this->tmpDir . '/' . $uuid . '.parquet', 'tmp file is removed');

        // WGS84 footprint of the three points around 500000 6200000 in EPSG:25832.
        $this->assertCount(4, $meta['bbox']);
        $this->assertGreaterThan(8.0, $meta['bbox'][0]);
        $this->assertLessThan(10.0, $meta['bbox'][2]);
        $this->assertGreaterThan(55.0, $meta['bbox'][1]);
        $this->assertLessThan(57.0, $meta['bbox'][3]);
        $this->assertEquals($meta['bbox'], json_decode($row['bbox'], true), 'the row carries the same footprint as metadata.json');
    }

    public function testViewSnapshotWithRequestedSrsReportsThatCrs(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points_view', 4326, self::$database, ['parquet']);
        $day = gmdate('Y-m-d'); // captured before the run so a midnight UTC rollover can't flake this
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=points_view/_gc2_snapshot_date=' . $day . '/';
        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata-' . $uuid . '.json'), true);
        $this->assertSame(2, $meta['row_count']);
        $this->assertSame('EPSG:4326', $meta['crs']);
    }

    public function testMaterializedViewSnapshotHasRealSchemaVersion(): void
    {
        $mvUuid = $this->snapshot()->create('snap', 'points_mv', null, self::$database, ['parquet']);
        $tableUuid = $this->snapshot()->create('snap', 'points', null, self::$database, ['parquet']);
        $day = gmdate('Y-m-d'); // captured before the run so a midnight UTC rollover can't flake this
        $summary = $this->worker()->processPending(5);
        $this->assertSame(2, $summary['succeeded']);

        $mvPartition = 'unit/' . self::$database . '/schema=snap/relation=points_mv/_gc2_snapshot_date=' . $day . '/';
        $mvMeta = json_decode(file_get_contents($this->storeDir . '/' . $mvPartition . 'metadata-' . $mvUuid . '.json'), true);
        $this->assertSame($mvUuid, $mvMeta['snapshot_id']);
        $this->assertSame(3, $mvMeta['row_count']);
        $this->assertSame('EPSG:25832', $mvMeta['crs']);
        $this->assertNotSame(SnapshotWorker::schemaVersion([]), $mvMeta['schema_version'], 'a materialized view must yield real column metadata, not an empty set');

        $tablePartition = 'unit/' . self::$database . '/schema=snap/relation=points/_gc2_snapshot_date=' . $day . '/';
        $tableMeta = json_decode(file_get_contents($this->storeDir . '/' . $tablePartition . 'metadata-' . $tableUuid . '.json'), true);
        $this->assertSame($tableUuid, $tableMeta['snapshot_id']);
        $this->assertSame($tableMeta['schema_version'], $mvMeta['schema_version'], 'same columns as the underlying table give the same schema_version');
    }

    public function testRelationWithoutGeometryHasNullCrs(): void
    {
        $uuid = $this->snapshot()->create('snap', 'plain', null, self::$database, ['parquet']);
        $day = gmdate('Y-m-d'); // captured before the run so a midnight UTC rollover can't flake this
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=plain/_gc2_snapshot_date=' . $day . '/';
        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata-' . $uuid . '.json'), true);
        $this->assertSame(2, $meta['row_count']);
        $this->assertNull($meta['crs']);
        $this->assertNull($meta['bbox'], 'a relation without a geometry column has no footprint');
    }

    public function testMissingRelationFailsRowWithError(): void
    {
        $uuid = $this->snapshot()->create('snap', 'does_not_exist', null, self::$database, ['parquet']);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['failed']);

        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertStringContainsString('does_not_exist', $row['error']);
        $this->assertNull($row['s3_path']);
        $this->assertNotNull($row['finished']);
        $this->assertFileDoesNotExist($this->tmpDir . '/' . $uuid . '.parquet');
    }

    public function testNothingPendingIsANoop(): void
    {
        $this->worker()->processPending(5); // drain
        $this->assertSame(['processed' => 0, 'succeeded' => 0, 'failed' => 0], $this->worker()->processPending(5));
    }

    public function testRerunSameDaySupersedesAndDeletesOldFile(): void
    {
        $first = $this->snapshot()->create('snap', 'points', null, self::$database, ['parquet']);
        $this->worker()->processPending(5);
        // The worker's own snapshot_date, not gmdate() after the fact: the two
        // differ if the run straddles midnight UTC and the paths below would
        // then point at a directory that was never written.
        $day = $this->snapshot()->get($first)['data']['snapshot_date'];
        $partition = 'unit/' . self::$database . '/schema=snap/relation=points/_gc2_snapshot_date=' . $day . '/';
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data-' . $first . '.parquet');

        $second = $this->snapshot()->create('snap', 'points', null, self::$database, ['parquet']);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded']);

        $this->assertSame('superseded', $this->snapshot()->get($first)['data']['status']);
        $this->assertSame($second, $this->snapshot()->getPublished('snap', 'points', $day)['data']['uuid']);
        $this->assertFileDoesNotExist($this->storeDir . '/' . $partition . 'data-' . $first . '.parquet', 'superseded files are removed');
        $this->assertFileDoesNotExist($this->storeDir . '/' . $partition . 'metadata-' . $first . '.json');
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data-' . $second . '.parquet');

        $collection = $this->json($this->catalogDir() . 'schema=snap/relation=points/collection.json');
        $items = array_values(array_filter($collection['links'], fn($l) => $l['rel'] === 'item'));
        $this->assertCount(1, $items, 'a superseded snapshot leaves the catalog');
        $this->assertSame("./_gc2_snapshot_date=$day/item.json", $items[0]['href']);
        $item = $this->json($this->catalogDir() . "schema=snap/relation=points/_gc2_snapshot_date=$day/item.json");
        $this->assertSame("./data-$second.parquet", $item['assets']['data']['href'], 'the item points at the surviving file');
    }

    public function testCatalogIsWrittenAfterASuccessfulSnapshot(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points', null, self::$database, ['parquet']);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));
        $day = $this->snapshot()->get($uuid)['data']['snapshot_date'];

        $catalog = $this->json($this->catalogDir() . 'catalog.json');
        $this->assertSame('Catalog', $catalog['type']);
        $this->assertSame(self::$database, $catalog['id']);
        $this->assertContains(
            './schema=snap/relation=points/collection.json',
            array_column($catalog['links'], 'href'),
            'the catalog links the relation relatively'
        );

        $collection = $this->json($this->catalogDir() . 'schema=snap/relation=points/collection.json');
        $this->assertSame('Collection', $collection['type']);
        $this->assertSame('snap.points', $collection['id']);
        $this->assertSame([[$day . 'T00:00:00Z', $day . 'T00:00:00Z']], $collection['extent']['temporal']['interval']);

        $item = $this->json($this->catalogDir() . "schema=snap/relation=points/_gc2_snapshot_date=$day/item.json");
        $this->assertSame('Feature', $item['type']);
        $this->assertSame("snap.points/$day", $item['id']);
        $this->assertSame($uuid, $item['properties']['gc2:snapshot_id']);
        $this->assertSame("./data-$uuid.parquet", $item['assets']['data']['href']);
        $this->assertFileExists(dirname($this->catalogDir() . "schema=snap/relation=points/_gc2_snapshot_date=$day/item.json") . '/data-' . $uuid . '.parquet', 'the asset href resolves next to the item');
        $this->assertSame('GeoParquet', $item['assets']['data']['title']);
        $this->assertCount(4, $item['bbox']);
        $this->assertSame($item['bbox'], $collection['extent']['spatial']['bbox'][0]);
        $this->assertSame('Polygon', $item['geometry']['type']);
    }

    public function testCatalogItemOfARelationWithoutGeometryHasNoFootprint(): void
    {
        $uuid = $this->snapshot()->create('snap', 'plain', null, self::$database, ['parquet']);
        $this->assertSame(1, $this->worker()->processPending(5)['succeeded']);
        $day = $this->snapshot()->get($uuid)['data']['snapshot_date'];

        $item = $this->json($this->catalogDir() . "schema=snap/relation=plain/_gc2_snapshot_date=$day/item.json");
        $this->assertNull($item['geometry']);
        $this->assertArrayNotHasKey('bbox', $item);
        $this->assertSame('Parquet', $item['assets']['data']['title']);

        $collection = $this->json($this->catalogDir() . 'schema=snap/relation=plain/collection.json');
        $this->assertSame([[-180, -90, 180, 90]], $collection['extent']['spatial']['bbox']);
        $this->assertSame('snap.plain', $collection['title'], 'a relation without layer metadata is titled by its name');
    }

    public function testFailedRunWritesNoCatalog(): void
    {
        $this->snapshot()->create('snap', 'does_not_exist', null, self::$database, ['parquet']);
        $this->assertSame(1, $this->worker()->processPending(5)['failed']);
        $this->assertFileDoesNotExist($this->catalogDir() . 'catalog.json', 'the catalog is only rebuilt after a publish');
    }


    /**
     * The catalog is a by-product: a storage that refuses one of its documents
     * must not cost the snapshot its published state, and the remaining
     * documents are still written.
     */
    public function testCatalogWriteFailureLeavesTheSnapshotSucceededAndWritesTheRest(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points', null, self::$database, ['parquet']);
        $storage = new class(new LocalSnapshotStorage($this->storeDir, 'unit')) implements \app\inc\snapshot\SnapshotStorage {
            public function __construct(private readonly LocalSnapshotStorage $inner)
            {
            }

            public function writeAt(string $database, string $relativePath, string $contents): void
            {
                if (str_ends_with($relativePath, 'collection.json')) {
                    throw new RuntimeException('no collections today');
                }
                $this->inner->writeAt($database, $relativePath, $contents);
            }

            public function exists(SnapshotRef $ref, string $file): bool
            {
                return $this->inner->exists($ref, $file);
            }

            public function size(SnapshotRef $ref, string $file): int
            {
                return $this->inner->size($ref, $file);
            }

            public function listFiles(SnapshotRef $ref): array
            {
                return $this->inner->listFiles($ref);
            }

            public function readStream(SnapshotRef $ref, string $file)
            {
                return $this->inner->readStream($ref, $file);
            }

            public function readRange(SnapshotRef $ref, string $file, int $start, int $length)
            {
                return $this->inner->readRange($ref, $file, $start, $length);
            }

            public function writeStream(SnapshotRef $ref, string $file, $stream): void
            {
                $this->inner->writeStream($ref, $file, $stream);
            }

            public function write(SnapshotRef $ref, string $file, string $contents): void
            {
                $this->inner->write($ref, $file, $contents);
            }

            public function delete(SnapshotRef $ref, string $file): void
            {
                $this->inner->delete($ref, $file);
            }

            public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string
            {
                return $this->inner->downloadUrl($ref, $file, $ttlSeconds);
            }

            public function locationOf(SnapshotRef $ref): string
            {
                return $this->inner->locationOf($ref);
            }
        };

        $summary = new SnapshotWorker(new Connection(database: self::$database), $storage, $this->tmpDir)->processPending(5);
        $this->assertSame(1, $summary['succeeded'], 'a catalog that cannot be written does not fail the snapshot');
        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertNotNull($row['published']);

        $day = $row['snapshot_date'];
        $this->assertFileDoesNotExist($this->catalogDir() . 'schema=snap/relation=points/collection.json');
        $this->assertFileExists($this->catalogDir() . "schema=snap/relation=points/_gc2_snapshot_date=$day/item.json", 'the rebuild carries on past a refused document');
        $this->assertFileExists($this->catalogDir() . 'catalog.json');
    }

    public function testFailedRunIsNotPublishedAndLeavesNoFiles(): void
    {
        $uuid = $this->snapshot()->create('snap', 'does_not_exist', null, self::$database, ['parquet']);
        $this->worker()->processPending(5);
        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('failed', $row['status']);
        $this->assertNull($row['published']);
        $this->assertSame([], $this->snapshot()->listPublished('snap', 'does_not_exist'));
    }

    private function catalogDir(): string
    {
        return $this->storeDir . '/unit/' . self::$database . '/';
    }

    private function json(string $path): array
    {
        $this->assertFileExists($path);
        return json_decode(file_get_contents($path), true);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($dir);
    }

    private function post(string $path, array $body): ?array
    {
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => json_encode($body),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents(self::API . $path, false, $ctx);
        if ($raw === false) {
            return null;
        }
        return json_decode($raw, true);
    }
}
