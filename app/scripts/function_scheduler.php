<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Schedule-trigger dispatcher. Runs once a minute from cron; enqueues async
 * invocations for functions whose triggers.schedule is due. The function_worker
 * then executes them.
 *
 *   * * * * * php -f /var/www/geocloud2/app/scripts/function_scheduler.php
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\FunctionScheduler;
use app\models\Database;

new App();
Cache::setInstance();

$only = $argv[1] ?? null;
$now = new DateTime('now');
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template'];

$database = new Database();
$dbs = $only ? [$only] : $database->listAllDbs()['data'];
$total = 0;

foreach ($dbs as $db) {
    if (in_array($db, $skip, true)) {
        continue;
    }
    try {
        $enqueued = new FunctionScheduler(new Connection(database: $db))->enqueueDue($now);
        if ($enqueued > 0) {
            echo "$db: enqueued=$enqueued\n";
            $total += $enqueued;
        }
    } catch (Throwable $e) {
        // Databases without the functions tables (or transient errors) are skipped.
    }
}

echo "TOTAL: enqueued=$total\n";
