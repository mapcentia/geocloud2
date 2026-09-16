<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\exceptions\GC2Exception;
use app\inc\snapshot\LocalSnapshotStorage;
use app\inc\snapshot\S3SnapshotStorage;
use app\inc\snapshot\SnapshotStorageFactory;
use Codeception\Test\Unit;

class SnapshotStorageFactoryTest extends Unit
{
    protected UnitTester $tester;

    public function testLocalStorage(): void
    {
        $s = SnapshotStorageFactory::fromConfig(['storage' => 'local', 'localPath' => sys_get_temp_dir(), 'prefix' => 'p'], []);
        $this->assertInstanceOf(LocalSnapshotStorage::class, $s);
    }

    public function testS3StorageDefaultsWhenBucketSet(): void
    {
        $s = SnapshotStorageFactory::fromConfig(['bucket' => 'b', 'prefix' => '', 'region' => 'eu-west-1'], ['id' => 'k', 'secret' => 's']);
        $this->assertInstanceOf(S3SnapshotStorage::class, $s);
    }

    public function testUnconfiguredThrows501(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(501);
        SnapshotStorageFactory::fromConfig(['bucket' => ''], []);
    }

    public function testS3WithoutCredentialsThrows501(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(501);
        SnapshotStorageFactory::fromConfig(['storage' => 's3', 'bucket' => 'b'], ['id' => '', 'secret' => '']);
    }

    public function testLocalWithoutPathThrows501(): void
    {
        $this->expectException(GC2Exception::class);
        $this->expectExceptionCode(501);
        SnapshotStorageFactory::fromConfig(['storage' => 'local'], []);
    }
}
