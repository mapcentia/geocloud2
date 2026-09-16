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
use app\exceptions\GC2Exception;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\Model;
use app\inc\SnapshotWorker;
use app\inc\snapshot\SnapshotStorageFactory;
use app\models\Database;

new App();
Cache::setInstance();

try {
    $storage = SnapshotStorageFactory::fromApp();
} catch (GC2Exception $e) {
    echo "SNAPSHOT WORKER: {$e->getMessage()}, nothing to do\n";
    exit(0);
} catch (Throwable $e) {
    echo "SNAPSHOT WORKER: could not initialise snapshot storage: {$e->getMessage()}\n";
    exit(1);
}

$batchPerDb = (int)(getenv('GC2_SNAPSHOT_BATCH') ?: 2);
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template', 'template_geocloud', 'mapcentia', 'gc2scheduler'];
$only = $argv[1] ?? null;

$dbs = $only ? [$only] : new Database()->listAllDbs()['data'];
$totals = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

echo "SNAPSHOT WORKER START: " . date('Y-m-d H:i:s') . "\n";

foreach ($dbs as $db) {
    if (in_array($db, $skip, true)) {
        continue;
    }
    $connection = new Connection(database: $db);
    try {
        $tmpDir = App::$param['path'] . "app/tmp/$db/__snapshots";
        $summary = new SnapshotWorker($connection, $storage, $tmpDir)
            ->processPending($batchPerDb);
        if ($summary['processed'] > 0) {
            echo "$db: processed={$summary['processed']} ok={$summary['succeeded']} failed={$summary['failed']}\n";
            foreach ($totals as $k => $v) {
                $totals[$k] += $summary[$k];
            }
        }
    } catch (Throwable $e) {
        // Databases without settings.snapshots (or transient errors) are
        // skipped silently; this worker is best-effort per run.
    } finally {
        // Release this database's PDO connection; the cache is per process.
        Model::disconnect($connection);
    }
}

echo "TOTAL: processed={$totals['processed']} ok={$totals['succeeded']} failed={$totals['failed']}\n";
