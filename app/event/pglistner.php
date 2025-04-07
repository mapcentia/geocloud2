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
$batchSize = 10;    // Flush once 10 messages arrive
$timeThreshold = 2;     // Seconds to wait before flushing partial batches
$timerFrequency = 1;     // How often (seconds) to check for stale batches
$reconnectDelay = 5;     // How long (seconds) to wait before reconnect attempts

include_once __DIR__ . "/../vendor/autoload.php";
include_once __DIR__ . "/../conf/App.php";
include_once __DIR__ . "/../conf/Connection.php";
include_once __DIR__ . "/../inc/Model.php";
include_once __DIR__ . "/../models/Database.php";

new App();

$database = new Database();
Connection::$param["postgisschema"] = "public";

$dbs = ['mydb'];
try {
    $dbs = $database->listAllDbs()['data'];
} catch (PDOException $e) {

}

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
 * Prepare payload into the final structure by batching queries.
 *
 * This version groups notifications by schema.table and key column (for INSERT/UPDATE)
 * so that a single query (with a WHERE ... IN clause) is run per group.
 *
 * @param array $batchPayload Array of notification payload strings.
 * @param string $db Database name.
 *
 * @return Future Resolves to the final structured result.
 */
function preparePayload(array $batchPayload, string $db): Future
{
    return async(function () use ($batchPayload, $db) {
        $host = Connection::$param["postgishost"];
        $user = Connection::$param["postgisuser"];
        $pw = Connection::$param["postgispw"];

        // Final structure to return (grouped by DB, table, operation).
        $results = [];
        // Group notifications that require a database fetch (INSERT/UPDATE) by table and key.
        $grouped = [];

        foreach ($batchPayload as $p) {
            $bits = explode(',', $p);
            $op = $bits[0];
            $schema = $bits[1];
            $table = $bits[2];
            $key = $bits[3];
            $value = $bits[4];
            $schemaTable = "{$schema}.{$table}";

            // Build final structure for broadcast
            $results[$db][$schemaTable][$op][] = array_slice($bits, 3);

            // For INSERT/UPDATE, we need to fetch the full record.
            if ($op === 'INSERT' || $op === 'UPDATE') {
                // Group by table and key.
                $groupKey = $schemaTable . ':' . $key;
                if (!isset($grouped[$groupKey])) {
                    $grouped[$groupKey] = [
                        'schemaTable' => $schemaTable,
                        'key' => $key,
                        'values' => []
                    ];
                }
                $grouped[$groupKey]['values'][] = $value;
            }
        }
        // Create a connection pool for asynchronous queries.
        $config = PostgresConfig::fromString("host={$host} user={$user} password={$pw} dbname={$db}");
        $pool = new PostgresConnectionPool($config);
        // For each group, issue a single batched query.
        foreach ($grouped as $group) {
            $rows = [];
            $schemaTable = $group['schemaTable'];
            $key = $group['key'];
            // Remove duplicates to avoid redundant queries.
            $values = array_unique($group['values']);
            // Quote values – in production, use parameterized queries to avoid SQL injection.
            $quotedValues = array_map(function ($val) {
                return "'" . addslashes($val) . "'";
            }, $values);
            $inList = implode(',', $quotedValues);
            $sql = "SELECT * FROM {$schemaTable} WHERE \"{$key}\" IN ({$inList})";
            $queryResult = $pool->query($sql);
            while (($row = ($queryResult->fetchRow())) !== null) {
                $rows[] = $row;
            }
            if (!isset($results[$db][$schemaTable]['full_data'])) {
                $results[$db][$schemaTable]['full_data'] = [];
            }
            $results[$db][$schemaTable]['full_data'] = array_merge(
                $results[$db][$schemaTable]['full_data'],
                $rows
            );

        }
        $pool->close();
        // Return the structured result.
        return $results;
    });
}

/**
 * Flush batch for a specific DB asynchronously.
 */
$flushBatch = function (string $db, string $channelName = '') use (&$batchState, &$broadcastHandler) {
    return async(function () use ($db, $channelName, &$batchState, &$broadcastHandler) {
        $count = $batchState[$db]['count'];
        $payLoad = $batchState[$db]['payLoad'];
        $startTime = $batchState[$db]['startTime'];

        echo "\n==========================================\n";
        echo "DB:         {$db}\n";
        echo "Channel:    " . ($channelName ?: '(periodic timer)') . "\n";
        echo "Batch size: {$count}\n";
        echo "Time:       " . (time() - $startTime) . " second(s)\n";
        echo "Payloads:\n";
        echo "==========================================\n\n";

        // Await the asynchronous preparePayload to complete
        $p = preparePayload($payLoad, $db)->await();

        print_r($p);

        try {
            // Send to all connected clients (or your own message bus).
            $broadcastHandler->sendToAll(json_encode($p));
        } catch (Throwable $error) {
            echo "[ERROR in flushBatch] " . $error->getMessage() . "\n";
        }

        // Reset counters for this DB
        $batchState[$db]['count'] = 0;
        $batchState[$db]['startTime'] = time();
        $batchState[$db]['payLoad'] = [];
    });
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
            $flushBatch($db, $notification->channel)->await();
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
    $pw = Connection::$param["postgispw"];

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
                    $flushBatch($dbName)->await();
                }
            }
        }
        // Wait timerFrequency seconds before checking again
        delay($timerFrequency);
    }
});

// --- Supervisor loop (Never exits, always restarts listeners) ---
async(function () use (
    $dbs, $skipList, &$batchState, $consumer,
    $flushBatch, $batchSize, $reconnectDelay, $startListenerForDb
) {
    while (true) {
        $futures = [];

        foreach ($dbs as $db) {
            if (in_array($db, $skipList, true) || str_contains($db, 'test')) {
                continue;
            }
            $batchState[$db] = [
                'count' => 0,
                'startTime' => time(),
                'payLoad' => []
            ];
            $futures[$db] = async(function () use (
                $db, &$batchState, $consumer, $flushBatch,
                $batchSize, $reconnectDelay, $startListenerForDb
            ) {
                while (true) {
                    try {
                        $startListenerForDb(
                            $db, $batchState, $consumer, $flushBatch,
                            $batchSize, $reconnectDelay
                        );
                    } catch (Throwable $e) {
                        echo "[CRITICAL] Listener crashed for DB '{$db}': " . $e->getMessage() . "\n";
                        echo "[INFO] Restarting listener for DB '{$db}' in {$reconnectDelay}s...\n";
                        delay($reconnectDelay);
                    }
                }
            });
        }
        // Wait until ANY of the futures complete or fail unexpectedly
        try {
            Future\await(array_values($futures));
            echo "[WARNING] All listeners unexpectedly completed. Restarting immediately...\n";
        } catch (Throwable $e) {
            echo "[CRITICAL] One or more listeners failed unexpectedly: " . $e->getMessage() . "\n";
        }
        // If you reach this point, something caused your listener tasks to finish/crash.
        // Sleep briefly to avoid rapid looping on permanent errors.
        delay($reconnectDelay);
        echo "[INFO] Supervisor restarting listeners...\n";
    }
});
