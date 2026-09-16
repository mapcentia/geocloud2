<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

use app\inc\snapshot\S3SnapshotStorage;
use app\inc\snapshot\SnapshotRef;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use Codeception\Test\Unit;
use GuzzleHttp\Psr7\Utils;

class S3SnapshotStorageTest extends Unit
{
    protected UnitTester $tester;
    private MockHandler $mock;
    private S3SnapshotStorage $storage;
    private SnapshotRef $ref;

    protected function _before(): void
    {
        $this->mock = new MockHandler();
        $client = new S3Client([
            'region' => 'eu-west-1',
            'version' => 'latest',
            'credentials' => ['key' => 'AKIATEST', 'secret' => 'secret'],
            'handler' => $this->mock,
        ]);
        $this->storage = new S3SnapshotStorage($client, 'gc2-parquet', 'prod');
        $this->ref = new SnapshotRef('mydb', 'geo', 'roads', '2026-09-16', 'abc-123');
    }

    public function testKeyAndLocation(): void
    {
        $this->assertSame('prod/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/data-abc-123.parquet', $this->storage->key($this->ref, 'data-abc-123.parquet'));
        $this->assertSame('s3://gc2-parquet/prod/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/', $this->storage->locationOf($this->ref));
    }

    public function testReadRangeSendsRangedGetObjectAndReturnsBody(): void
    {
        $this->mock->append(new Result(['Body' => Utils::streamFor('PAR1')]));
        $stream = $this->storage->readRange($this->ref, 'data-abc-123.parquet', 0, 4);
        $this->assertSame('PAR1', stream_get_contents($stream));

        $cmd = $this->mock->getLastCommand();
        $this->assertSame('GetObject', $cmd->getName());
        $this->assertSame('gc2-parquet', $cmd['Bucket']);
        $this->assertSame('prod/mydb/schema=geo/relation=roads/_gc2_snapshot_date=2026-09-16/data-abc-123.parquet', $cmd['Key']);
        $this->assertSame('bytes=0-3', $cmd['Range']);
    }

    public function testReadRangeOffsetArithmetic(): void
    {
        $this->mock->append(new Result(['Body' => Utils::streamFor('xyz')]));
        $this->storage->readRange($this->ref, 'f', 100, 3);
        $this->assertSame('bytes=100-102', $this->mock->getLastCommand()['Range']);
    }

    public function testDownloadUrlIsPresignedForBucketAndKey(): void
    {
        $url = $this->storage->downloadUrl($this->ref, 'data-abc-123.parquet', 300);
        $this->assertNotNull($url);
        $this->assertStringStartsWith('https://gc2-parquet.s3.eu-west-1.amazonaws.com/prod/mydb/schema%3Dgeo/relation%3Droads/_gc2_snapshot_date%3D2026-09-16/data-abc-123.parquet?', $url);
        $this->assertStringContainsString('X-Amz-Expires=300', $url);
        $this->assertStringContainsString('X-Amz-Signature=', $url);
    }
}
