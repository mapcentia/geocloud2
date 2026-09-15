<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\Connection;
use app\inc\Model;
use app\inc\SnapshotWorker;
use app\models\Snapshot;
use Codeception\Test\Unit;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;

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
            new Filesystem(new LocalFilesystemAdapter($this->storeDir)),
            'test-bucket',
            $prefix,
            $this->tmpDir,
        );
    }

    private function snapshot(): Snapshot
    {
        return new Snapshot(new Connection(database: self::$database));
    }

    public function testPartitionKeyLayout(): void
    {
        $this->assertSame(
            'prod/mydb/schema=geodanmark/relation=bygning/harvest_date=2026-09-15/',
            SnapshotWorker::partitionKey('prod', 'mydb', 'geodanmark', 'bygning', '2026-09-15')
        );
        $this->assertSame(
            'mydb/schema=s/relation=r/harvest_date=2026-09-15/',
            SnapshotWorker::partitionKey('', 'mydb', 's', 'r', '2026-09-15'),
            'empty prefix is omitted'
        );
        $this->assertSame(
            'p/mydb/schema=s/relation=r/harvest_date=2026-09-15/',
            SnapshotWorker::partitionKey('/p/', 'mydb', 's', 'r', '2026-09-15'),
            'prefix slashes are normalised'
        );
    }

    public function testTableSnapshotSucceedsAndWritesParquetAndMetadata(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points', null, self::$database);
        $summary = $this->worker()->processPending(5);

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=points/harvest_date=' . gmdate('Y-m-d') . '/';
        $this->assertFileExists($this->storeDir . '/' . $partition . 'data.parquet');
        $this->assertGreaterThan(0, filesize($this->storeDir . '/' . $partition . 'data.parquet'));
        $this->assertFileExists($this->storeDir . '/' . $partition . 'metadata.json');

        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata.json'), true);
        $this->assertSame($uuid, $meta['snapshot_id']);
        $this->assertSame(self::$database, $meta['database']);
        $this->assertSame('snap.points', $meta['source']);
        $this->assertSame(3, $meta['row_count']);
        $this->assertSame('EPSG:25832', $meta['crs'], 'native SRID used when no srs is requested');
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $meta['schema_version']);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $meta['harvested_at']);

        $row = $this->snapshot()->get($uuid)['data'];
        $this->assertSame('succeeded', $row['status']);
        $this->assertSame('s3://test-bucket/' . $partition, $row['s3_path']);
        $this->assertSame(3, (int)$row['row_count']);
        $this->assertNotNull($row['finished']);

        $this->assertFileDoesNotExist($this->tmpDir . '/' . $uuid . '.parquet', 'tmp file is removed');
    }

    public function testViewSnapshotWithRequestedSrsReportsThatCrs(): void
    {
        $uuid = $this->snapshot()->create('snap', 'points_view', 4326, self::$database);
        $summary = $this->worker()->processPending(5);
        $this->assertSame(1, $summary['succeeded'], 'error: ' . ($this->snapshot()->get($uuid)['data']['error'] ?? ''));

        $partition = 'unit/' . self::$database . '/schema=snap/relation=points_view/harvest_date=' . gmdate('Y-m-d') . '/';
        $meta = json_decode(file_get_contents($this->storeDir . '/' . $partition . 'metadata.json'), true);
        $this->assertSame(2, $meta['row_count']);
        $this->assertSame('EPSG:4326', $meta['crs']);
    }

    public function testMissingRelationFailsRowWithError(): void
    {
        $uuid = $this->snapshot()->create('snap', 'does_not_exist', null, self::$database);
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
