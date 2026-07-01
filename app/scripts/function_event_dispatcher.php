<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * DB-event-trigger dispatcher. Drains settings.function_event_queue and enqueues
 * async invocations for subscribed functions; the function_worker executes them.
 * Run frequently from cron (granularity is one minute via crontab).
 *
 *   * * * * * php -f /var/www/geocloud2/app/scripts/function_event_dispatcher.php
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Cache;
use app\inc\Connection;
use app\inc\FunctionEventDispatcher;
use app\models\Database;

new App();
Cache::setInstance();

$only = $argv[1] ?? null;
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template'];

$database = new Database();
$dbs = $only ? [$only] : $database->listAllDbs()['data'];
$total = 0;

foreach ($dbs as $db) {
    if (in_array($db, $skip, true)) {
        continue;
    }
    try {
        Database::setDb($db);
        $enqueued = (new FunctionEventDispatcher(new Connection(database: $db)))->dispatch();
        if ($enqueued > 0) {
            echo "$db: enqueued=$enqueued\n";
            $total += $enqueued;
        }
    } catch (Throwable $e) {
        // Databases without the function tables (or transient errors) are skipped.
    }
}

echo "TOTAL: enqueued=$total\n";
