<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 */

namespace app\inc\snapshot;

use app\conf\App;
use app\exceptions\GC2Exception;
use Aws\S3\S3Client;

/**
 * Builds the configured SnapshotStorage from App::$param['snapshot'] (+ the
 * s3 block for credentials). Shared by the cron worker and the read API so
 * both always see the same backend.
 */
final class SnapshotStorageFactory
{
    /**
     * @param array<string,mixed>|null $cfg   App::$param['snapshot']
     * @param array<string,mixed>|null $s3Cfg App::$param['s3']
     * @throws GC2Exception 501 SNAPSHOT_NOT_CONFIGURED
     */
    public static function fromConfig(?array $cfg, ?array $s3Cfg): SnapshotStorage
    {
        $cfg = $cfg ?? [];
        $storage = $cfg['storage'] ?? (!empty($cfg['bucket']) ? 's3' : '');
        $prefix = (string)($cfg['prefix'] ?? '');
        if ($storage === 'local') {
            $root = (string)($cfg['localPath'] ?? '');
            if ($root === '') {
                throw new GC2Exception("Snapshot storage is local but snapshot.localPath is not set", 501, null, "SNAPSHOT_NOT_CONFIGURED");
            }
            return new LocalSnapshotStorage($root, $prefix);
        }
        if ($storage === 's3') {
            $bucket = (string)($cfg['bucket'] ?? '');
            $id = (string)($s3Cfg['id'] ?? '');
            $secret = (string)($s3Cfg['secret'] ?? '');
            if ($bucket === '' || $id === '' || $secret === '') {
                throw new GC2Exception("Snapshot storage is s3 but snapshot.bucket or s3.id/s3.secret is not set", 501, null, "SNAPSHOT_NOT_CONFIGURED");
            }
            $client = new S3Client([
                'credentials' => ['key' => $id, 'secret' => $secret],
                'region' => (string)($cfg['region'] ?? 'eu-west-1'),
                'version' => 'latest',
            ]);
            return new S3SnapshotStorage($client, $bucket, $prefix);
        }
        throw new GC2Exception("Snapshot storage is not configured on this server", 501, null, "SNAPSHOT_NOT_CONFIGURED");
    }

    /** Convenience for runtime code. */
    public static function fromApp(): SnapshotStorage
    {
        return self::fromConfig(App::$param['snapshot'] ?? null, App::$param['s3'] ?? null);
    }
}
