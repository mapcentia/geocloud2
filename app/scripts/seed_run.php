<?php
/**
 * Runs one claimed tile seed job and owns its row from claim to outcome.
 *
 * Spawned detached by app/scripts/seed_worker.php, one process per job, because a
 * seed runs for hours and a cron tick must not. Every 5 seconds it writes a
 * heartbeat and the log tail, and re-reads cancel_requested: that flag is how a
 * request on any node stops a seed on this one. A shutdown function and signal
 * handlers stop the child and finalise the row even when this process is killed,
 * so a row is never left 'running' with an orphaned mapcache_seed still writing
 * tiles behind it — the pattern get.php uses for scheduler runs.
 *
 * Ownership runs the other way too: whoever holds the row holds the outcome. If
 * the heartbeat finds the row is no longer 'running' (the reaper, or a tick that
 * mis-probed the spawn, got there first) this process stops its child and exits
 * without writing a status at all, rather than overwriting what that other
 * writer decided.
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
// Both reach the filesystem below (log path, mapcache config path) before either
// touches SQL; refuse anything that is not the shape we expect rather than let a
// crafted value walk a path.
if (!preg_match('/^[0-9a-f-]{36}$/', $uuid)) {
    fwrite(STDERR, "--uuid is not a valid UUID\n");
    exit(1);
}
if (!preg_match('/^[A-Za-z0-9_\-]+$/', $database)) {
    fwrite(STDERR, "--database contains invalid characters\n");
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
$grace = App::$param['tileseeder']['cancelGraceSeconds'] ?? 10;

$finalised = false;
/** Writes the outcome once. Never throws: a dying process must not fault out of
 *  a shutdown/signal handler. Returns whether the write actually landed, so a
 *  caller that promised a status in its exit code does not lie about it. */
$finalise = function (string $status, ?string $error) use (&$finalised, $jobs, $uuid, $logPath, $tailBytes): bool {
    if ($finalised) {
        return true;
    }
    $finalised = true;
    try {
        $jobs->finish($uuid, $status, $error, tail($logPath, $tailBytes));
        return true;
    } catch (Throwable $e) {
        // Postgres bounce, connection drop, whatever it is: without this line the
        // row is left 'running' and reapStale() reports it as a stale timeout ten
        // minutes later, pointing the operator at the wrong cause with no trace.
        error_log("seed_run.php: could not finalise job $uuid as '$status': " . $e->getMessage());
        return false;
    }
};

$process = null; // set once mapcache_seed is spawned; handlers below kill it first
// A crash, an OOM kill, `docker stop`, a supervisord restart or the worker's
// `timeout` must still stop the child and leave a finished row — never an
// orphaned mapcache_seed and a row that claims 'cancelled' while it keeps running.
register_shutdown_function(function () use (&$finalised, $finalise, &$process, $jobs, $uuid, $grace) {
    if ($finalised) {
        return;
    }
    stopChild($process, $grace);
    if (wasCancelRequested($jobs, $uuid)) {
        $finalise('cancelled', null);
        return;
    }
    $err = error_get_last();
    $finalise('failed', $err !== null ? 'terminated: ' . $err['message'] : 'terminated');
});
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    // SIGHUP/SIGQUIT too: the script must not depend on how its caller spawns it
    // (e.g. under nohup, which only happens to cover SIGHUP) to keep the child
    // from being orphaned.
    foreach ([SIGINT, SIGTERM, SIGHUP, SIGQUIT] as $sig) {
        pcntl_signal($sig, function () use (&$process, $jobs, $uuid, $grace, $finalise) {
            stopChild($process, $grace);
            // A signal alone is not a cancellation: only cancel_requested is. A
            // 14-hour seed cut off by the worker's timeout is 'failed', not
            // 'cancelled' — the client must be able to tell that apart from its
            // own DELETE.
            if (wasCancelRequested($jobs, $uuid)) {
                $ok = $finalise('cancelled', null);
                exit($ok ? 2 : 1);
            }
            $finalise('failed', 'terminated by signal');
            exit(1);
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

// The window between claim and spawn: a cancel asked for before the child ever
// started must not start it.
if ($jobs->isCancelRequested($uuid)) {
    $ok = $finalise('cancelled', null);
    exit($ok ? 2 : 1);
}

$log = fopen($logPath, 'w');
if ($log === false) {
    // Blame the actual cause (an unwritable app/tmp, e.g. root-owned from a cron
    // run) rather than reporting "could not start mapcache_seed" for a binary
    // that was never even tried.
    $finalise('failed', "could not open log file for writing: $logPath");
    exit(1);
}
// mapcache_seed's own build (GDAL/PROJ) may need LD_LIBRARY_PATH, PROJ_LIB,
// GDAL_DATA or HOME; v3's exec() inherited the whole environment and this must
// too. PGPASSWORD always wins over anything of the same name inherited.
$inheritedEnv = getenv();
$env = $command->env($jobs->postgispw) + (is_array($inheritedEnv) ? $inheritedEnv : []);
$process = proc_open($argv, [1 => $log, 2 => $log], $pipes, null, $env);
if (!is_resource($process)) {
    $finalise('failed', 'could not start ' . $argv[0]);
    exit(1);
}
$jobs->setPid($uuid, (int)proc_get_status($process)['pid'], $logPath);

while (true) {
    $status = proc_get_status($process);
    if (!$status['running']) {
        $exit = $status['exitcode'];
        $ok = $finalise($exit === 0 ? 'succeeded' : 'failed', $exit === 0 ? null : "mapcache_seed exited with $exit");
        exit($ok && $exit === 0 ? 0 : 1);
    }
    if ($jobs->heartbeat($uuid, tail($logPath, $tailBytes)) === 0) {
        // The row is not 'running' any more, so this process no longer owns it:
        // the reaper failed it as stale (a PDO stall longer than the stale
        // window), or the tick's own 200 ms posix_kill($pid, 0) probe read a
        // false negative and finalised it as "could not spawn". Either way the
        // other writer owns the row's outcome, and the only thing left that is
        // this process's business is the child it started — which would
        // otherwise keep writing tiles, unobservable and unstoppable
        // (requestCancel() answers 'noop' for a non-running row) while the
        // freed slot let the next tick start a second seed on top of it. Stop
        // the child and leave the status alone, including from the shutdown
        // function: $finalised is set first so a throw inside stopChild() can't
        // let it write one either.
        $finalised = true;
        stopChild($process, $grace);
        error_log("seed_run.php: job $uuid is no longer 'running' — someone else finished it; stopped the seed and exited without writing a status");
        exit(1);
    }
    if ($jobs->isCancelRequested($uuid)) {
        // Reuse the same stop sequence the handlers use: SIGTERM, wait the grace
        // period, SIGKILL if it is still alive — written once, used everywhere a
        // child must be stopped before the row is finalised.
        stopChild($process, $grace);
        $ok = $finalise('cancelled', null);
        exit($ok ? 2 : 1);
    }
    sleep(5);
}

/**
 * isCancelRequested() for a teardown path (signal handler, shutdown function),
 * where a dead Postgres must not turn into an uncaught exception: that would
 * skip finalise() entirely and defeat the very Postgres-bounce case finalise()'s
 * own try/catch exists for. An unrequested stop defaults to not-cancelled, which
 * is also the spec-correct default — an unrequested stop is 'failed', not
 * 'cancelled'.
 */
function wasCancelRequested(SeedJob $jobs, string $uuid): bool
{
    try {
        return $jobs->isCancelRequested($uuid);
    } catch (Throwable) {
        return false;
    }
}

/**
 * Stops the seeding child: SIGTERM, wait up to $grace seconds for it to exit on
 * its own, then SIGKILL if it is still alive. Blocking is fine here — the row is
 * about to be finalised either way, and this is the only place that sends a
 * signal to the child, whether the trigger was a cooperative cancel or this
 * process itself being killed.
 */
function stopChild(&$process, int $grace): void
{
    if (!is_resource($process)) {
        return;
    }
    $status = proc_get_status($process);
    if (!$status['running']) {
        return;
    }
    proc_terminate($process, SIGTERM);
    $deadline = time() + $grace;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) {
            return;
        }
        sleep(1);
    } while (time() < $deadline);
    $status = proc_get_status($process);
    if ($status['running']) {
        proc_terminate($process, SIGKILL);
        for ($i = 0; $i < 20; $i++) {
            if (!proc_get_status($process)['running']) {
                break;
            }
            usleep(100000);
        }
    }
}

/**
 * The last $bytes of the log, so a client sees what the process last said.
 *
 * Reads through a freshly opened handle and sizes it with fseek()/ftell() on
 * that handle rather than filesize($path): filesize() goes through PHP's
 * per-process stat cache, which is never invalidated for a file this process
 * only ever reads and someone else is still writing — a poll can see the size
 * observed several polls ago while the file has grown to hundreds of kilobytes,
 * and the whole thing is returned uncapped. A handle's own ftell() cannot be
 * stale: it reflects the file exactly as this fopen() call just found it.
 */
function tail(string $path, int $bytes): ?string
{
    $fh = @fopen($path, 'r');
    if ($fh === false) {
        return null;
    }
    fseek($fh, 0, SEEK_END);
    $size = ftell($fh);
    if ($size > $bytes) {
        fseek($fh, -$bytes, SEEK_END);
    } else {
        rewind($fh);
    }
    $out = stream_get_contents($fh);
    fclose($fh);
    return $out === false ? null : $out;
}
