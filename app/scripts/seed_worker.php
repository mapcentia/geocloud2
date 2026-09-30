<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2026 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * Cron tick for the tile seeder: reap what died, claim what waits, spawn one
 * seed_run.php per job, return.
 *
 * It spawns rather than runs, because a seed lasts hours while a tick runs under
 * flock -n and must let the next tick in. This is what Job::runJob does for
 * scheduler runs, and deliberately not what snapshot_worker.php does inline — that
 * is right for jobs measured in minutes.
 *
 * The cron line must wrap this script as `flock -n -o <lockfile> php -f
 * seed_worker.php`: the -o (--close) is load-bearing. Without it, flock keeps the
 * lock file descriptor open across exec(), this script's spawned grandchild
 * inherits it (fds survive fork/exec unless closed), and the lock then stays held
 * for as long as that seed runs — up to maxHours — during which every later tick
 * fails flock -n silently: no reaping, no claiming, no pruning, in any database.
 * -o closes the descriptor in the child before it execs php, so nothing forked
 * from this process can hold it. Verified in the dev container: with the run
 * spawned exactly as below, `ls -l /proc/<pid>/fd` shows only 0/1/2, and a second
 * `flock -n -o <lockfile> true` succeeds while that run is still alive.
 *
 * Capacity comes from settings.seed_jobs (`count(*) WHERE status = 'running' AND
 * host = :host`), not from counting processes: pgrep matches both the `timeout`
 * wrapper and its `php` child for one run (double-counting), and fails open to 0
 * — unbounded claiming — the moment pgrep itself can't be read. The database is
 * the same source of truth claimOne() writes and the API already reads.
 *
 * Usage: php seed_worker.php [--database=<db>] [--binary=<path>]
 */

use app\conf\App;
use app\inc\Connection;
use app\inc\Model;
use app\models\Database;
use app\models\SeedJob;

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");
new App();

$options = getopt("", ["database::", "binary::"]);
$only = $options["database"] ?? null;
$binary = $options["binary"] ?? null;

// Same shape seed_run.php itself enforces on the value once it receives it, so a
// crafted --database can't walk the log-retention glob()/@unlink() below into a
// path outside app/tmp/<database>/seed.
if ($only !== null && !preg_match('/^[A-Za-z0-9_\-]+$/', $only)) {
    fwrite(STDERR, "--database contains invalid characters\n");
    exit(1);
}

$maxConcurrent = App::$param['tileseeder']['maxConcurrent'] ?? 1;
$maxHours = App::$param['tileseeder']['maxHours'] ?? 12;
// Whole seconds, not (int)$maxHours . 'h': a fractional setting like 0.5 would
// truncate to 0h, which is no timeout at all rather than the intended 30 minutes.
$timeoutSeconds = (int)round($maxHours * 3600);
$php = PHP_BINARY;
$runner = App::$param['path'] . 'app/scripts/seed_run.php';
$host = SeedJob::currentHost();

// Databases with no settings.seed_jobs table (system catalogs, a fresh database
// without the migration applied yet) are skipped up front rather than left to the
// per-database catch below: same list snapshot_worker.php uses for the same reason.
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template', 'template_geocloud', 'mapcentia', 'gc2scheduler'];

// flock -n guards cron ticks against each other, but a hand-run tick
// (--database= is explicitly meant for that) takes no lock at all, so two ticks
// can both read a count before either claims. The database-backed count narrows
// that window a lot (it's one SELECT, not a whole seed's lifetime) but doesn't
// close it; a real fix would be a lock, which isn't worth adding for a rare
// manual overlap.
$databases = $only ? [$only] : new Database()->listAllDbs()['data'];
foreach ($databases as $database) {
    if (!$only && in_array($database, $skip, true)) {
        continue;
    }
    $connection = new Connection(database: $database);
    try {
        try {
            $jobs = new SeedJob(connection: $connection);
            $reaped = $jobs->reapStale();
            if ($reaped > 0) {
                echo "$database: reaped $reaped stale run(s)\n";
            }
            // Counted after reapStale(): a row the reaper just failed must not
            // still occupy a slot this tick.
            $live = $jobs->countRunningOnHost($host);
            while ($live < $maxConcurrent) {
                $row = $jobs->claimOne();
                if ($row === null) {
                    break;
                }
                $cmd = '/usr/bin/setsid /usr/bin/nohup /usr/bin/timeout -s SIGINT -k 60 ' . $timeoutSeconds . 's '
                    . escapeshellarg($php) . ' ' . escapeshellarg($runner)
                    . ' --database=' . escapeshellarg($database)
                    . ' --uuid=' . escapeshellarg($row['uuid']);
                if ($binary !== null) {
                    $cmd .= ' --binary=' . escapeshellarg($binary);
                }
                // setsid so the run is its own session (a signal to the tick's
                // process group must not reach it); < /dev/null so it never blocks
                // on the tick's stdin, same as Job::runJob's spawn of get.php.
                $out = [];
                exec($cmd . ' < /dev/null > /dev/null 2>&1 & echo $!', $out);
                $pid = (int)trim($out[count($out) - 1] ?? '0');

                // exec() itself always "succeeds" — the sh and the echo run fine
                // even when nohup, timeout or $runner do not, so the pid alone
                // proves nothing. A short settle then a signal-0 probe is the
                // cheapest way to tell a real process from a shell that already
                // exited; posix_kill(pid, 0) sends no signal, only checks it exists.
                usleep(200000);
                if ($pid > 0 && posix_kill($pid, 0)) {
                    $live++;
                    echo "$database: started seed {$row['uuid']} ({$row['tileset']})\n";
                } else {
                    // Left 'running', reapStale() would eventually catch this row
                    // ten minutes later and blame a lost heartbeat for a process
                    // that never existed. Finalise it now with the real cause
                    // instead, and don't count it against this tick's capacity.
                    $jobs->finish($row['uuid'], 'failed', 'could not spawn seed run', null);
                    echo "$database: FAILED to spawn seed {$row['uuid']}: could not spawn seed run\n";
                }
            }
        } catch (Throwable $e) {
            // A database without settings.seed_jobs, or a transient error: the
            // next tick tries again. Best-effort per tick, like the snapshot
            // worker.
        }

        // A separate try: a reap/claim failure above must not also skip pruning
        // for this database.
        try {
            // The tail lives in the row; the full file is for debugging on this node.
            $keepHours = App::$param['tileseeder']['keepLogHours'] ?? 72;
            $logDir = App::$param['path'] . "app/tmp/$database/seed";
            if (is_dir($logDir)) {
                foreach (glob("$logDir/*.log") ?: [] as $file) {
                    if (filemtime($file) < time() - $keepHours * 3600) {
                        @unlink($file);
                    }
                }
            }
        } catch (Throwable $e) {
            // Same best-effort rule: the next tick prunes what this one missed.
        }
    } finally {
        Model::disconnect($connection);
    }
}
