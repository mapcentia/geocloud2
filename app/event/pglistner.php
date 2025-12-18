<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use Amp\Parallel\Worker\Execution;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Postgres\PostgresListener;
use app\conf\App;
use app\event\tasks\DatabaseTask;
use app\event\tasks\PreparePayloadTask;
use app\event\tasks\RegisterPayload;
use app\inc\ShapeFilter;
use function Amp\async;
use function Amp\delay;


//
// --- Configuration ---
//
$batchSize = 100;    // Flush once 10 messages arrive
$timeThreshold = 2;     // Seconds to wait before flushing partial batches
$timerFrequency = 1;     // How often (seconds) to check for stale batches
$reconnectDelay = 5;     // How long (seconds) to wait before reconnect attempts

include_once __DIR__ . "/../vendor/autoload.php";
include_once __DIR__ . "/../conf/App.php";
include_once __DIR__ . "/../conf/Connection.php";
include_once __DIR__ . "/../inc/Model.php";
include_once __DIR__ . "/../models/Database.php";

new App();

$worker = Amp\Parallel\Worker\createWorker();

$dbs = [];

// Per-DB “batch state”: count, startTime, and payload array
$batchState = [];

// Keep track of async futures for concurrency
$futures = [];

$preparePayloadWithPDO = function (array $batchPayload, string $db) use ($worker): Execution {
    $task = new PreparePayloadTask($batchPayload, $db);
    return $worker->submit($task);
};

$RegisterPayloadWithPDO = function (array $batchPayload, string $db) use ($worker): Execution {
    $task = new RegisterPayload($batchPayload, $db);
    return $worker->submit($task);
};

/**
 * Flush batch for a specific DB asynchronously.
 */
$flushBatch = function (string $db, string $channelName = '') use (&$batchState, &$broadcastHandler, $preparePayloadWithPDO, $RegisterPayloadWithPDO) {
    return async(function () use ($db, $channelName, &$batchState, &$broadcastHandler, $preparePayloadWithPDO, $RegisterPayloadWithPDO) {
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
        $preparedPayload = $preparePayloadWithPDO($payLoad, $db)->await();
        // Await the asynchronous registerPayload to complete
        $RegisterPayloadWithPDO($preparedPayload, $db)->await();
        try {
            $clients = $broadcastHandler->gateway->getClients();
            foreach ($clients as $client) {
                $props = $broadcastHandler->getProperties($client);
                if ($props['db'] !== $db) {
                    continue;
                }
                // Filter payload per client relations (if specified)
                $batchForClient = [];
                $allowedRels = $props['rels'] ?? null;
                if (is_array($allowedRels) && !empty($allowedRels) && is_array($preparedPayload)) {
                    foreach ($preparedPayload[$db] as $key => $value) {
                        if (in_array($key, $allowedRels)) {
                            $batchForClient[$db][$key] = $value;;
                        }
                    }
                }
                if (empty($batchForClient)) {
                    // Nothing to send to this client after filtering
                    continue;
                }
                echo "[INFO] filtering payload\n";

                $filter = new ShapeFilter();

                $batch = [
                    'type' => 'batch',
                    'db' => $db,
                    'batch' => $batchForClient
                ];

                //$where = "text = 'test3'";
                $where = "";

                $batch = $filter->filter($batch, $where);

                echo "[INFO] Sending to: " . $client->getId() . "\n";
                $client->sendText(json_encode($batch));

            }
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
    $host = (new \app\inc\Connection())->host;
    $user = (new \app\inc\Connection())->user;
    $port = (new \app\inc\Connection())->port;
    $pw = (new \app\inc\Connection())->password;

    // The channel(s) we want to listen to
    $channel = "_gc2_notify_transaction";

    while (true) {
        $pool = null;
        try {
            $config = PostgresConfig::fromString(
                "host=$host user=$user password=$pw dbname=$db port=$port sslmode=allow"
            );
            // Attempt to connect + create a pool.
            // If DB isn't available, this will throw.
            $pool = new PostgresConnectionPool($config);
            // Now attempt to listen on your channel.
            // If DB fails mid-listen, it throws inside consumer loop.
            $listener = $pool->listen($channel);
            printf("[INFO] Connected and listening on channel '%s' for DB '%s'\n", $channel, $db);
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
            $pool?->close();
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

// --- Dynamic DB discovery loop: periodically fetch DBs and start listeners for new ones ---
async(function () use (&$dbs, &$batchState, &$futures, &$worker, $consumer, $flushBatch, $batchSize, $reconnectDelay, $startListenerForDb) {
    // Initial fetch to seed $dbs
    $current = [];
    try {
        $current = $worker->submit(new DatabaseTask())->await();
    } catch (Throwable $e) {
        echo "[ERROR] Initial DB discovery failed: " . $e->getMessage() . "\n";
    }
    if (!is_array($current)) {
        $current = [];
    }
    $dbs = $current;

    // Ensure listeners started for initial set
    foreach ($dbs as $db) {
        if (!isset($futures[$db])) {
            // Initialize batch state for this DB
            $batchState[$db] = [
                'count' => 0,
                'startTime' => time(),
                'payLoad' => []
            ];
            $futures[$db] = async(function () use ($db, &$batchState, $consumer, $flushBatch, $batchSize, $reconnectDelay, $startListenerForDb) {
                while (true) {
                    try {
                        $startListenerForDb($db, $batchState, $consumer, $flushBatch, $batchSize, $reconnectDelay);
                    } catch (Throwable $e) {
                        echo "[CRITICAL] Listener crashed for DB '{$db}': " . $e->getMessage() . "\n";
                        echo "[INFO] Restarting listener for DB '{$db}' in {$reconnectDelay}s...\n";
                        delay($reconnectDelay);
                    }
                }
            });
        }
    }

    while (true) {
        delay(10);
        try {
            $discovered = $worker->submit(new DatabaseTask())->await();
        } catch (Throwable $e) {
            echo "[ERROR] DB discovery failed: " . $e->getMessage() . "\n";
            continue;
        }
        if (!is_array($discovered)) {
            $discovered = [];
        }
        // Find new DBs not yet in $futures
        foreach ($discovered as $db) {
            if (!isset($futures[$db])) {
                echo "[INFO] New DB discovered: {$db}. Starting listener...\n";
                // Add to public list and start listener
                if (!in_array($db, $dbs, true)) {
                    $dbs[] = $db;
                }
                $batchState[$db] = [
                    'count' => 0,
                    'startTime' => time(),
                    'payLoad' => []
                ];
                $futures[$db] = async(function () use ($db, &$batchState, $consumer, $flushBatch, $batchSize, $reconnectDelay, $startListenerForDb) {
                    while (true) {
                        try {
                            $startListenerForDb($db, $batchState, $consumer, $flushBatch, $batchSize, $reconnectDelay);
                        } catch (Throwable $e) {
                            echo "[CRITICAL] Listener crashed for DB '{$db}': " . $e->getMessage() . "\n";
                            echo "[INFO] Restarting listener for DB '{$db}' in {$reconnectDelay}s...\n";
                            delay($reconnectDelay);
                        }
                    }
                });
            }
        }
        // Optionally: we could handle removed DBs here by checking $futures keys not in discovered
    }
});

