<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use Aws\S3\S3Client;
use GuzzleHttp\Psr7\StreamWrapper;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

/**
 * Snapshots in an S3 bucket. Ranges become ranged GetObject calls (only the
 * requested bytes leave S3); downloads can be handed out as presigned URLs.
 */
final class S3SnapshotStorage extends FlysystemSnapshotStorage
{
    public function __construct(
        private readonly S3Client $client,
        private readonly string   $bucket,
        string                    $prefix = '',
    ) {
        parent::__construct(new Filesystem(new AwsS3V3Adapter($client, $bucket)), $prefix);
    }

    public function readRange(SnapshotRef $ref, string $file, int $start, int $length)
    {
        $end = $start + $length - 1;
        $result = $this->client->getObject([
            'Bucket' => $this->bucket,
            'Key' => $this->key($ref, $file),
            'Range' => "bytes=$start-$end",
            '@http' => ['stream' => true],
        ]);
        return StreamWrapper::getResource($result['Body']);
    }

    public function downloadUrl(SnapshotRef $ref, string $file, int $ttlSeconds): ?string
    {
        $cmd = $this->client->getCommand('GetObject', ['Bucket' => $this->bucket, 'Key' => $this->key($ref, $file)]);
        return (string)$this->client->createPresignedRequest($cmd, "+$ttlSeconds seconds")->getUri();
    }

    public function locationOf(SnapshotRef $ref): string
    {
        return "s3://{$this->bucket}/" . $this->key($ref);
    }
}
