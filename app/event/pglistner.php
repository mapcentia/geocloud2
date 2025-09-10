<?php

use Amp\Future;
use Amp\Parallel\Worker\Execution;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Postgres\PostgresListener;
use app\conf\App;
use app\conf\Connection;
use app\event\tasks\DatabaseTask;
use app\event\tasks\PreparePayloadTask;
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

Connection::$param["postgisschema"] = "public";
$worker = Amp\Parallel\Worker\createWorker();

$dbs = [];

// Per-DB “batch state”: count, startTime, and payload array
$batchState = [];

// Keep track of async futures for concurrency
$futures = [];

$preparePayloadWithPDO = function (array $batchPayload, string $db) use ($worker): Execution
{
    $task = new PreparePayloadTask($batchPayload, $db);
    return $worker->submit($task);
};

/**
 * Flush batch for a specific DB asynchronously.
 */
$flushBatch = function (string $db, string $channelName = '') use (&$batchState, &$broadcastHandler, $preparePayloadWithPDO) {
    return async(function () use ($db, $channelName, &$batchState, &$broadcastHandler, $preparePayloadWithPDO) {
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
        try {
            $clients = $broadcastHandler->gateway->getClients();
            foreach ($clients as $client) {
                if ($broadcastHandler->getProperties($client)['db'] !== $db) {
                    continue;
                }
                echo "[INFO] Sending to: " . $client->getId() . "\n";
                $client->sendText(json_encode([
                        'type' => 'batch',
                        'db' => $db,
                        'batch' => $preparedPayload,
                    ]
                ));
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
    $host = Connection::$param["postgishost"];
    $user = Connection::$param["postgisuser"];
    $pw = Connection::$param["postgispw"];

    // The channel(s) we want to listen to
    $channel = "_gc2_notify_transaction";

    while (true) {
        $pool = null;
        try {
            $config = PostgresConfig::fromString(
                "host={$host} user={$user} password={$pw} dbname={$db} sslmode=allow"
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

// --- Supervisor loop (Never exits, always restarts listeners) ---
async(function () use (
    &$dbs, &$batchState, $consumer,
    $flushBatch, $batchSize, $reconnectDelay, $startListenerForDb
) {
    while (true) {
        $futures = [];

        foreach ($dbs as $db) {
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

// --- Supervisor loop (Never exits, always restarts listeners) ---
async(function () use (&$dbs, &$worker) {
    $dbs = $worker->submit(new DatabaseTask())->await();
    delay(10);
});

