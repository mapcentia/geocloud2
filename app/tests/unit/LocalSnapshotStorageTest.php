<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\LocalSnapshotStorage;
use app\inc\snapshot\SnapshotRef;
use Codeception\Test\Unit;

class LocalSnapshotStorageTest extends Unit
{
    protected UnitTester $tester;
    private string $root;
    private LocalSnapshotStorage $storage;
    private SnapshotRef $ref;

    protected function _before(): void
    {
        $this->root = sys_get_temp_dir() . '/local_snapshot_storage_' . bin2hex(random_bytes(4));
        mkdir($this->root, 0777, true);
        $this->storage = new LocalSnapshotStorage($this->root, 'unit');
        $this->ref = new SnapshotRef('mydb', 'geo', 'roads', '2026-09-16', 'abc-123');
    }

    protected function _after(): void
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->root);
    }

    public function testKeyLayoutAndLocation(): void
    {
        $this->assertSame('unit/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/data-abc-123.parquet', $this->storage->key($this->ref, 'data-abc-123.parquet'));
        $this->assertSame('mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/', (new LocalSnapshotStorage($this->root, ''))->key($this->ref));
        $this->assertSame('file://' . $this->root . '/unit/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/', $this->storage->locationOf($this->ref));
    }

    public function testWriteExistsSizeListAndDelete(): void
    {
        $this->assertFalse($this->storage->exists($this->ref, 'a.bin'));
        $this->storage->write($this->ref, 'a.bin', 'PAR1hello');
        $tmp = tmpfile();
        fwrite($tmp, '0123456789');
        rewind($tmp);
        $this->storage->writeStream($this->ref, 'b.bin', $tmp);
        fclose($tmp);

        $this->assertTrue($this->storage->exists($this->ref, 'a.bin'));
        $this->assertSame(9, $this->storage->size($this->ref, 'a.bin'));
        $this->assertSame(10, $this->storage->size($this->ref, 'b.bin'));
        $this->assertEquals(
            [['name' => 'a.bin', 'size_bytes' => 9], ['name' => 'b.bin', 'size_bytes' => 10]],
            $this->storage->listFiles($this->ref)
        );
        $this->assertSame('PAR1hello', stream_get_contents($this->storage->readStream($this->ref, 'a.bin')));

        $this->storage->delete($this->ref, 'a.bin');
        $this->assertFalse($this->storage->exists($this->ref, 'a.bin'));
        $this->storage->delete($this->ref, 'a.bin'); // missing: no exception
        $this->assertSame([['name' => 'b.bin', 'size_bytes' => 10]], $this->storage->listFiles($this->ref));
    }

    public function testReadRangeIsPositionedAtStart(): void
    {
        $this->storage->write($this->ref, 'r.bin', '0123456789');
        $this->assertSame('0123', fread($this->storage->readRange($this->ref, 'r.bin', 0, 4), 4));
        $this->assertSame('789', fread($this->storage->readRange($this->ref, 'r.bin', 7, 3), 3));
        $this->assertSame('9', fread($this->storage->readRange($this->ref, 'r.bin', 9, 1), 1));
        // Beyond EOF: the stream yields what exists; callers bound by the catalog size anyway.
        $this->assertSame('89', stream_get_contents($this->storage->readRange($this->ref, 'r.bin', 8, 100)));
    }

    /**
     * key() interpolates every segment straight into the path, so a separator
     * or a ".." in any of them would escape the snapshot directory. The
     * controller's regex already rules this out; the backend refuses anyway.
     */
    public function testKeyRejectsPathSeparatorsAndDotDot(): void
    {
        $bad = [
            'relation with slash' => new SnapshotRef('mydb', 'geo', 'roads/../..', '2026-09-16', 'id'),
            'schema with dotdot' => new SnapshotRef('mydb', '..', 'roads', '2026-09-16', 'id'),
            'database with backslash' => new SnapshotRef('my\\db', 'geo', 'roads', '2026-09-16', 'id'),
            'date with slash' => new SnapshotRef('mydb', 'geo', 'roads', '2026/09/16', 'id'),
        ];
        foreach ($bad as $why => $ref) {
            try {
                $this->storage->key($ref, 'x.parquet');
                $this->fail("expected InvalidArgumentException for $why");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        try {
            $this->storage->key($this->ref, '../../etc/passwd');
            $this->fail('expected InvalidArgumentException for a traversing file name');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
    }

    public function testNoDownloadUrlForLocalStorage(): void
    {
        $this->storage->write($this->ref, 'x.bin', 'x');
        $this->assertNull($this->storage->downloadUrl($this->ref, 'x.bin', 60));
    }
}
