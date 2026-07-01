<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Background worker for async function invocations. Runs as a privileged CLI
 * process (cron) so it can exec the runtime + sandbox, unlike the web-server
 * user. Drains pending invocations across all databases, once per run.
 *
 *   * * * * * php -f /var/www/geocloud2/app/scripts/function_worker.php
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\FunctionWorker;
use app\models\Database;

new App();
// The web entrypoint sets this up; the CLI must too (Setting/cache lookups).
Cache::setInstance();

$batchPerDb = (int)(getenv('GC2_FUNCTION_BATCH') ?: 20);
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template'];

// Optional first argument limits the run to a single database.
$only = $argv[1] ?? null;

$database = new Database();
$totals = ['processed' => 0, 'succeeded' => 0, 'failed' => 0];

$dbs = $only ? [$only] : $database->listAllDbs()['data'];
foreach ($dbs as $db) {
    if (in_array($db, $skip, true)) {
        continue;
    }
    try {
        Database::setDb($db);
        $summary = (new FunctionWorker(new Connection(database: $db)))->processPending($batchPerDb);
        if ($summary['processed'] > 0) {
            echo "$db: processed={$summary['processed']} ok={$summary['succeeded']} failed={$summary['failed']}\n";
            foreach ($totals as $k => $v) {
                $totals[$k] += $summary[$k];
            }
        }
    } catch (Throwable $e) {
        // Databases without settings.function_invocations (or transient errors)
        // are skipped silently; this worker is best-effort per run.
    }
}

echo "TOTAL: processed={$totals['processed']} ok={$totals['succeeded']} failed={$totals['failed']}\n";
