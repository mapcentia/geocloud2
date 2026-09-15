<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Background worker for Parquet snapshots. Drains pending rows in
 * settings.snapshots across all databases, exporting each relation with
 * ogr2ogr and uploading to S3.
 *
 *   * * * * * php -f /var/www/geocloud2/app/scripts/snapshot_worker.php
 *
 * Optional first argument limits the run to one database.
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\SnapshotWorker;
use app\models\Database;
use Aws\S3\S3Client;
use League\Flysystem\AwsS3V3\AwsS3V3Adapter;
use League\Flysystem\Filesystem;

new App();
Cache::setInstance();

$bucket = App::$param['snapshot']['bucket'] ?? '';
if ($bucket === '') {
    echo "SNAPSHOT WORKER: snapshot.bucket is not configured, nothing to do\n";
    exit(0);
}
$prefix = App::$param['snapshot']['prefix'] ?? '';
$region = App::$param['snapshot']['region'] ?? 'eu-west-1';

$batchPerDb = (int)(getenv('GC2_SNAPSHOT_BATCH') ?: 2);
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template', 'template_geocloud', 'mapcentia', 'gc2scheduler'];
$only = $argv[1] ?? null;

$filesystem = new Filesystem(new AwsS3V3Adapter(new S3Client([
    'credentials' => [
        'key' => App::$param['s3']['id'],
        'secret' => App::$param['s3']['secret'],
    ],
    'region' => $region,
    'version' => 'latest',
]), $bucket));

$dbs = $only ? [$only] : new Database()->listAllDbs()['data'];
$totals = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

echo "SNAPSHOT WORKER START: " . date('Y-m-d H:i:s') . "\n";

foreach ($dbs as $db) {
    if (in_array($db, $skip, true)) {
        continue;
    }
    try {
        $tmpDir = App::$param['path'] . "app/tmp/$db/__snapshots";
        $summary = new SnapshotWorker(new Connection(database: $db), $filesystem, $bucket, $prefix, $tmpDir)
            ->processPending($batchPerDb);
        if ($summary['processed'] > 0) {
            echo "$db: processed={$summary['processed']} ok={$summary['succeeded']} failed={$summary['failed']}\n";
            foreach ($totals as $k => $v) {
                $totals[$k] += $summary[$k];
            }
        }
    } catch (Throwable $e) {
        // Databases without settings.snapshots (or transient errors) are
        // skipped; this worker is best-effort per run.
        echo "$db: skipped ({$e->getMessage()})\n";
    }
}

echo "TOTAL: processed={$totals['processed']} ok={$totals['succeeded']} failed={$totals['failed']}\n";
