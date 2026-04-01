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
$batchSize = 1000;    // Flush once 1000 messages arrive
$timeThreshold = 2;     // Seconds to wait before flushing partial batches
$timerFrequency = 1;     // How often (seconds) to check for stale batches
$reconnectDelay = 5;     // How long (seconds) to wait before reconnect attempts

include_once __DIR__ . "/../vendor/autoload.php";
include_once __DIR__ . "/../conf/App.php";
include_once __DIR__ . "/../conf/Connection.php";
include_once __DIR__ . "/../inc/Model.php";
include_once __DIR__ . "/../models/Database.php";

new App();

$workerPool = Amp\Parallel\Worker\workerPool();

$dbs = [];

// Per-DB “batch state”: count, startTime, and payload array
$batchState = [];

// Keep track of async futures for concurrency
$futures = [];

$preparePayloadWithPDO = function (array $batchPayload, string $db) use ($workerPool): Execution {
    $task = new PreparePayloadTask($batchPayload, $db);
    return $workerPool->getWorker()->submit($task);
};

$RegisterPayloadWithPDO = function (array $batchPayload, string $db) use ($workerPool): Execution {
    $task = new RegisterPayload($batchPayload, $db);
    return $workerPool->getWorker()->submit($task);
};

$opMap = ['I' => 'INSERT', 'U' => 'UPDATE', 'D' => 'DELETE'];

/**
 * Flush batch for a specific DB asynchronously.
 * Drains the outbox table and processes the events.
 */
$flushBatch = function (string $db, string $channelName = '') use (&$batchState, &$broadcastHandler, $preparePayloadWithPDO, $RegisterPayloadWithPDO, $opMap) {
    return async(function () use ($db, $channelName, &$batchState, &$broadcastHandler, $preparePayloadWithPDO, $RegisterPayloadWithPDO, $opMap) {
        try {
            $pool = $batchState[$db]['pool'] ?? null;
            if (!$pool) {
                echo "[ERROR] No pool available for DB '{$db}', skipping flush\n";
                return;
            }

            // Atomically drain outbox
            $result = $pool->query(
                "WITH d AS (DELETE FROM settings.outbox RETURNING id, op, schema_name, table_name, pk_column, pk_value, payload) " .
                "SELECT * FROM d ORDER BY id"
            );

            $rows = [];
            foreach ($result as $row) {
                $rows[] = $row;
            }

            if (empty($rows)) {
                return;
            }

            $drained = count($rows);

            // Coalesce U-events: last-write-wins per (schema, table, pk)
            $seenUpdates = [];
            $coalesced = [];
            for ($i = count($rows) - 1; $i >= 0; $i--) {
                $row = $rows[$i];
                if ($row['op'] === 'U') {
                    $key = "{$row['schema_name']}.{$row['table_name']}:{$row['pk_value']}";
                    if (isset($seenUpdates[$key])) {
                        continue;
                    }
                    $seenUpdates[$key] = true;
                }
                $coalesced[] = $row;
            }
            $coalesced = array_reverse($coalesced);

            $payLoad = [];
            foreach ($coalesced as $row) {
                $op = $opMap[$row['op']] ?? $row['op'];
                $entry = [
                    'op' => $op,
                    'schema' => $row['schema_name'],
                    'table' => $row['table_name'],
                    'pk_column' => $row['pk_column'],
                    'pk_value' => $row['pk_value'],
                ];
                if (!empty($row['payload'])) {
                    $entry['payload'] = $row['payload'];
                }
                $payLoad[] = $entry;
            }

            $count = count($payLoad);
            $startTime = $batchState[$db]['startTime'];

            echo "\n==========================================\n";
            echo "DB:         {$db}\n";
            echo "Channel:    " . ($channelName ?: '(periodic timer)') . "\n";
            echo "Drained:    {$drained}, after coalesce: {$count}\n";
            echo "Time:       " . (time() - $startTime) . " second(s)\n";
            echo "Payloads:\n";
            echo "==========================================\n\n";

            // Await the asynchronous preparePayload to complete
            $preparedPayload = $preparePayloadWithPDO($payLoad, $db)->await();
            // Await the asynchronous registerPayload to complete
            $RegisterPayloadWithPDO($preparedPayload, $db)->await();

            $clients = $broadcastHandler->gateway->getClients();
            foreach ($clients as $client) {
                $props = $broadcastHandler->getProperties($client);
                if ($props['db'] !== $db) {
                    continue;
                }

                $subscriptions = $props['subscriptions'] ?? [];
                $rawSubscriptions = $props['rawSubscriptions'] ?? [];
                $allowedRels = $props['rels'] ?? null;

                // --- GraphQL Subscription-based delivery ---
                if (!empty($subscriptions)) {
                    foreach ($subscriptions as $sub) {
                        $subRel = $sub['rel'];
                        $subOp = $sub['op'];       // 'INSERT', 'UPDATE', or 'DELETE'
                        $subWhere = $sub['where'];
                        $subColumns = $sub['columns'];
                        $subField = $sub['field'];
                        $subId = $sub['id'];

                        // Check if this batch has data for the subscription's relation and operation
                        if (!isset($preparedPayload[$db][$subRel])) {
                            continue;
                        }
                        $relData = $preparedPayload[$db][$subRel];
                        if (empty($relData[$subOp])) {
                            continue;
                        }

                        // Build a mini-batch with only this relation and operation
                        $miniBatch = [
                            'type' => 'batch',
                            'db' => $db,
                            'batch' => [
                                $db => [
                                    $subRel => [
                                        $subOp => $relData[$subOp],
                                        'full_data' => $relData['full_data'] ?? [],
                                    ]
                                ]
                            ]
                        ];

                        // Apply ShapeFilter with subscription's where and columns
                        $filter = new ShapeFilter();
                        $miniBatch = $filter->filter($miniBatch, $subWhere, $subColumns);

                        // Extract filtered full_data rows
                        $rows = $miniBatch['batch'][$db][$subRel]['full_data'] ?? [];
                        if (empty($rows)) {
                            continue;
                        }

                        // Send as GraphQL subscription response
                        $response = [
                            'type' => 'subscription',
                            'id' => $subId,
                            'data' => [
                                $subField => $rows,
                            ],
                        ];

                        echo "[INFO] Subscription $subId -> {$client->getId()} ($subOp on $subRel, " . count($rows) . " rows)\n";
                        $client->sendText(json_encode($response));
                    }
                    continue; // Subscriptions handled, skip legacy batch for this client
                }

                // --- Raw Subscription-based delivery ---
                if (!empty($rawSubscriptions)) {
                    foreach ($rawSubscriptions as $sub) {
                        $subRel = $sub['schema'] . '.' . $sub['rel'];
                        $subOp = $sub['op'];       // 'INSERT', 'UPDATE', or 'DELETE'
                        $subWhere = $sub['where'];
                        $subColumns = $sub['columns'];
                        $subId = $sub['id'];

                        // Check if this batch has data for the subscription's relation and operation
                        if (!isset($preparedPayload[$db][$subRel])) {
                            continue;
                        }
                        $relData = $preparedPayload[$db][$subRel];
//                        if (empty($relData[$subOp])) {
//                            continue;
//                        }

                        // Build a mini-batch with only this relation and operation
                        $miniBatch = [
                            'type' => 'batch',
                            'db' => $db,
                            'batch' => [
                                $db => [
                                    $subRel => [
                                        $subOp => $relData[$subOp],
                                        'full_data' => $relData['full_data'] ?? [],
                                    ]
                                ]
                            ]
                        ];

                        // Apply ShapeFilter with subscription's where and columns
                        $filter = new ShapeFilter();
                        $miniBatch = $filter->filter($miniBatch, $subWhere, $subColumns);

                        echo "[INFO] Subscription $subId -> {$client->getId()} ($subOp on $subRel, " . count($rows) . " rows)\n";
                        $client->sendText(json_encode($miniBatch));
                    }
                    continue; // Subscriptions handled, skip legacy batch for this client
                }

                // --- Legacy batch delivery (clients without subscriptions) ---
                $batchForClient = [];
                if (is_array($allowedRels) && !empty($allowedRels) && is_array($preparedPayload)) {
                    foreach ($preparedPayload[$db] as $key => $value) {
                        if (in_array($key, $allowedRels)) {
                            $batchForClient[$db][$key] = $value;;
                        }
                    }
                }
                if (empty($batchForClient)) {
                    continue;
                }

                $batch = [
                    'type' => 'batch',
                    'db' => $db,
                    'batch' => $batchForClient
                ];

                echo "[INFO] Sending to: " . $client->getId() . "\n";
                $client->sendText(json_encode($batch));
            }
        } catch (Throwable $error) {
            echo "[ERROR in flushBatch] " . $error->getMessage() . "\n";
        } finally {
            // Always reset counters so the system keeps running
            $batchState[$db]['count'] = 0;
            $batchState[$db]['startTime'] = time();
        }
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
        // If this is the first wake-up in a new batch, reset timer
        if ($batchState[$db]['count'] === 0) {
            $batchState[$db]['startTime'] = time();
        }
        // Count wake-ups (actual data is in the outbox table)
        $batchState[$db]['count']++;
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

    // The channel for outbox wake-up notifications
    $channel = "_gc2_outbox_wake";

    while (true) {
        $pool = null;
        try {
            $config = PostgresConfig::fromString(
                "host=$host user=$user password=$pw dbname=$db port=$port sslmode=allow"
            );
            // Attempt to connect + create a pool.
            // If DB isn't available, this will throw.
            $pool = new PostgresConnectionPool($config);
            // Store pool reference so flushBatch can query outbox
            $batchState[$db]['pool'] = $pool;
            // Drain any events left in outbox from before this connection
            $batchState[$db]['count'] = 1;
            $batchState[$db]['startTime'] = 0;
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
async(function () use (&$dbs, &$batchState, &$futures, &$workerPool, $consumer, $flushBatch, $batchSize, $reconnectDelay, $startListenerForDb) {
    // Initial fetch to seed $dbs
    $current = [];
    try {
        $current = $workerPool->getWorker()->submit(new DatabaseTask())->await();
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
                'pool' => null,
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
            $discovered = $workerPool->getWorker()->submit(new DatabaseTask())->await();
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
                    'pool' => null,
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

