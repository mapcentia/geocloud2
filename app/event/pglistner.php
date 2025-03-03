<?php

use app\conf\App;
use app\conf\Connection;
use app\models\Database;
use Amp\Future;
use Amp\Postgres\PostgresConfig;
use Amp\Postgres\PostgresConnectionPool;
use Amp\Postgres\PostgresListener;
use Amp\Websocket\Client\Rfc6455ConnectionFactory;
use Amp\Websocket\ConstantRateLimit;
use Amp\Websocket\Parser\Rfc6455ParserFactory;
use function Amp\async;
use function Amp\delay;

//
// --- Configuration ---
//
$batchSize = 10;  // Flush immediately once 3 messages are in a batch
$timeThreshold = 2;  // Seconds to wait before flushing if no new messages arrive
$timerFrequency = 1;  // How often (seconds) we check for “stale” partial batches

require_once __DIR__ . "/../vendor/autoload.php";
include_once __DIR__ . "/../conf/App.php";
include_once __DIR__ . "/../conf/Connection.php";
include_once __DIR__ . "/../inc/Model.php";
include_once __DIR__ . "/../models/Database.php";

new App();

$database = new Database();
Connection::$param["postgisschema"] = "public";
$dbs = $database->listAllDbs()['data'];
$pools = [];
$futures = [];
$skipList = [
    'rdsadmin',
    'template1',
    'template0',
    'postgres',
    'gc2scheduler',
    'template_geocloud',
    'mapcentia'
];

// Variables for batching
$count = 0;
$startTime = time();
$payLoad = [];

$connectionFactory = new Rfc6455ConnectionFactory(
    rateLimit: new ConstantRateLimit(
        bytesPerSecondLimit: 2 ** 17, // 128 KiB
        framesPerSecondLimit: 10,
    ),
    parserFactory: new Rfc6455ParserFactory(
        messageSizeLimit: 2 ** 20, // 1 MiB
    ),
    frameSplitThreshold: 2 ** 14, // 16 KiB
    closePeriod: 0.5, // 0.5 seconds
);

function preparePayload(array $payLoad, $db)
{
    $h = [];
    foreach ($payLoad as $p) {
        $bits = explode(',', $p);
        $h[$db][$bits[1] . '.' . $bits[2]][$bits[0]][] = [$bits[3], $bits[4]];
    }
    return ($h);
}

$flushBatch = function (string $db, int &$count, int &$startTime, string $channelName = '')
{
    global $broadcastHandler, $payLoad;
    echo "\n==========================================\n";
    echo "DB:         {$db}\n";
    echo "Channel:    " . ($channelName ?: '(periodic timer)') . "\n";
    echo "Batch size: {$count}\n";
    echo "Time:       " . (time() - $startTime) . " second(s)\n";
    echo "Payloads:\n";
    echo "==========================================\n\n";

    $p = preparePayload($payLoad, $db);
    try {
        $broadcastHandler->sendToAll(json_encode($p));
    } catch (Throwable $error) {
        echo $error->getMessage() . "\n";
    }
    $count = 0;
    $startTime = time();
    $payLoad = [];
};

$consumer = function (PostgresListener $listener) use (&$connection, &$connector, &$handshake, &$count, &$startTime, $batchSize, &$payLoad, $flushBatch): void {
    foreach ($listener as $notification) {
        // If this is the first message in a new batch,
        // reset the timer reference
        if ($count === 0) {
            $startTime = time();
        }

        // Accumulate
        $count++;
        $payLoad[] = $notification->payload;

        // FLUSH on batch size only
        if ($count >= $batchSize) {
            $flushBatch('mydb', $count, $startTime, $notification->channel);
        }
    }
};

foreach ($dbs as $db) {
    if (
        in_array($db, $skipList, true) ||
        str_contains($db, 'test')
    ) {
        continue;
    }
    $config = PostgresConfig::fromString("host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " database=$db");
    $pool = new PostgresConnectionPool($config);
    $pools[] = $pool;
    $channel1 = "_gc2_notify_transaction";
    $listener1 = $pool->listen($channel1);
    printf("Listening on channel '%s'\n", $listener1->getChannel());
    $futures[] = async($consumer, $listener1);
}

//
// Periodic timer to flush partial batches if too old
//
async(function () use (&$connection, &$connector, &$handshake, &$timeThreshold, &$count, &$startTime, $flushBatch) {
    while (true) {
        if ($count > 0) {
            $elapsed = time() - $startTime;
            if ($elapsed >= $timeThreshold) {
                $flushBatch('mydb', $count, $startTime, '');
            }
        }
        delay(2);
    }
});

Future\await($futures);

foreach ($pools as $pool) {
    $pool->close();
}
