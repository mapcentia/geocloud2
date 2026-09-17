<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

set_time_limit(0);

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");


use app\conf\App;
use app\conf\Connection;
use app\controllers\Tilecache;
use app\inc\Cache;
use app\inc\SchedulerLock;
use app\inc\Util;
use app\inc\WfsPaging;
use app\models\Database;
use app\models\Layer;
use app\models\Table;

new App();

Cache::setInstance();


$report = [];
$lastError = null;

const DOWNLOADTYPE = "downloadType";
const FEATURECOUNT = "featureCount";
const MAXCELLCOUNT = "maxCellCount";
const DUPSCOUNT = "dupsCount";
const URL = "Url";
const GML = "Grid/GML";
const GMLAS = "Grid/GMLAS";
const FILE = "File";
const ZIP = "Zip";
const SLEEP = "sleep";
$report[SLEEP] = 0;

print "Info: Started at " . date(DATE_RFC822);

// Set path so libjvm.so can be loaded in ogr2ogr for MS Access support
putenv("LD_LIBRARY_PATH=/usr/lib/jvm/java-8-openjdk-amd64/jre/lib/amd64/server");

$longopts = array(
    "db:",
    "schema:",
    "safeName:",
    "url:",
    "srid:",
    "type:",
    "encoding:",
    "jobId:",
    "deleteAppend:",
    "extra:",
    "preSql:",
    "postSql:",
    "downloadSchema:",
    "snapshot:",
    "manual:",
    "name:",
);
$options = getopt("", $longopts);

$db = $options["db"];
$schema = $options["schema"];
$safeName = $options["safeName"];
$url = $options["url"];
$srid = $options["srid"];
$type = $options["type"];
$encoding = $options["encoding"];
$jobId = $options["jobId"];
$deleteAppend = $options["deleteAppend"];
$extra = $options["extra"] == "null" ? null : base64_decode($options["extra"]);
$preSql = $options["preSql"] == "null" ? null : base64_decode($options["preSql"]);
$postSql = $options["postSql"] == "null" ? null : base64_decode($options["postSql"]);
$downloadSchema = $options["downloadSchema"];
$snapshotAfterImport = $options["snapshot"] ?? null;
$manualStart = !empty($options["manual"]);
$runName = !empty($options["name"]) ? (base64_decode($options["name"]) ?: null) : null;

$workingSchema = "_gc2scheduler";

$tmpDir = App::$param['path'] . "app/tmp/";

$conn = new \app\inc\Connection(user: (!empty(App::$param['setUser']) ? $db : Connection::$param["postgisuser"]), database: $db);

// Locking and run registry live in gc2scheduler (Postgres advisory locks),
// on a dedicated session that lasts for the whole run. See app/inc/SchedulerLock.php.
$runHost = gethostname() ?: 'unknown';
$runPid = getmypid();
$schedulerLock = new SchedulerLock();
$schedulerLock->reap();
if (!$schedulerLock->tryJobLock((int)$jobId)) {
    $running = $schedulerLock->runningRun((int)$jobId);
    $reason = "already running" . ($running ? " (run {$running['uuid']}, started {$running['started_at']}, host {$running['host']})" : "");
    $schedulerLock->recordSkipped((int)$jobId, $db, $runName ?? $safeName, $runPid, $runHost, $reason);
    print "\nInfo: Job {$jobId} is {$reason}. Exiting.";
    exit(0);
}
$runUuid = null; // set right after the job lock, below

// Cooldown: a job that ran less than gc2scheduler.minInterval seconds ago is
// skipped (users forget the cron fields and get a job every minute). Manual
// starts (UI "run now", API) bypass it; the caller asked for this run.
$minInterval = (int)(App::$param['gc2scheduler']['minInterval'] ?? 0);
if ($minInterval > 0) {
    $last = $schedulerLock->latestRun((int)$jobId);
    if ($last !== null) {
        $ago = time() - strtotime($last['started_at']);
        if ($ago < $minInterval) {
            if ($manualStart) {
                print "\nInfo: Cooldown bypassed (manual start).";
            } else {
                $reason = "cooldown: last run started {$last['started_at']}, {$ago} s ago, minimum {$minInterval} s";
                $schedulerLock->recordSkipped((int)$jobId, $db, $runName ?? $safeName, $runPid, $runHost, $reason);
                print "\nInfo: Job {$jobId} skipped: {$reason}. Exiting.";
                exit(0);
            }
        }
    }
}

// The run is registered as soon as the job lock is held, before the (possibly
// hour-long) wait for a run slot: a lock-holding run must never be invisible
// to the API. The slot is filled in by assignSlot() once one is acquired.
$runUuid = $schedulerLock->startRun((int)$jobId, $db, $runName ?? $safeName, $runPid, null, $runHost);
print "\nInfo: Run {$runUuid} registered";

// Bookkeeping when the process dies without reaching cleanUp(): the locks
// are released by Postgres regardless; this only keeps the registry honest.
register_shutdown_function(function () use (&$schedulerLock, &$runUuid) {
    if ($runUuid === null) {
        return;
    }
    $err = error_get_last();
    $reason = $err !== null ? "terminated: " . $err['message'] : "terminated";
    try {
        $schedulerLock->finishRun($runUuid, 'failed', $reason); // no-op if cleanUp() already finalised
    } catch (Throwable) {
    }
});
if (function_exists('pcntl_async_signals')) {
    pcntl_async_signals(true);
    pcntl_signal(SIGINT, function () use (&$schedulerLock, &$runUuid) {
        print "\nError: Terminated by SIGINT (timeout).";
        if ($runUuid !== null) {
            try {
                $schedulerLock->finishRun($runUuid, 'failed', 'timeout');
            } catch (Throwable) {
            }
        }
        exit(130);
    });
    pcntl_signal(SIGTERM, function () use (&$schedulerLock, &$runUuid) {
        if ($runUuid !== null) {
            try {
                $schedulerLock->finishRun($runUuid, 'failed', 'terminated');
            } catch (Throwable) {
            }
        }
        exit(143);
    });
}

$getFunction = null;
$contentIsCsv = false;
$contentIsJson = false;

// Check if Paging should be used
if (sizeof(explode("|http", $url)) > 1) {
    $grid = explode("|", $url)[0];
    $url = explode("|", $url)[1];
    if (sizeof(explode(",", $grid)) > 1) {
        $id = explode(",", $grid)[1];
        $grid = explode(",", $grid)[0];
    } else {
        $id = null;
    }
    $getFunction = "getCmdPaging"; // Paging by grid
} else {
    if (strtolower(explode(':', $url)[0]) == 'json') {
        print "\nInfo: Explicit set to JSON...";
        $contentIsJson = true;
        $getFunction = "getCmd";
        $url = substr($url, 5); // Strip json:
    }
    $grid = null;
    // A plain WFS 2.0.0 GetFeature URL is paged with startIndex/count (and
    // sortBy when it can be determined). Grid ("|") jobs and WFS 1.x keep
    // their existing paths; an explicit startIndex means the caller pages.
    $wfsPaging = null;
    if (!$getFunction && ($wfsPaging = WfsPaging::detect($url)) !== null) {
        print "\nInfo: WFS 2.0.0 GetFeature detected. Using startIndex/count paging (count={$wfsPaging->pageSize}).";
        $getFunction = "getCmdWfsPaging";
    }
    if ($getFunction !== "getCmdWfsPaging") {
        // Check if Content type is zip
        // An explicit timeout: without one this blocks on the default
        // default_socket_timeout while holding the job lock. false (failed
        // HEAD/GET) is not fatal -- the extension check below still decides.
        $ctx = stream_context_create(['http' => ['timeout' => 30]]);
        $headers = get_headers($url, false, $ctx) ?: [];
        print "\n\nheaders\n";
        foreach ($headers as $header) {
            if ($header == "Content-Type: application/zip") {
                $getFunction = "getCmdZip";
            }
            if (str_contains($header, "text/csv")) {
                $contentIsCsv = true;
                $getFunction = "getCmd";
            }
            print " $header\n";
        }
    }
    // Check file extension if getFunction still not is set
    if (!$getFunction) {
        $extCheck1 = explode(".", $url);
        $extCheck2 = array_reverse($extCheck1);
        if (strtolower($extCheck2[0]) == "shp" || strtolower($extCheck2[0]) == "tab" || strtolower($extCheck2[0]) == "geojson") {
            $getFunction = "getCmdFile"; // Shape or TAB file set
        } elseif (strtolower($extCheck2[0]) == "zip" || strtolower($extCheck2[0]) == "rar" || strtolower($extCheck2[0]) == "gz") {
            $getFunction = "getCmdZip"; // Zip or rar file
        } else {
            $getFunction = "getCmd"; // Service or single file
        }
    }
}

$dir = App::$param['path'] . "app/tmp/" . $db . "/__vectors";
$tempFile = md5(microtime() . rand());
$randTableName = "_" . time() . "_table_" . md5(microtime() . rand());
$err = null;
$out = null;

if (!file_exists(App::$param['path'] . "app/tmp/" . $db)) {
    @mkdir(App::$param['path'] . "app/tmp/" . $db);
}

if (!file_exists($dir)) {
    @mkdir($dir);
}

if (is_numeric($safeName[0])) {
    $safeName = "_" . $safeName;
}
function isCsv($filePath): bool
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        return false;
    }
    $handle = fopen($filePath, 'r');
    if ($handle === false) {
        return false;
    }
    $rowCount = 0;
    $delimiter = ',';
    $expectedFields = null;
    while (($line = fgets($handle)) !== false && $rowCount < 5) {
        $line = trim($line);
        if (empty($line)) {
            continue;
        }
        if ($rowCount == 0) {
            if (str_contains($line, ',')) {
                $delimiter = ',';
            } elseif (str_contains($line, ';')) {
                $delimiter = ';';
            } elseif (str_contains($line, "\t")) {
                $delimiter = "\t";
            } else {
                fclose($handle);
                return false;
            }
        }
        $fields = str_getcsv($line, $delimiter);
        if ($expectedFields === null) {
            $expectedFields = count($fields);
        } elseif (count($fields) !== $expectedFields) {
            fclose($handle);
            return false;
        }
        $rowCount++;
    }
    fclose($handle);
    return $rowCount > 1;
}

/**
 * @return string
 */
function which(): string
{
    return "/usr/local/bin/ogr2ogr";
}

/**
 * Build an ogr2ogr shell command with common flags and escapeshellarg.
 *
 * @param string $encoding PGCLIENTENCODING value
 * @param string $srid EPSG code
 * @param string $db Database name
 * @param string $workingSchema Target schema
 * @param string $randTableName Target table
 * @param string $inputPath Source file/path (already escaped if needed)
 * @param string $mode "-overwrite" or "-append"
 * @param string $type Geometry type (or "AUTO" to omit -nlt)
 * @param bool $preserveFid Whether to add -preserve_fid
 * @param array $extraArgs Additional arguments (already escaped)
 * @return string
 */
function buildOgr2ogrCmd(
    string $encoding,
    string $srid,
    string $db,
    string $workingSchema,
    string $randTableName,
    string $inputPath,
    string $mode = '-overwrite',
    string $type = 'AUTO',
    bool   $preserveFid = false,
    array  $extraArgs = [],
): string
{
    $pgConn = "host=" . Connection::$param["postgishost"]
        . " port=" . Connection::$param["postgisport"]
        . " user=" . (!empty(App::$param['setUser']) ? $db : Connection::$param["postgisuser"])
        . " password=" . Connection::$param["postgispw"]
        . " dbname=" . $db;

    $parts = [
        "env PGCLIENTENCODING=" . escapeshellarg($encoding),
        which(),
        $mode,
        "-dim 2",
        "-lco " . escapeshellarg("GEOMETRY_NAME=the_geom"),
        "-lco " . escapeshellarg("FID=gid"),
        "-lco " . escapeshellarg("PRECISION=NO"),
        "-a_srs " . escapeshellarg("EPSG:$srid"),
        "-f " . escapeshellarg("PostgreSQL"),
        "PG:" . escapeshellarg($pgConn),
    ];

    if ($preserveFid) {
        $parts[] = "-preserve_fid";
    }

    foreach ($extraArgs as $arg) {
        $parts[] = $arg;
    }

    $parts[] = $inputPath;
    $parts[] = "-nln " . escapeshellarg("$workingSchema.$randTableName");

    if ($type !== "AUTO") {
        $parts[] = "-nlt " . escapeshellarg($type);
    }

    return implode(" ", $parts);
}

/**
 *
 */
function getCmd(): void
{
    global $encoding, $srid, $dir, $tempFile, $type, $db, $workingSchema, $randTableName, $downloadSchema, $url, $report, $out, $err, $contentIsCsv, $contentIsJson;
    global $schedulerLock, $runUuid, $lastError;
    if ($runUuid !== null) {
        $schedulerLock->heartbeat($runUuid);
    }

    $report[DOWNLOADTYPE] = URL;
    $tmpFilePath = $dir . "/" . $tempFile;

    print "\nInfo: Fetching remote data...";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $fp = fopen($tmpFilePath, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
    }
    curl_close($ch);
    fclose($fp);
    if (isset($error_msg)) {
        print "\n" . $error_msg;
        $lastError = $error_msg;
        cleanUp();
        exit(1);
    }

    if ($contentIsJson) {
        print "\nInfo: Converting JSON to CSV...";
        $csvFile = $dir . "/" . $tempFile . ".csv";
        Util::json2cvs($tmpFilePath, $csvFile);
        $tmpFilePath = $csvFile;
    }

    // We test for CSV data, because ogr2ogr can't detect this format
    $isCsv = $contentIsCsv || isCsv($tmpFilePath);

    print "\nInfo: Staring inserting in temp table using ogr2ogr...";

    $extraArgs = [
        "-oo " . escapeshellarg("DOWNLOAD_SCHEMA=" . ($downloadSchema && !$contentIsCsv ? "YES" : "NO")),
    ];
    if ($isCsv) {
        $extraArgs[] = "-oo " . escapeshellarg("X_POSSIBLE_NAMES=lon*,Lon*,x,X");
        $extraArgs[] = "-oo " . escapeshellarg("Y_POSSIBLE_NAMES=lat*,Lat*,y,Y");
        $extraArgs[] = "-oo " . escapeshellarg("AUTODETECT_TYPE=YES");
        $extraArgs[] = "-oo " . escapeshellarg("GEOM_POSSIBLE_NAMES=geometri");
    }

    $source = $isCsv ? escapeshellarg("CSV:" . $tmpFilePath) : escapeshellarg($tmpFilePath);

    $cmd = buildOgr2ogrCmd(
        encoding: $encoding,
        srid: $srid,
        db: $db,
        workingSchema: $workingSchema,
        randTableName: $randTableName,
        inputPath: $source,
        type: $type,
        extraArgs: $extraArgs,
    );
    exec($cmd . ' 2>&1', $out, $err);
}

/**
 *
 */
function getCmdPaging(): void
{
    global $randTableName, $type, $db, $workingSchema, $url, $grid, $id, $encoding, $downloadSchema, $table, $pass, $cellTemps, $report, $numberOfFeatures, $srid;

    $downloadSchema ? $report[DOWNLOADTYPE] = GMLAS : $report[DOWNLOADTYPE] = GML;

    print "\nInfo: Start paged download...";

    $pass = true;
    $sql = "SELECT gid,ST_XMIN(st_fishnet), ST_YMIN(st_fishnet), ST_XMAX(st_fishnet), ST_YMAX(st_fishnet) FROM $grid GROUP BY gid, st_xmin, st_ymin, st_xmax, st_ymax ORDER BY gid";
    $res = $table->execQuery($sql);
    $cellTemps = [];

    print "\n";
    $cellNumber = 1;
    print "\nProcessing cell ";
    while ($row = $table->fetchRow($res)) {
        global $count;
        $count = 1;
        print $cellNumber . ' ';
        $cellNumber++;
        $bbox = "{$row["st_xmin"]},{$row["st_ymin"]},{$row["st_xmax"]},{$row["st_ymax"]},EPSG:{$srid}";
        fetchPart("cell-" . $row["gid"], $url . "&BBOX=" . $bbox);
    }
    print "\n";

    finalizePagedTables();
}

function fetchPart(string $label, string $requestUrl): array
{
    global $pass, $count, $cellNumber, $table, $cellTemps, $id, $numberOfFeatures, $out, $err, $tmpDir,
           $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $srid;
    $out = [];
    global $schedulerLock, $runUuid;
    if ($runUuid !== null) {
        $schedulerLock->heartbeat($runUuid);
    }
    $pass = true; // each attempt starts clean so a successful retry counts
    $counts = ['matched' => null, 'returned' => null];
    $gmlName = $randTableName . "-" . $label . ".gml";

    $cellTemp = "_" . time() . "_cell_" . md5(microtime() . rand());

    print ("\n$requestUrl\n");
    if (!file_put_contents($tmpDir . $gmlName, Util::wget($requestUrl))) {
        print "\nError: could not get GML for {$label}";
        $pass = false;
    } else {
        // numberMatched/numberReturned sit on the FeatureCollection root; WFS paging needs them
        $counts = WfsPaging::parseCounts((string)file_get_contents($tmpDir . $gmlName, false, null, 0, 8192));
    }

    $gmlPath = $tmpDir . $gmlName;

    // SRS normalizer
    $logNormalizeCount = true;

    $perlExpr = $logNormalizeCount ? <<<'PERL'
        BEGIN { $c = 0; }
        $c += s{
          (srsName=")
          (?:
            https?://www\.opengis\.net/gml/srs/epsg\.xml\#(\d+)
          | https?://www\.opengis\.net/def/crs/EPSG/0/(\d+)/?
          | urn:ogc:def:crs:EPSG::(\d+)
          )
          (")
        }{
          $1 . "EPSG:" . ($2 // $3 // $4) . $5
        }gex;
        END { print STDERR "SRS normalized: $c\n"; }
        PERL
        : <<<'PERL'
        $c += s{
          (srsName=")
          (?:
            https?://www\.opengis\.net/gml/srs/epsg\.xml\#(\d+)
          | https?://www\.opengis\.net/def/crs/EPSG/0/(\d+)/?
          | urn:ogc:def:crs:EPSG::(\d+)
          )
          (")
        }{
          $1 . "EPSG:" . ($2 // $3 // $4) . $5
        }gex;
        PERL;

    // Normalize SRS in-place
    $normalizeCmd = "perl -i -0777 -pe " . escapeshellarg($perlExpr) . " " . escapeshellarg($gmlPath);
    exec($normalizeCmd . ' 2>&1');

    // Build final cmd
    if ($downloadSchema) {
        $extraArgs = [
            "-oo " . escapeshellarg("CONFIG_FILE=" . App::$param['path'] . "app/scripts/gmlasconf.xml"),
        ];
        $cmd = buildOgr2ogrCmd(
            encoding: $encoding,
            srid: $srid,
            db: $db,
            workingSchema: $workingSchema,
            randTableName: $cellTemp,
            inputPath: "GMLAS:" . escapeshellarg($gmlPath),
            type: $type,
            preserveFid: true,
            extraArgs: $extraArgs,
        );
    } else {
        $cmd = buildOgr2ogrCmd(
            encoding: $encoding,
            srid: $srid,
            db: $db,
            workingSchema: $workingSchema,
            randTableName: $cellTemp,
            inputPath: escapeshellarg($gmlPath),
            type: $type,
            preserveFid: true,
        );
    }

    print ("\n$cmd\n");
    exec($cmd . ' 2>&1', $out, $err);
    if ($err) {
        $pass = false;
    }

    // The GMLAS driver sometimes throws a 404 error, so we can't stop on this kind of error
    foreach ($out as $line) {
        if (str_contains($line, "FAILURE") || (str_contains($line, "ERROR") && $line != "ERROR 1: HTTP error code : 404")) {
            $pass = false;
            break;
        }
    }

    if (!$pass) {
        if ($count > 2) {
            print "\nError: Too many recursive tries to fetch {$label}";
            cleanUp();
            exit(1);
        }
        $count++;
        sleep(5 * $count); // We increase the wait for each try
        $retried = fetchPart($label, $requestUrl);
        if ($pass) {
            return $retried; // the retry succeeded
        }
        foreach ($out as $line) {
            print "\n" . $line;
        }
        print "\nRequest: " . $requestUrl;
        print "\nInfo: Outputting the first few lines of the file:";
        $handle = @fopen($tmpDir . $gmlName, "r");
        if ($handle) {
            for ($i = 0; $i < 40; $i++) {
                $buffer = fgets($handle, 4096);
                print $buffer;
            }
            if (!feof($handle)) {
                print "\nError: unexpected fgets() fail.";
            }
            fclose($handle);
        }
        @unlink($tmpDir . $gmlName);
        cleanUp();
        exit(1);
    }

    @unlink($tmpDir . $gmlName);

    $checkSql = "SELECT EXISTS (
       SELECT FROM pg_catalog.pg_class c
       JOIN   pg_catalog.pg_namespace n ON n.oid = c.relnamespace
       WHERE  n.nspname = '{$workingSchema}'
       AND    c.relname = '{$cellTemp}'
       AND    c.relkind = 'r'    -- only tables
       ) AS exists";
    $checkRes = $table->execQuery($checkSql);
    if ($table->fetchRow($checkRes)["exists"]) {
        $sql = "SELECT count(*) AS number FROM {$workingSchema}.{$cellTemp}";
        try {
            $res = $table->prepare($sql);
            $res->execute();
            $numberOfFeatures[] = $table->fetchRow($res)["number"];
            $cellTemps[] = $cellTemp;
        } catch (PDOException $e) {
            $numberOfFeatures[] = 0;
        }
    } else {
        $numberOfFeatures[] = 0;
    }
    return $counts;
}
/**
 * Unions the per-cell/per-page temp tables into the job table, resolves the
 * identifier, removes duplicates and re-sequences gid. Shared by the grid
 * (bbox) paging and the WFS startIndex/count paging.
 */
function finalizePagedTables(): void
{
    global $table, $workingSchema, $randTableName, $cellTemps, $report, $id, $numberOfFeatures, $lastError;

    $selects = [];
    $drops = [];
    $fields = [];
    $gotFields = false;
    foreach ($cellTemps as $t) {
        if (!$gotFields) {
            foreach ($table->getMetaData("$workingSchema.$t", false, false, null, null, false, false) as $k => $v) {
                if (
                    array_reverse(explode("_", $k))[0] != "nil" &&
                    $k != "description_href" &&
                    $k != "description_title" &&
                    $k != "description_nilreason" &&
                    $k != "description" &&
                    $k != "descriptionreference_href" &&
                    $k != "descriptionreference_title" &&
                    $k != "descriptionreference_nilreason" &&
                    $k != "identifier_codespace" &&
                    $k != "identifier" &&
                    $k != "location_location_pkid"
                ) {
                    $fields[] = $k;
                }
            }
            if (sizeof($fields) > 0) {
                $gotFields = true; // Don't read fields again
            }
        }
        $selects[] = "SELECT \"" . implode("\",\"", $fields) . "\" FROM {$workingSchema}.{$t}";
        $drops[] = "DROP TABLE {$workingSchema}.{$t}";
    }

    // Create UNION table
    if (sizeof($selects) == 0) {
        print "\nNotice: No data for the area.";
        $report[FEATURECOUNT] = 0;
        cleanUp(1);
        exit(0);
    }

    $sql = "CREATE TABLE $workingSchema.$randTableName AS " . implode("\nUNION ALL\n", $selects);
    $res = $table->prepare($sql);
    try {
        $table->execute($res);
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        cleanUp();
        exit(1);
    } finally {
        // Clean cell tmp tables
        foreach ($drops as $d) {
            $res = $table->prepare($d);
            try {
                $table->execute($res);
            } catch (PDOException $e) {
                print "Warning: ";
                print_r($e->getMessage());
            }
        }
    }

    // If source has an "id" fields and identifier is gml:id, it will be mapped to id2 by GMLAS driver
    // We try to rename id2 to id and drop id1
    $tmpTableName = $workingSchema . ".". $randTableName;
    if ($table->doesColumnExist($tmpTableName, 'id2')['exists']) {
        $sql = "ALTER TABLE $tmpTableName RENAME id2 TO id";
        $res = $table->prepare($sql);
        try {
            $table->execute($res);
        } catch (PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
            $lastError = $e->getMessage();
            cleanUp();
            exit(1);
        }
    } else {
        print "\nNotice: Could not rename id2 to id. Source may not has an 'id' field.";
    }
    if ($table->doesColumnExist($tmpTableName, 'id1')['exists']) {
        $sql = "ALTER TABLE $tmpTableName DROP id1";
        $res = $table->prepare($sql);
        try {
            $table->execute($res);
        } catch (PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
            $lastError = $e->getMessage();
            cleanUp();
            exit(1);
        }
    } else {
        print "\nNotice: Could not drop id1. Source may not has an 'id' field.";
    }

    if (!$id) {
        $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$workingSchema}' AND table_name='{$randTableName}' and column_name='gml_id'";
        $res = $table->prepare($sql);
        try {
            $res->execute();
            $row = $table->fetchRow($res);
            if ($row) {
                $id = "gml_id";
            } else {
                $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$workingSchema}' AND table_name='{$randTableName}' and column_name='id'";
                $res = $table->prepare($sql);
                try {
                    $res->execute();
                    $row = $table->fetchRow($res);
                    if ($row) {
                        $id = "id";
                    } else {
                        $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$workingSchema}' AND table_name='{$randTableName}' and column_name='fid'";
                        $res = $table->prepare($sql);
                        try {
                            $res->execute();
                            $row = $table->fetchRow($res);
                            if ($row) {
                                $id = "fid";
                            } else {
                                print "\nError: Could not find id or fid field. Please set identifier name in URL";
                                cleanUp();
                                exit(1);
                            }
                        } catch (PDOException $e) {
                            print "Error: ";
                            print_r($e->getMessage());
                            $lastError = $e->getMessage();
                            cleanUp();
                            exit(1);
                        }
                    }
                } catch (PDOException $e) {
                    print "Error: ";
                    print_r($e->getMessage());
                    $lastError = $e->getMessage();
                    cleanUp();
                    exit(1);
                }
            }
        } catch (PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
            $lastError = $e->getMessage();
            cleanUp();
            exit(1);
        }
    }

    print "\nInfo: Identifier set to: {$id}";

    // Count dups
    $sql = "SELECT count(*) as num FROM (
              SELECT {$id},count(*) as num
              FROM {$workingSchema}.{$randTableName}
              GROUP BY {$id} HAVING COUNT(*) > 1
          ) AS foo";

    $res = $table->prepare($sql);
    try {
        $res->execute();
        $row = $table->fetchRow($res);
        if ($row["num"] > 0) {
            print "\nInfo: Removed " . $row["num"] . " duplicates.";
            $report[DUPSCOUNT] = $row["num"];
        } else {
            print "\nNotice: Removed no duplicates.";
            $report[DUPSCOUNT] = 0;
        }
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }

    // Remove dups. Default to ogr_pkid as unique field
    $sql = "DELETE FROM {$workingSchema}.{$randTableName} a USING (
      SELECT MIN(ctid) as ctid, {$id}
        FROM {$workingSchema}.{$randTableName} 
        GROUP BY {$id} HAVING COUNT(*) > 1
      ) b
      WHERE a.{$id} = b.{$id} 
      AND a.ctid <> b.ctid";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }

    // Create a dummy gml_id field
    // Support of legacy destination tables with gml_id field
    $sql = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$workingSchema}' AND table_name='{$randTableName}' and column_name='gml_id'";
    $res = $table->prepare($sql);
    try {
        $res->execute();
        $row = $table->fetchRow($res);
        if ($row) {
            print "\nNotice: gml_id field already exist.";
        } else {
            $sql = "ALTER TABLE {$workingSchema}.{$randTableName} ADD gml_id INT";
            $res = $table->prepare($sql);
            try {
                $res->execute();
                print "\nNotice: Dummy gml_id field created.";
            } catch (PDOException $e) {
                print "\nWarning: Could not create a dummy gml_id field.";
            }
        }
    } catch (PDOException $e) {
        print "\nWarning: Could not detect gml_id field.";
    }

    // Alter gid so it becomes unique
    $sql = "CREATE TEMPORARY SEQUENCE gid_seq";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} ALTER gid SET DEFAULT nextval('gid_seq')";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }
    $sql = "UPDATE {$workingSchema}.{$randTableName} SET gid=DEFAULT";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }

    // Drop ogr_pkid
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} DROP COLUMN IF EXISTS ogr_pkid";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }

    arsort($numberOfFeatures);
    print "\nInfo: Highest number of features in cell: " . array_values($numberOfFeatures)[0] . (" (#" . ((int)array_keys($numberOfFeatures)[0] + 1)) . ")";
    $report[MAXCELLCOUNT] = array_values($numberOfFeatures)[0];
}

/**
 * WFS 2.0.0 paging: fetches the job URL page by page with startIndex/count
 * (and sortBy when the DescribeFeatureType response has an id-like
 * property), loads each page like a grid cell, then unions the pages.
 */
function getCmdWfsPaging(): void
{
    global $wfsPaging, $report, $downloadSchema, $pass, $cellTemps, $numberOfFeatures, $count, $cellNumber;

    $downloadSchema ? $report[DOWNLOADTYPE] = GMLAS : $report[DOWNLOADTYPE] = GML;

    print "\nInfo: Start WFS paged download (count={$wfsPaging->pageSize})...";

    if ($wfsPaging->sortBy !== null) {
        print "\nInfo: sortBy from URL: {$wfsPaging->sortBy}";
    } else {
        $property = null;
        try {
            print "\n{$wfsPaging->describeFeatureTypeUrl()}\n";
            $xsd = Util::wget($wfsPaging->describeFeatureTypeUrl(), 10, 120);
            $property = is_string($xsd) ? WfsPaging::pickSortProperty($xsd) : null;
        } catch (Throwable $e) {
            print "\nWarning: DescribeFeatureType failed: " . $e->getMessage();
        }
        if ($property !== null) {
            $wfsPaging = $wfsPaging->withSortBy($property);
            print "\nInfo: sortBy set to {$property} (from DescribeFeatureType)";
        } else {
            print "\nWarning: Could not determine a sortBy property. Paging without sortBy; the server must page in a stable order.";
        }
    }

    $pass = true;
    $cellTemps = [];
    $numberOfFeatures = [];
    $startIndex = 0;
    $page = 1;
    print "\nProcessing page ";
    while (true) {
        $count = 1;
        $cellNumber = $page;
        print $page . ' ';
        $counts = fetchPart("page-" . $page, $wfsPaging->pageUrl($startIndex));
        if ($counts['returned'] === null) {
            print "\nWarning: numberReturned missing on page {$page}; assuming it was the last page.";
        }
        if (WfsPaging::isLastPage($startIndex, $counts['returned'], $counts['matched'], $wfsPaging->pageSize)) {
            break;
        }
        $startIndex += $counts['returned'];
        $page++;
    }
    print "\nInfo: Fetched {$page} page(s)" . ($counts['matched'] !== null ? " of {$counts['matched']} matched features" : "") . ".";

    finalizePagedTables();
}

function getCmdFile(): void
{
    global $randTableName, $type, $db, $workingSchema, $url, $encoding, $srid, $report, $out, $err, $dir;
    global $schedulerLock, $runUuid;
    if ($runUuid !== null) {
        $schedulerLock->heartbeat($runUuid);
    }

    $report[DOWNLOADTYPE] = FILE;

    print "\nInfo: Staring inserting in temp table using file download...";

    $randFileName = "_" . md5(microtime() . rand());
    $files = [];
    $out = [];

    // Check if file extension
    // =======================
    $extCheck1 = explode(".", $url);
    $extCheck2 = array_reverse($extCheck1);
    $extension = $extCheck2[0];

    array_shift($extCheck2);
    $base = implode(".", array_reverse($extCheck2));

    switch (strtolower($extension)) {
        case "shp":
            $files[$randFileName . ".shp"] = $url;
            $files[$randFileName . ".SHP"] = $url;
            $files[$randFileName . ".Shp"] = $url;

            // Try to get both upper and lower case extension
            $files[$randFileName . ".dbf"] = $base . ".dbf";
            $files[$randFileName . ".DBF"] = $base . ".DBF";
            $files[$randFileName . ".Dbf"] = $base . ".Dbf";

            $files[$randFileName . ".shx"] = $base . ".shx";
            $files[$randFileName . ".SHX"] = $base . ".SHX";
            $files[$randFileName . ".Shx"] = $base . ".Shx";

            $fileSetName = $randFileName . "." . $extension;
            break;

        case "tab":
            $files[$randFileName . ".tab"] = $url;
            $files[$randFileName . ".TAB"] = $url;
            $files[$randFileName . ".Tab"] = $url;

            // Try to get both upper and lower case extension
            $files[$randFileName . ".map"] = $base . ".map";
            $files[$randFileName . ".MAP"] = $base . ".MAP";
            $files[$randFileName . ".Map"] = $base . ".Map";

            $files[$randFileName . ".dat"] = $base . ".dat";
            $files[$randFileName . ".DAT"] = $base . ".DAT";
            $files[$randFileName . ".Dat"] = $base . ".Dat";

            $files[$randFileName . ".id"] = $base . ".id";
            $files[$randFileName . ".ID"] = $base . ".ID";
            $files[$randFileName . ".Id"] = $base . ".Id";

            $files[$randFileName . ".ind"] = $base . ".ind";
            $files[$randFileName . ".IND"] = $base . ".IND";
            $files[$randFileName . ".Ind"] = $base . ".Ind";

            $fileSetName = $randFileName . "." . $extension;
            break;

        default:
            $files[$randFileName . ".general"] = $url;
            $fileSetName = $randFileName . ".general";
            break;
    }

    foreach ($files as $key => $file) {
        $path = $dir . "/" . $key;
        $fileRes = fopen($path, 'w');
        try {
            file_put_contents($path, Util::wget($file));
        } catch (Exception $e) {
            print "Error: ";
            print $file . "   ";
            // Delete files with errors
            @unlink($path);
            print "\n" . $e->getMessage();
            cleanUp();
            exit(1);
        }
    }

    $cmd = buildOgr2ogrCmd(
        encoding: $encoding,
        srid: $srid,
        db: $db,
        workingSchema: $workingSchema,
        randTableName: $randTableName,
        inputPath: escapeshellarg($dir . "/" . $fileSetName),
        mode: '-append',
        type: $type,
    );
    exec($cmd . ' 2>&1', $out, $err);

    array_map('unlink', glob($dir . "/" . $randFileName . ".*"));
}

function getCmdZip(): void
{
    global $extCheck2, $dir, $url, $tempFile, $encoding, $srid, $type, $db, $workingSchema, $randTableName, $downloadSchema, $outFileName, $report, $out, $err;
    global $schedulerLock, $runUuid, $lastError;
    if ($runUuid !== null) {
        $schedulerLock->heartbeat($runUuid);
    }

    $report[DOWNLOADTYPE] = ZIP;

    print "\nInfo: Fetching remote zip...";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $fp = fopen($dir . "/" . $tempFile . "." . $extCheck2[0], 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
    }
    curl_close($ch);
    fclose($fp);
    if (isset($error_msg)) {
        print "\n" . $error_msg;
        $lastError = $error_msg;
        cleanUp();
        exit(1);
    }
    $ext = array("shp", "tab", "geojson", "gml", "kml", "mif", "gdb", "csv", "json", "gpkg");

    // ZIP start
    // =========
    if (!strtolower($extCheck2[0]) == "gz") {
        $zip = new ZipArchive;
        $res = $zip->open($dir . "/" . $tempFile . "." . $extCheck2[0]);
        if ($res === false) {
            print "Error: Could not unzip file";
            cleanUp();
            exit(1);
        }
        $zip->extractTo($dir . "/" . $tempFile);
        $zip->close();
        unlink($dir . "/" . $tempFile . "." . $extCheck2[0]);
    }

    // GZIP start
    // ==========
    else {
        $bufferSize = 4096; // read 4kb at a time
        mkdir($dir . "/" . $tempFile);
        $outFileName = str_replace('.gz', '', $dir . "/" . $tempFile . "/" . $tempFile . "." . $extCheck2[0]);

        $file = gzopen($dir . "/" . $tempFile . "." . $extCheck2[0], 'rb');

        if (!$file) {
            print "Error: Could not gunzip file";
            cleanUp();
            exit(1);
        }

        $outFile = fopen($outFileName, 'wb');

        while (!gzeof($file)) {
            fwrite($outFile, gzread($file, $bufferSize));
        }

        fclose($outFile);
        gzclose($file);
    }

    $it = new RecursiveDirectoryIterator($dir . "/" . $tempFile);
    foreach (new RecursiveIteratorIterator($it) as $f) {
        $files = explode('.', $f);
        if (in_array(strtolower(array_pop($files)), $ext)) {
            $outFileName = $f->getPathName();
            break;
        }

    }
    $isCsv = false;
    if (array_reverse(explode('.', $outFileName))[0] == "json") {
        $csvFile = $outFileName . ".csv";
        Util::json2cvs($outFileName, $csvFile);
        $outFileName = $csvFile;
        $isCsv = true;
    }

    $extraArgs = [
        "-oo " . escapeshellarg("DOWNLOAD_SCHEMA=" . ($downloadSchema ? "YES" : "NO")),
    ];
    if ($isCsv) {
        $extraArgs[] = "-oo " . escapeshellarg("X_POSSIBLE_NAMES=lon*,Lon*,x,X");
        $extraArgs[] = "-oo " . escapeshellarg("Y_POSSIBLE_NAMES=lat*,Lat*,y,Y");
        $extraArgs[] = "-oo " . escapeshellarg("GEOM_POSSIBLE_NAMES=geometri");
    }

    $cmd = buildOgr2ogrCmd(
        encoding: $encoding,
        srid: $srid,
        db: $db,
        workingSchema: $workingSchema,
        randTableName: $randTableName,
        inputPath: escapeshellarg($outFileName),
        type: $type,
        extraArgs: $extraArgs,
    );
    exec($cmd . ' 2>&1', $out, $err);
}

$table = new Table(table: $schema . "." . $safeName, connection: $conn);

// The working schema must be committed before the job's transaction starts:
// ogr2ogr loads into it over its own connection and cannot see an
// uncommitted CREATE SCHEMA (a database that never ran a job would fail
// every import, and the paged downloads would retry for ~40 minutes first).
$sql = "CREATE SCHEMA IF NOT EXISTS {$workingSchema}";
$res = $table->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print "Error: ";
    print_r($e->getMessage());
    $lastError = $e->getMessage();
    cleanUp();
    exit(1);
}

// Wait for a run slot, register the run, then download
// ======================================================
// Done before the transaction begins, so the customer database never sits
// idle-in-transaction for however long the wait takes (up to hours).
$maxJobs = (int)(App::$param['gc2scheduler']['maxJobs'] ?? SchedulerLock::DEFAULT_MAX_JOBS);
$slot = $schedulerLock->acquireSlot($maxJobs, function (int $max, int $sleep) use (&$report, $schedulerLock, &$runUuid) {
    print "\nInfo: All {$max} run slots are busy. Waiting {$sleep} seconds...";
    $report[SLEEP] += $sleep;
    // The run is already registered (right after the job lock), so keep it
    // from looking stale while it queues.
    $schedulerLock->heartbeat($runUuid);
});
$schedulerLock->assignSlot($runUuid, $slot);
print "\nInfo: Run {$runUuid} registered on slot {$slot}";

// Begin transaction
// =================
$table->begin();
$table->execQuery("SET LOCAL statement_timeout = '24h'");

$getFunction();

// Check output
// ============
if ($err) {
    print "\nError " . $err;
    print_r($out);
    $lastError = "ogr2ogr failed (exit {$err}): " . implode(" | ", $out);
    // Output the first few lines of file
    if ($grid == null) {
        print "\nInfo: Outputting the first few lines of the file:";
        $handle = @fopen($dir . "/" . $tempFile, "r");
        if ($handle) {
            for ($i = 0; $i < 40; $i++) {
                $buffer = fgets($handle, 4096);
                print $buffer;
            }
            if (!feof($handle)) {
                print "\nError: unexpected fgets() fail.";
            }
            fclose($handle);
        }
    }
    cleanUp();
    exit(1);

} else {
    foreach ($out as $line) {
        if (strpos($line, "FAILURE") !== false || (strpos($line, "ERROR") !== false && $line != "ERROR 1: HTTP error code : 404")) {
            print_r($out);
            $lastError = "ogr2ogr reported an error: " . implode(" | ", $out);
            cleanUp();
            exit(1);
        }
    }
}

// Run for real if the dry run is passed.
print "\nInfo: Inserting in temp table done, proceeding...";
if ($deleteAppend == "1") {
    print "\nInfo: Delete/append is enabled.";
    if (!$table->exists) { // If table doesn't exists, when do not try to delete/append
        print "\nNotice: Table doesn't exists.";
        $o = "-overwrite";
    } else {
        print "\nInfo: Table exists.";
        $o = "-append";
    }
} else {
    print "\nInfo: Overwrite is enabled.";
    $o = "-overwrite";
}

$pkSql = null;
$idxSql = null;


// Count features
// ==============
$sql = "SELECT count(*) AS number FROM {$workingSchema}.{$randTableName}";

$res = $table->prepare($sql);

try {
    $res->execute();
    $n = $table->fetchRow($res)["number"];
    print "\nInfo: Total number of fetched features: " . $n;
    $report[FEATURECOUNT] = $n;

} catch (PDOException $e) {
    print "\nNotice: No data for the area (a guess).";
    $report[FEATURECOUNT] = 0;
    $table->rollback();
    cleanUp(1);
    exit(0);
}

// Pre run SQL
// ============
if ($preSql) {
    foreach (explode(";", trim($preSql, ";")) as $q) {
        print "\nInfo: Running pre-SQL: {$q}";
        $res = $table->prepare($q);
        try {
            $res->execute();
        } catch (PDOException $e) {
            print "\nError: ";
            print_r($e->getMessage());
            $lastError = $e->getMessage();
            $table->rollback();
            cleanUp();
            exit(1);
        }
    }
}

$extras = [];
$fieldObj = json_decode($extra);

if ($fieldObj) {
    if (gettype($fieldObj) == "object") {
        $fieldObj = [$fieldObj];
    }

    foreach ($fieldObj as $f) {
        $extras[] = $f->name;
    }
}

$fields = [];
foreach ($table->getMetaData("{$workingSchema}.{$randTableName}", false, false, null, null, false, false) as $k => $v) {
    if (!in_array($k, $extras)) {
        $fields[] = $k;
    }
}

print "\nInfo: Fields in source: ";
print implode(", ", $fields);

// Delete/append
// =============
if ($o != "-overwrite") {
    $sql = "DELETE FROM {$schema}.{$safeName}";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "\nError: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }

    print "\nInfo: Data in existing table deleted.";
    $fieldsStr = implode("\",\"", $fields);
    $sql = "INSERT INTO {$schema}.{$safeName} (\"{$fieldsStr}\") (SELECT \"{$fieldsStr}\" FROM {$workingSchema}.{$randTableName})";

// Overwrite
} else {
    $sql = "DROP TABLE IF EXISTS {$schema}.{$safeName} CASCADE";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "\nError: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        $table->rollback();
        cleanUp();
        exit(1);
    }
    $sql = "SELECT * INTO {$schema}.{$safeName} FROM {$workingSchema}.{$randTableName}";
    $pkSql = "ALTER TABLE {$schema}.{$safeName} ADD PRIMARY KEY (gid)";

    // Check for the_geom and create GIST index on it
    $sqlCheckForGeom = "SELECT column_name FROM information_schema.columns WHERE table_schema='{$schema}' AND table_name='{$safeName}' and column_name='the_geom'";
    $res = $table->prepare($sqlCheckForGeom);
    try {
        $res->execute();
        $row = $table->fetchRow($res);
        if ($row) {
            $idxSql = "CREATE INDEX {$safeName}_gix ON {$schema}.{$safeName} USING GIST (the_geom)";
        }
    } catch (PDOException $e) {
        print "\nError: ";
        print_r($e->getMessage());
        $lastError = $e->getMessage();
        cleanUp();
        exit(1);
    }

}

print "\nInfo: Create/update final table...";
$res = $table->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print "\nError: ";
    print_r($e->getMessage());
    $lastError = $e->getMessage();
    $table->rollback();
    cleanUp();
    exit(1);
}

if ($pkSql) {
    $res = $table->prepare($pkSql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "\nError: ";
        print_r($e->getMessage());
    }
}

if ($idxSql) {
    $res = $table->prepare($idxSql);
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "\nError: ";
        print_r($e->getMessage());
    }
}

// Add extra field and insert values
// =================================
if ($extra) {
    $fieldObj = json_decode($extra);

    if (!$fieldObj) {
        print "\nWarning: Extra fields JSON string is not valid.";
    } else {

        if (gettype($fieldObj) == "object") {
            $fieldObj = [$fieldObj];
        }

        foreach ($fieldObj as $f) {
            $fieldName = $f->name;
            $fieldType = $f->type ?? "varchar";
            $fieldValue = $f->value ?? null;
            Cache::deleteByPatterns([$table->postgisdb . '_' . $schema . '.' . $safeName . '*']);
            $check = $table->doesColumnExist($schema . "." . $safeName, $fieldName);
            if (!$check["exists"]) {
                $sql = "ALTER TABLE \"{$schema}\".\"{$safeName}\" ADD COLUMN {$fieldName} {$fieldType}";
                print "\nInfo: Adding {$fieldName}";
                $res = $table->prepare($sql);
                try {
                    $res->execute();
                } catch (PDOException $e) {
                    print "\nError: ";
                    print_r($e->getMessage());
                    $lastError = $e->getMessage();
                    $table->rollback();
                    cleanUp();
                    exit(1);
                }
            } else {
                print "\nInfo: Extra field {$fieldName} already exists.";
            }
            $sql = "UPDATE \"{$schema}\".\"{$safeName}\" SET {$fieldName} =:value";
            print "\nInfo: Updating extra field {$fieldName}...";
            $res = $table->prepare($sql);
            try {
                $res->execute(array(":value" => $fieldValue));
            } catch (PDOException $e) {
                print "\nError: ";
                print_r($e->getMessage());
                $lastError = $e->getMessage();
                $table->rollback();
                cleanUp();
                exit(1);

            }
        }
    }
}

// Post run SQL
// ============
if ($postSql) {
    foreach (explode(";", trim($postSql, ";")) as $q) {
        $q = str_replace("@TABLE@", $schema . "." . $safeName, $q);
        print "\nInfo: Running post-SQL: {$q}";
        $res = $table->prepare($q);
        try {
            $res->execute();
        } catch (PDOException $e) {
            print "\nError: ";
            print_r($e->getMessage());
            $lastError = $e->getMessage();
            $table->rollback();
            cleanUp();
            exit(1);
        }
    }
}

// Commit transaction
// =================
$table->commit();

print "\nInfo: Data imported into " . $schema . "." . $safeName;
//print "\nInfo: " . Tilecache::bust($schema . "." . $safeName)["message"];

// Clean up
// ========
function cleanUp(int $success = 0): void
{
    global $schema, $workingSchema, $randTableName, $table, $jobId, $dir, $tempFile, $safeName, $db, $report, $snapshotAfterImport, $schedulerLock, $runUuid, $lastError, $conn;

    // Unlink temp file
    // ================
    if (is_dir($dir . "/" . $tempFile)) {
        $it = new RecursiveDirectoryIterator($dir . "/" . $tempFile, RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            if ($file->isDir()) {
                @rmdir($file->getRealPath());
            } else {
                @unlink($file->getRealPath());
            }
        }
        rmdir($dir . "/" . $tempFile);
    }
    @unlink($dir . "/" . $tempFile);
    @unlink($dir . "/" . $tempFile . ".gz"); // In case of gz file


    // Update jobs table
    // =================
    $job = new \app\inc\Model(connection: new \app\inc\Connection(database: 'gc2scheduler'));

    // lastcheck
    // =========
    $res = $job->prepare("UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id");
    try {
        $res->execute([":lastcheck" => $success, ":id" => $jobId]);
    } catch (PDOException $e) {
        print "\nWarning: ";
        print_r($e->getMessage());
    }

    // lastrun
    // =======
    $res = $job->prepare("UPDATE jobs SET lastrun=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
    try {
        $res->execute(["id" => $jobId]);
    } catch (PDOException $e) {
        print "\nWarning: ";
        print_r($e->getMessage());
    }

    // Report
    // ======
    $res = $job->prepare("UPDATE jobs SET report=:report WHERE id=:id");
    try {
        $res->execute(["id" => $jobId, "report" => json_encode($report)]);
    } catch (PDOException $e) {
        print "\nWarning: ";
        print_r($e->getMessage());
    }

    if ($success) {
        // lasttimestamp
        $res = $job->prepare("UPDATE jobs SET lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
        try {
            $res->execute(["id" => $jobId]);
        } catch (PDOException $e) {
            print "\nWarning: ";
            print_r($e->getMessage());
        }
    }

    // Drop temp table
    $res = $table->prepare("DROP TABLE IF EXISTS {$workingSchema}.{$randTableName}");
    try {
        $res->execute();
    } catch (PDOException $e) {
        print "\nWarning: ";
        print_r($e->getMessage());
    }
    print "\nInfo: Temp table dropped.";

    if ($success) {
        $layer = new Layer(connection: $conn);
        $layer->updateLastmodified(schema: $schema, table: $safeName);
        print "\nInfo: Last modified value updated";
        $layer->insertDefaultMeta();
        if (!empty($snapshotAfterImport)) {
            try {
                $snap = new \app\models\Snapshot(new \app\inc\Connection(database: $db));
                if (!$snap->hasActive($schema, $safeName)) {
                    $snap->create($schema, $safeName, null, $db);
                    print "\nInfo: Snapshot queued for $schema.$safeName";
                }
            } catch (\Throwable $e) {
                print "\nWarning: could not queue snapshot: " . $e->getMessage();
            }
        }
    }

    if ($runUuid !== null) {
        $schedulerLock->finishRun($runUuid, $success ? 'succeeded' : 'failed', $success ? null : ($lastError ?? 'see job log'));
    }
}

cleanUp(1);
$schedulerLock->release();
exit(0);


