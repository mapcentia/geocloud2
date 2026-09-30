<?php
/**
 * Runs one claimed tile seed job and owns its row from claim to outcome.
 *
 * Spawned detached by app/scripts/seed_worker.php, one process per job, because a
 * seed runs for hours and a cron tick must not. Every 5 seconds it writes a
 * heartbeat and the log tail, and re-reads cancel_requested: that flag is how a
 * request on any node stops a seed on this one. A shutdown function and signal
 * handlers finalise the row even when the process is killed, so a row is never
 * left 'running' by a process that is gone — the pattern get.php uses for
 * scheduler runs.
 *
 * Usage: php seed_run.php --database=<db> --uuid=<uuid> [--binary=<path>]
 */

use app\conf\App;
use app\inc\Connection;
use app\inc\tileseeder\SeedCommand;
use app\models\SeedJob;

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");
new App();

$options = getopt("", ["database:", "uuid:", "binary::"]);
$database = $options["database"] ?? null;
$uuid = $options["uuid"] ?? null;
if (!$database || !$uuid) {
    fwrite(STDERR, "--database and --uuid are required\n");
    exit(1);
}

$jobs = new SeedJob(connection: new Connection(database: $database));
$row = $jobs->get($uuid);
if ($row === null || $row['status'] !== 'running') {
    fwrite(STDERR, "job $uuid is not claimed for running\n");
    exit(1);
}

$logDir = App::$param['path'] . "app/tmp/$database/seed";
if (!is_dir($logDir)) {
    @mkdir($logDir, 0777, true);
}
$logPath = "$logDir/$uuid.log";
$tailBytes = App::$param['tileseeder']['logTailBytes'] ?? 8192;

$finalised = false;
$finalise = function (string $status, ?string $error) use (&$finalised, $jobs, $uuid, $logPath, $tailBytes): void {
    if ($finalised) {
        return;
    }
    $finalised = true;
    try {
        $jobs->finish($uuid, $status, $error, tail($logPath, $tailBytes));
    } catch (Throwable) {
        // Best effort: a dying process must not throw out of a shutdown/signal
        // handler. A row left 'running' here is still caught by reapStale().
    }
};
// A crash, an OOM kill or the worker's `timeout` must still leave a finished row.
register_shutdown_function(function () use (&$finalised, $finalise) {
    if (!$finalised) {
        $err = error_get_last();
        $finalise('failed', $err !== null ? 'terminated: ' . $err['message'] : 'terminated');
    }
});
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    foreach ([SIGINT, SIGTERM] as $sig) {
        pcntl_signal($sig, function () use ($finalise) {
            $finalise('cancelled', 'stopped by signal');
            exit(2);
        });
    }
}

$command = new SeedCommand(
    database: $database,
    tileset: $row['tileset'],
    grid: $row['grid'],
    zoomStart: (int)$row['zoom_start'],
    zoomEnd: (int)$row['zoom_end'],
    extentLayer: $row['extent_layer'],
    threads: (int)$row['threads'],
);
// The connection GC2 threaded into $jobs already resolved host/port/user/password
// for this database (app\inc\Connection), so the shell command is built from that
// explicit instance rather than the global \app\conf\Connection statics.
$argv = $command->argv([
    'host' => $jobs->postgishost,
    'port' => $jobs->postgisport,
    'user' => $jobs->postgisuser,
]);
if (!empty($options['binary'])) {
    $argv[0] = $options['binary'];   // the tests drive a stub instead of mapcache_seed
}

$log = fopen($logPath, 'w');
$process = proc_open($argv, [1 => $log, 2 => $log], $pipes, null,
    $command->env($jobs->postgispw) + ['PATH' => getenv('PATH')]);
if (!is_resource($process)) {
    $finalise('failed', 'could not start ' . $argv[0]);
    exit(1);
}
$jobs->setPid($uuid, (int)proc_get_status($process)['pid'], $logPath);

$grace = App::$param['tileseeder']['cancelGraceSeconds'] ?? 10;
$cancelling = null;
while (true) {
    $status = proc_get_status($process);
    if (!$status['running']) {
        $exit = $status['exitcode'];
        if ($cancelling !== null) {
            $finalise('cancelled', null);
            exit(2);
        }
        $finalise($exit === 0 ? 'succeeded' : 'failed', $exit === 0 ? null : "mapcache_seed exited with $exit");
        exit($exit === 0 ? 0 : 1);
    }
    $jobs->heartbeat($uuid, tail($logPath, $tailBytes));
    if ($cancelling === null && $jobs->isCancelRequested($uuid)) {
        $cancelling = time();
        proc_terminate($process, SIGTERM);
    } elseif ($cancelling !== null && time() - $cancelling >= $grace) {
        proc_terminate($process, SIGKILL);
    }
    sleep(5);
}

/** The last $bytes of the log, so a client sees what the process last said. */
function tail(string $path, int $bytes): ?string
{
    if (!is_file($path)) {
        return null;
    }
    $size = filesize($path);
    $fh = fopen($path, 'r');
    if ($fh === false) {
        return null;
    }
    if ($size > $bytes) {
        fseek($fh, -$bytes, SEEK_END);
    }
    $out = stream_get_contents($fh);
    fclose($fh);
    return $out === false ? null : $out;
}
