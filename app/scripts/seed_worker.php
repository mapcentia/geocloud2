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

$maxConcurrent = App::$param['tileseeder']['maxConcurrent'] ?? 1;
$maxHours = App::$param['tileseeder']['maxHours'] ?? 12;
$php = PHP_BINARY;
$runner = App::$param['path'] . 'app/scripts/seed_run.php';

// Live children of this node, whatever database they belong to. The pattern is
// written [s]eed_run.php rather than seed_run.php: exec() runs this through a
// shell whose own command line then literally contains the search text, and a
// plain "seed_run.php" pattern matches that wrapping shell too, inflating the
// count by (at least) one even when no run is actually alive. The bracketed
// character is a one-character regex class that still matches a real
// seed_run.php process but never appears verbatim in the invoking command.
exec("pgrep -f '[s]eed_run.php' | wc -l", $out);
$live = (int)trim($out[0] ?? '0');

// Databases with no settings.seed_jobs table (system catalogs, a fresh database
// without the migration applied yet) are skipped up front rather than left to the
// per-database catch below: same list snapshot_worker.php uses for the same reason.
$skip = ['rdsadmin', 'template1', 'template0', 'postgres', 'postgis_template', 'template_geocloud', 'mapcentia', 'gc2scheduler'];

$databases = $only ? [$only] : new Database()->listAllDbs()['data'];
foreach ($databases as $database) {
    if (!$only && in_array($database, $skip, true)) {
        continue;
    }
    $connection = new Connection(database: $database);
    try {
        $jobs = new SeedJob(connection: $connection);
        $reaped = $jobs->reapStale();
        if ($reaped > 0) {
            echo "$database: reaped $reaped stale run(s)\n";
        }
        while ($live < $maxConcurrent) {
            $row = $jobs->claimOne();
            if ($row === null) {
                break;
            }
            $cmd = '/usr/bin/nohup /usr/bin/timeout -s SIGINT -k 60 ' . (int)$maxHours . 'h '
                . escapeshellarg($php) . ' ' . escapeshellarg($runner)
                . ' --database=' . escapeshellarg($database)
                . ' --uuid=' . escapeshellarg($row['uuid']);
            if ($binary !== null) {
                $cmd .= ' --binary=' . escapeshellarg($binary);
            }
            exec($cmd . ' > /dev/null 2>&1 & echo $!');
            $live++;
            echo "$database: started seed {$row['uuid']} ({$row['tileset']})\n";
        }

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
        // A database without settings.seed_jobs, or a transient error: the next
        // tick tries again. Best-effort per tick, like the snapshot worker.
    } finally {
        Model::disconnect($connection);
    }
}
