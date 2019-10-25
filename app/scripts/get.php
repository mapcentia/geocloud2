<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

set_time_limit(0);

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

new \app\conf\App();

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Util;

$report = [];

$lockDir = App::$param['path'] . "/app/tmp/scheduler_locks";

if (!file_exists($lockDir)) {
    @mkdir($lockDir);
}

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

print "Info: Started at " . date(DATE_RFC822) . "\n\n";

// Set path so libjvm.so can be loaded in ogr2ogr for MS Access support
putenv("LD_LIBRARY_PATH=/usr/lib/jvm/java-8-openjdk-amd64/jre/lib/amd64/server");

$db = $argv[1];
$schema = $argv[2];
$safeName = $argv[3];
$url = $argv[4];
$srid = $argv[5];
$type = $argv[6];
$encoding = $argv[7];
$jobId = $argv[8];
$deleteAppend = $argv[9];
$extra = $argv[10] == "null" ? null : base64_decode($argv[10]);
$preSql = $argv[11] == "null" ? null : base64_decode($argv[11]);
$postSql = $argv[12] == "null" ? null : base64_decode($argv[12]);
$downloadSchema = $argv[13];

$workingSchema = "_gc2scheduler";

// Create lock file
$lockDir = App::$param['path'] . "/app/tmp/scheduler_locks";
$lockFile = $lockDir . "/" . $jobId . ".lock";

if (!file_exists($lockDir)) {
    @mkdir($lockDir);
}

if (!file_exists($lockFile)) {
    @touch($lockFile);
}


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
    $grid = null;

    // Check if file extension
    // =======================
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

$dir = App::$param['path'] . "app/tmp/" . $db . "/__vectors";
$tempFile = md5(microtime() . rand());
$randTableName = "_" . md5(microtime() . rand());
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

if ($grid == null) {

}

function which()
{
    return "/usr/local/bin/ogr2ogr";
}

function getCmd()
{
    global $encoding, $srid, $dir, $tempFile, $type, $db, $workingSchema, $randTableName, $downloadSchema, $url, $report, $out, $err;

    $report[DOWNLOADTYPE] = URL;

    print "Info: Fetching remote data...\n\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $fp = fopen($dir . "/" . $tempFile, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    print "Info: Staring inserting in temp table using ogr2ogr...\n\n";

    $cmd = "PGCLIENTENCODING={$encoding} " . which() . " " .
        "-overwrite " .
        "-dim 2 " .
        "-oo 'DOWNLOAD_SCHEMA=" . ($downloadSchema ? "YES" : "NO") . "' " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:{$srid}' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " port=" . Connection::$param["postgisport"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "'" . $dir . "/" . $tempFile . "' " .
        "-nln " . $workingSchema . "." . $randTableName . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    exec($cmd . ' 2>&1', $out, $err);
}

function getCmdPaging()
{
    global $randTableName, $type, $db, $workingSchema, $url, $grid, $id, $encoding, $downloadSchema, $table, $pass, $cellTemps, $report, $numberOfFeatures;

    $downloadSchema ? $report[DOWNLOADTYPE] = GMLAS :  $report[DOWNLOADTYPE] = GML;

    print "Info: Start paged download...\n\n";

    $pass = true;
    $sql = "SELECT gid,ST_XMIN(st_fishnet), ST_YMIN(st_fishnet), ST_XMAX(st_fishnet), ST_YMAX(st_fishnet) FROM {$grid} GROUP BY gid, st_xmin, st_ymin, st_xmax, st_ymax ORDER BY gid";
    $res = $table->execQuery($sql);
    $cellTemps = [];

    function fetch($row, $url, $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $id)
    {
        global $pass, $count, $table, $cellTemps, $id, $numberOfFeatures, $srid, $out, $err;
        $out = [];
        $bbox = "{$row["st_xmin"]},{$row["st_ymin"]},{$row["st_xmax"]},{$row["st_ymax"]},EPSG:{$srid}";
        $wfsUrl = $url . "&BBOX=";
        $gmlName = $randTableName . "-" . $row["gid"] . ".gml";

        $cellTemp = "cell_" . md5(microtime() . rand());

        if (!file_put_contents("/var/www/geocloud2/public/logs/" . $gmlName, Util::wget($wfsUrl . $bbox))) {
            print "Error: could not get GML for cell #{$row["gid"]}\n";
            $pass = false;
        };

        $cmd = "PGCLIENTENCODING={$encoding} " . which("ogr2ogr") . " " .
            "-overwrite " .
            "-preserve_fid " .
            "-dim 2 " .
            ($downloadSchema ? "-oo 'CONFIG_FILE=/var/www/geocloud2/app/scripts/gmlasconf.xml' " : " ") .
            "-lco 'GEOMETRY_NAME=the_geom' " .
            "-lco 'FID=gid' " .
            "-lco 'PRECISION=NO' " .
            "-a_srs 'EPSG:{$srid}' " .
            "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " port=" . Connection::$param["postgisport"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
            ($downloadSchema ? "GMLAS:/var/www/geocloud2/public/logs/" . $gmlName . " " : "/var/www/geocloud2/public/logs/" . $gmlName . " ") .
            "-nln {$workingSchema}.{$cellTemp} " .
            "-nlt {$type}";

        exec($cmd . ' 2>&1', $out, $err);

        if ($err) {
            $pass = false;
        }

        // The GMLAS driver sometimes throws a 404 error, so we can't stop on this kind of error
        foreach ($out as $line) {
            if (strpos($line, "FAILURE") !== false || (strpos($line, "ERROR") !== false && $line != "ERROR 1: HTTP error code : 404")) {
                $pass = false;
                break 1;
            }
        }

        if (!$pass) {
            if ($count > 2) {
                print "Error; Too many recursive tries to fetch cell #{$row["gid"]}\n";
                cleanUp();
                exit(1);
            }
            sleep(5);
            $count++;
            fetch($row, $url, $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $id);
            foreach ($out as $line) {
                print $line . "\n";
            }
            print "Request: " . $wfsUrl . $bbox . "\n\n";
            print "\nInfo: Outputting the first few lines of the file:\n\n";
            $handle = @fopen("/var/www/geocloud2/public/logs/" . $gmlName, "r");
            if ($handle) {
                for ($i = 0; $i < 40; $i++) {
                    $buffer = fgets($handle, 4096);
                    print $buffer;
                }
                if (!feof($handle)) {
                    print "Error: unexpected fgets() fail\n";
                }
                fclose($handle);
            }
            @unlink("/var/www/geocloud2/public/logs/" . $gmlName);
            cleanUp();
            exit(1);
        }

        @unlink("/var/www/geocloud2/public/logs/" . $gmlName);

        $sql = "SELECT count(*) AS number FROM {$workingSchema}.{$cellTemp}";

        $res = $table->prepare($sql);

        try {
            $res->execute();
            $numberOfFeatures[] = $table->fetchRow($res)["number"];
            $cellTemps[] = $cellTemp;
        } catch (\PDOException $e) {
            $numberOfFeatures[] = 0;
        }
        print $count;
    }

    print "Cells: ";
    while ($row = $table->fetchRow($res)) {
        global $count;
        $count = 1;
        fetch($row, $url, $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $id);
    }
    print "\n\n";

    $selects = [];
    $drops = [];
    $gotFields = false;
    foreach ($cellTemps as $t) {
        if (!$gotFields) {
            foreach ($table->getMetaData("{$workingSchema}.{$t}") as $k => $v) {
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
        print "Notice: No data for the area.\n\n";
        $report[FEATURECOUNT] = 0;
        cleanUp(1);
    }

    $sql = "CREATE TABLE {$workingSchema}.{$randTableName} AS " . implode("\nUNION ALL\n", $selects);
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        cleanUp();
        exit(1);
    } finally {
        // Clean cell tmp tables
        foreach ($drops as $d) {
            $res = $table->prepare($d);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                print "Warning: ";
                print_r($e->getMessage());
            }
        }
    }

    // If source has an "id" fields and identifier is gml:id, it will be mapped to id2 by GMLAS driver
    // We try to rename id2 to id and drop id1
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} RENAME id2 TO id";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Notice: Could not rename id2 to id. Source may not has an 'id' field.";
        print "\n\n";
    }
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} DROP id1";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Notice: Could not drop id1. Source may not has an 'id' field.";
        print "\n\n";
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
                                print "Error: Could not find id or fid field. Please set identifier name in URL\n\n";
                                cleanUp();
                                exit(1);
                            }
                        } catch (\PDOException $e) {
                            print "Error: ";
                            print_r($e->getMessage());
                            cleanUp();
                            exit(1);
                        }
                    }
                } catch (\PDOException $e) {
                    print "Error: ";
                    print_r($e->getMessage());
                    cleanUp();
                    exit(1);
                }
            }
        } catch (\PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
            cleanUp();
            exit(1);
        }
    }

    print "Info: Identifier set to: {$id}\n\n";

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
        if (sizeof($row["num"]) > 0) {
            print "Info: Removed " . $row["num"] . " duplicates.\n\n";
            $report[DUPSCOUNT] = $row["num"];
        } else {
            print "Notice: Removed no dups\n\n";
            $report[DUPSCOUNT] = 0;
        }
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
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
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    // Create a dummy gml_id field
    // Support of legacy destination tables with gml_id field
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} ADD gml_id INT";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Warning: Could not create a dummy gml_id field.";
        print "\n\n";
    }

    // Alter gid so it becomes unique
    $sql = "CREATE TEMPORARY SEQUENCE gid_seq";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} ALTER gid SET DEFAULT nextval('gid_seq')";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }
    $sql = "UPDATE {$workingSchema}.{$randTableName} SET gid=DEFAULT";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    // Drop ogr_pkid
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} DROP COLUMN IF EXISTS ogr_pkid";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    rsort($numberOfFeatures);
    print "Info: Highest number of features in cell: " . $numberOfFeatures[0] . "\n\n";
    $report[MAXCELLCOUNT] = $numberOfFeatures[0];

}

function getCmdFile()
{
    global $randTableName, $type, $db, $workingSchema, $url, $encoding, $srid, $report, $out, $err;

    $report[DOWNLOADTYPE] = FILE;

    print "Info: Staring inserting in temp table using file download...\n\n";

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
            // Try to get both upper and lower case extension
            $files[$randFileName . ".dbf"] = $base . ".dbf";
            $files[$randFileName . ".DBF"] = $base . ".DBF";
            $files[$randFileName . ".shx"] = $base . ".shx";
            $files[$randFileName . ".SHX"] = $base . ".SHX";
            $fileSetName = $randFileName . "." . $extension;
            break;

        case "tab":
            $files[$randFileName . ".tab"] = $url;
            $files[$randFileName . ".TAB"] = $url;
            // Try to get both upper and lower case extension
            $files[$randFileName . ".map"] = $base . ".map";
            $files[$randFileName . ".MAP"] = $base . ".MAP";
            $files[$randFileName . ".dat"] = $base . ".dat";
            $files[$randFileName . ".DAT"] = $base . ".DAT";
            $files[$randFileName . ".id"] = $base . ".id";
            $files[$randFileName . ".ID"] = $base . ".ID";
            $files[$randFileName . ".ind"] = $base . ".ind";
            $files[$randFileName . ".IND"] = $base . ".IND";
            $fileSetName = $randFileName . "." . $extension;
            break;

        default:
            $files[$randFileName . ".general"] = $url;
            $fileSetName = $randFileName . ".general";
            break;
    }

    foreach ($files as $key => $file) {
        $path = "/var/www/geocloud2/public/logs/" . $key;
        $fileRes = fopen($path, 'w');
        try {
            file_put_contents($path, Util::wget($file));
        } catch (Exception $e) {
            print "Error: ";
            print $file . "   ";
            // Delete files with errors
            @unlink($path);
            print $e->getMessage() . "\n";
            cleanUp();
            exit(1);
        }
    }

    $cmd = "PGCLIENTENCODING={$encoding} " . which() . " " .
        "-append " .
        "-dim 2 " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:{$srid}' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " port=" . Connection::$param["postgisport"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "/var/www/geocloud2/public/logs/" . $fileSetName . " " .
        "-nln {$workingSchema}.{$randTableName} " .
        "-nlt {$type}";
    exec($cmd . ' 2>&1', $out, $err);

    array_map('unlink', glob("/var/www/geocloud2/public/logs/" . $randFileName . ".*"));
}

function getCmdZip()
{
    global $extCheck2, $dir, $url, $tempFile, $encoding, $srid, $type, $db, $workingSchema, $randTableName, $downloadSchema, $outFileName, $report, $out, $err;

    $report[DOWNLOADTYPE] = ZIP;

    print "Info: Fetching remote zip...\n\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $fp = fopen($dir . "/" . $tempFile . "." . $extCheck2[0], 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
    $ext = array("shp", "tab", "geojson", "gml", "kml", "mif", "gdb", "csv");

    // ZIP start
    // =========
    if (strtolower($extCheck2[0]) == "zip") {
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

    // RAR start
    // =========
    if (strtolower($extCheck2[0]) == "rar") {
        $rarFile = rar_open($dir . "/" . $tempFile . "." . $extCheck2[0]);
        if (!$rarFile) {
            print "Error: Could not unrar file";
            cleanUp();
            exit(1);
        }

        $list = rar_list($rarFile);
        foreach ($list as $file) {
            $entry = rar_entry_get($rarFile, $file);
            $file->extract($dir . "/" . $tempFile);
        }
        rar_close($rarFile);
        unlink($dir . "/" . $tempFile . "." . $extCheck2[0]);

    }

    // GZIP start
    // ==========
    if (strtolower($extCheck2[0]) == "gz") {
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

    $cmd = "PGCLIENTENCODING={$encoding} " . which() . " " .
        "-overwrite " .
        "-dim 2 " .
        "-oo 'DOWNLOAD_SCHEMA=" . ($downloadSchema ? "YES" : "NO") . "' " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:{$srid}' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " port=" . Connection::$param["postgisport"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "'" . $outFileName . "' " .
        "-nln " . $workingSchema . "." . $randTableName . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    exec($cmd . ' 2>&1', $out, $err);
}

\app\models\Database::setDb($db);
$table = new \app\models\Table($schema . "." . $safeName);

// Begin transaction
// =================
$table->begin();

$sql = "CREATE SCHEMA IF NOT EXISTS {$workingSchema}";
$res = $table->prepare($sql);
try {
    $res->execute();
} catch (\PDOException $e) {
    print "Error: ";
    print_r($e->getMessage());
    cleanUp();
    exit(1);
}

// We poll for running jobs
// ========================
function poll()
{
    global $getFunction, $lockDir, $report;
    $sleep = 10;
    $maxJobs = 20;
    $fi = new FilesystemIterator($lockDir, FilesystemIterator::SKIP_DOTS);
    if (iterator_count($fi) > $maxJobs) {
        print "info: There are " . iterator_count($fi) . " jobs running right now. Waiting {$sleep} seconds...\n\n";
        $report[SLEEP]+= $sleep;
        sleep($sleep);
        poll();
    } else {
        $getFunction();
    }
}
poll();

// Check output
// ============
if ($err) {
    print "Error " . $err . "\n\n";
    print_r($out);
    print "\n\n";
    // Output the first few lines of file
    if ($grid == null) {
        Print "Info: Outputting the first few lines of the file:\n\n";
        $handle = @fopen($dir . "/" . $tempFile, "r");
        if ($handle) {
            for ($i = 0; $i < 40; $i++) {
                $buffer = fgets($handle, 4096);
                print $buffer;
            }
            if (!feof($handle)) {
                print "Error: unexpected fgets() fail\n";
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
            cleanUp();
            exit(1);
        }
    }
}

// Run for real if the dry run is passed.
print "Info: Inserting in temp table done, proceeding...\n\n";
if ($deleteAppend == "1") {
    print "Info: Delete/append is enabled.\n\n";
    if (!$table->exits) { // If table doesn't exists, when do not try to delete/append
        print "Notice: Table doesn't exists.\n\n";
        $o = "-overwrite";
    } else {
        print "Info: Table exists.\n\n";
        $o = "-append";
    }
} else {
    print "Info: Overwrite is enabled.\n\n";
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
    print "Info: Total number of fetched features: " . $n . "\n";
    $report[FEATURECOUNT] = $n;

} catch (\PDOException $e) {
    print "Notice: No data for the area (a guess).\n\n";
    $report[FEATURECOUNT] = 0;
    $table->rollback();
    cleanUp(1);
}

// Pre run SQL
// ============
if ($preSql) {
    foreach (explode(";", trim($preSql, ";")) as $q) {
        print "Info: Running pre-SQL: {$q}\n";
        $res = $table->prepare($q);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
            $table->rollback();
            cleanUp();
            exit(1);
        }
    }
    print "\n";
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

foreach ($table->getMetaData("{$workingSchema}.{$randTableName}") as $k => $v) {
    if (!in_array($k, $extras)) {
        $fields[] = $k;
    }
}

print "\nInfo: Fields in source: ";
print implode(", ", $fields);
print "\n\n";

// Delete/append
// =============
if ($o != "-overwrite") {
    $sql = "DELETE FROM {$schema}.{$safeName}";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    print "Info: Data in existing table deleted.\n\n";
    $sql = "INSERT INTO {$schema}.{$safeName} (SELECT \"" . implode("\",\"", $fields) . "\" FROM {$workingSchema}.{$randTableName})";

// Overwrite
} else {
    $sql = "DROP TABLE IF EXISTS {$schema}.{$safeName} CASCADE";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
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
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
        cleanUp();
        exit(1);
    }

}

print "Info: Create/update final table...\n\n";
//print $sql . "\n\n";
$res = $table->prepare($sql);
try {
    $res->execute();
} catch (\PDOException $e) {
    print "Error: ";
    print_r($e->getMessage());
    $table->rollback();
    cleanUp();
    exit(1);
}

if ($pkSql) {
    $res = $table->prepare($pkSql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
    }
}

if ($idxSql) {
    $res = $table->prepare($idxSql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Error: ";
        print_r($e->getMessage());
    }
}

// Add extra field and insert values
// =================================
if ($extra) {
    $fieldObj = json_decode($extra);

    if (!$fieldObj) {
        print "\n\nWarning: Extra fields JSON string is not valid\n\n";
    } else {

        if (gettype($fieldObj) == "object") {
            $fieldObj = [$fieldObj];
        }

        foreach ($fieldObj as $f) {
            $fieldName = $f->name;
            $fieldType = isset($f->type) ? $f->type : "varchar";
            $fieldValue = isset($f->value) ? $f->value : null;
            $check = $table->doesColumnExist($schema . "." . $safeName, $fieldName);
            if (!$check["exists"]) {
                $sql = "ALTER TABLE \"{$schema}\".\"{$safeName}\" ADD COLUMN {$fieldName} {$fieldType}";
                print "info: Adding {$fieldName}\n\n";
                $res = $table->prepare($sql);
                try {
                    $res->execute();
                } catch (\PDOException $e) {
                    print "Error: ";
                    print_r($e->getMessage());
                    $table->rollback();
                    cleanUp();
                    exit(1);
                }
            } else {
                print "Info: Extra field {$fieldName} already exists.\n\n";
            }
            $sql = "UPDATE \"{$schema}\".\"{$safeName}\" SET {$fieldName} =:value";
            print "Info: Updating extra field {$fieldName}...\n\n";
            $res = $table->prepare($sql);
            try {
                $res->execute(array(":value" => $fieldValue));
            } catch (\PDOException $e) {
                print "Error: ";
                print_r($e->getMessage());
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
        print "Info: Running post-SQL: {$q}\n";
        $res = $table->prepare($q);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            print "Error: ";
            print_r($e->getMessage());
            $table->rollback();
            cleanUp();
            exit(1);
        }
    }
    print "\n";
}

// Commit transaction
// =================
$table->commit();

print "Info: Data imported into " . $schema . "." . $safeName . "\n";
print "\nInfo: " . \app\controllers\Tilecache::bust($schema . "." . $safeName)["message"] . "\n";

// Clean up
// ========
function cleanUp($success = 0)
{
    global $schema, $workingSchema, $randTableName, $table, $jobId, $dir, $tempFile, $safeName, $db, $report, $lockFile;

    // Unlink lock file
    unlink($lockFile);

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
    \app\models\Database::setDb("gc2scheduler");
    $job = new \app\inc\Model();

    // lastcheck
    // =========
    $res = $job->prepare("UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id");
    try {
        $res->execute([":lastcheck" => $success, ":id" => $jobId]);
    } catch (\PDOException $e) {
        print "Warning: ";
        print_r($e->getMessage());
    }

    // lastrun
    // =======
    $res = $job->prepare("UPDATE jobs SET lastrun=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
    try {
        $res->execute(["id" => $jobId]);
    } catch (\PDOException $e) {
        print "Warning: ";
        print_r($e->getMessage());
    }

    // Report
    // ======
    $res = $job->prepare("UPDATE jobs SET report=:report WHERE id=:id");
    try {
        $res->execute(["id" => $jobId, "report" => json_encode($report)]);
    } catch (\PDOException $e) {
        print "Warning: ";
        print_r($e->getMessage());
    }

    if ($success) {
        // lasttimestamp
        $res = $job->prepare("UPDATE jobs SET lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
        try {
            $res->execute(["id" => $jobId]);
        } catch (\PDOException $e) {
            print "Warning: ";
            print_r($e->getMessage());
        }
    }

    // Drop temp table
    $res = $table->prepare("DROP TABLE IF EXISTS {$workingSchema}.{$randTableName}");
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print "Warning: ";
        print_r($e->getMessage());
    }
    print "\nInfo: Temp table dropped.\n\n";

    if ($success) {
        \app\models\Database::setDb($db);
        $layer = new \app\models\Layer();
        $res = $layer->updateLastmodified($schema . "." . $safeName . ".the_geom");
        print "Info: " . $res["message"] . "\n";
    }
}

cleanUp(1);
exit(0);


