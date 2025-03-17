<?php

use app\conf\App;
use app\conf\Connection;
use app\models\Database;
use Amp\Future;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Postgres\PostgresListener;
use function Amp\async;
use function Amp\delay;

//
// --- Configuration ---
//
$batchSize      = 10;    // Flush once 10 messages arrive
$timeThreshold  = 2;     // Seconds to wait before flushing partial batches
$timerFrequency = 1;     // How often (seconds) to check for stale batches
$reconnectDelay = 5;     // How long (seconds) to wait before reconnect attempts

require_once __DIR__ . "/../vendor/autoload.php";
include_once __DIR__ . "/../conf/App.php";
include_once __DIR__ . "/../conf/Connection.php";
include_once __DIR__ . "/../inc/Model.php";
include_once __DIR__ . "/../models/Database.php";

new App();

$database = new Database();
Connection::$param["postgisschema"] = "public";
$dbs = $database->listAllDbs()['data'];

// Filter out any DB names you want to skip
$skipList = [
    'rdsadmin', 'template1', 'template0', 'postgres',
    'gc2scheduler', 'template_geocloud', 'mapcentia'
];

// Per-DB “batch state”: count, startTime, and payload array
$batchState = [];

// Keep track of async futures for concurrency
$futures = [];

/**
 * Prepare payload into the final structure.
 * Modify as needed based on your actual notification payload format.
 */
function preparePayload(array $payLoad, string $db): array
{
    $h = [];
    foreach ($payLoad as $p) {
        // Example: if each $p is "op,schema,table,id1,id2"
        $bits = explode(',', $p);
        // $bits[0] => operation type (?)
        // $bits[1] => schema
        // $bits[2] => table
        // $bits[3], $bits[4], etc. => IDs or other info
        $schemaTable = $bits[1] . '.' . $bits[2];
        $op = $bits[0];
        // Group them by DB, schema.table, then operation type
        $h[$db][$schemaTable][$op][] = array_slice($bits, 3);
    }
    return $h;
}

/**
 * Flush batch for a specific DB
 */
$flushBatch = function (string $db, string $channelName = '') use (&$batchState, &$broadcastHandler) {
    $count     = $batchState[$db]['count'];
    $payLoad   = $batchState[$db]['payLoad'];
    $startTime = $batchState[$db]['startTime'];

    echo "\n==========================================\n";
    echo "DB:         {$db}\n";
    echo "Channel:    " . ($channelName ?: '(periodic timer)') . "\n";
    echo "Batch size: {$count}\n";
    echo "Time:       " . (time() - $startTime) . " second(s)\n";
    echo "Payloads:\n";
    echo "==========================================\n\n";

    $p = preparePayload($payLoad, $db);

    try {
        // Send to all connected clients (or your own message bus).
        $broadcastHandler->sendToAll(json_encode($p));
    } catch (Throwable $error) {
        echo "[ERROR in flushBatch] " . $error->getMessage() . "\n";
    }

    // Reset counters for this DB
    $batchState[$db]['count']     = 0;
    $batchState[$db]['startTime'] = time();
    $batchState[$db]['payLoad']   = [];
};

/**
 * Consumer logic for each PostgresListener
 */
$consumer = function (PostgresListener $listener, string $db) use (
    &$batchState,
    $batchSize,
    $flushBatch
) {
    foreach ($listener as $notification) {
        // If this is the first message in a new batch, reset timer
        if ($batchState[$db]['count'] === 0) {
            $batchState[$db]['startTime'] = time();
        }

        // Accumulate
        $batchState[$db]['count']++;
        $batchState[$db]['payLoad'][] = $notification->payload;

        // Flush on batch size
        if ($batchState[$db]['count'] >= $batchSize) {
            $flushBatch($db, $notification->channel);
        }
    }
};

/**
 * Start a listener loop for a single DB. If the connection
 * or listener fails (including "DB not up"), close and retry
 * after $reconnectDelay.
 *
 * This loop continues indefinitely. If the DB is not up at
 * script startup, the catch block will handle the failure,
 * wait $reconnectDelay seconds, and retry.
 */
$startListenerForDb = function (
    string   $db,
    array    &$batchState,
    callable $consumer,
    callable $flushBatch,
    int      $batchSize,
    int      $reconnectDelay
) {
    // Pull out the global connection params:
    $host = Connection::$param["postgishost"];
    $user = Connection::$param["postgisuser"];
    $pw   = Connection::$param["postgispw"];

    // The channel(s) we want to listen to
    $channel = "_gc2_notify_transaction";

    while (true) {
        $pool = null;
        try {
            $config = PostgresConfig::fromString(
                "host={$host} user={$user} password={$pw} dbname={$db}"
            );

            // Attempt to connect + create a pool.
            // If DB isn't available, this will throw.
            $pool = new PostgresConnectionPool($config);

            // Now attempt to listen on your channel.
            // If DB fails mid-listen, it throws inside consumer loop.
            $listener = $pool->listen($channel);
            printf("Connected and listening on channel '%s' for DB '%s'\n", $channel, $db);

            // Blocking loop: handle incoming notifications for this DB
            $consumer($listener, $db);

            // If the foreach ends, the listener was closed unexpectedly.
            // We'll throw an exception to trigger the reconnect logic.
            throw new \RuntimeException("Listener ended unexpectedly for DB {$db}");
        } catch (Throwable $error) {
            // This includes the case where the DB/cluster is down when
            // we first start, or goes down at any time in the future.
            echo "[ERROR] DB '{$db}' => " . $error->getMessage() . "\n";
        } finally {
            // Clean up the pool if we got that far
            if ($pool !== null) {
                $pool->close();
            }
        }

        // Delay before attempting to reconnect
        echo "[INFO] Reconnecting to DB '{$db}' in {$reconnectDelay} second(s)...\n";
        delay($reconnectDelay);
    }
};

// -----------------------------------------------------
// Initialize batch state for each DB and start the listener tasks
// -----------------------------------------------------
$dbListenersStarted = 0;

foreach ($dbs as $db) {
    if (in_array($db, $skipList, true) || str_contains($db, 'test')) {
        continue;
    }

    // Initialize batch state for this DB
    $batchState[$db] = [
        'count'     => 0,
        'startTime' => time(),
        'payLoad'   => []
    ];

    // Spawn an async task that continuously attempts to connect and listen
    $futures[] = async(
        $startListenerForDb,
        $db,
        $batchState,
        $consumer,
        $flushBatch,
        $batchSize,
        $reconnectDelay
    );

    $dbListenersStarted++;
}

if ($dbListenersStarted === 0) {
    echo "[INFO] No databases to listen on. Exiting.\n";
    exit(0);
}

// -----------------------------------------------------
// Periodic timer to flush partial batches if too old
// -----------------------------------------------------
async(function () use (
    &$batchState,
    $timeThreshold,
    $timerFrequency,
    $flushBatch
) {
    while (true) {
        foreach ($batchState as $dbName => $state) {
            if ($state['count'] > 0) {
                $elapsed = time() - $state['startTime'];
                if ($elapsed >= $timeThreshold) {
                    // Flush partial batch for this DB
                    $flushBatch($dbName);
                }
            }
        }
        // Wait timerFrequency seconds before checking again
        delay($timerFrequency);
    }
});

// -----------------------------------------------------
// Wait for all async tasks (listeners + timer) to finish
// (In practice, they run indefinitely.)
// -----------------------------------------------------
try {
    Future\await($futures);
} catch (Throwable $error) {
    echo "[FATAL] Uncaught error: " . $error->getMessage() . "\n";
}

