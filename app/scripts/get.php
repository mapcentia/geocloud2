<?php
include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

new \app\conf\App();

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Util;


echo date(DATE_RFC822) . "\n\n";

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


// Check if Paging should be used
if (sizeof(explode("|", $url)) > 1) {
    $grid = explode("|", $url)[0];
    $url = explode("|", $url)[1];
    if (sizeof(explode(",", $grid)) > 1) {
        $id = explode(",", $grid)[1];
        $grid = explode(",", $grid)[0];
    } else {
        $id = "ogr_pkid";
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
    global $encoding, $srid, $dir, $tempFile, $type, $db, $workingSchema, $randTableName, $downloadSchema, $url, $out, $err;

    print "Fetching remote data...\n\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $fp = fopen($dir . "/" . $tempFile, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    print "Staring inserting in temp table using ogr2ogr...\n\n";
    $cmd = "PGCLIENTENCODING={$encoding} " . which() . " " .
        "-overwrite " .
        "-dim 2 " .
        "-oo 'DOWNLOAD_SCHEMA=" . ($downloadSchema ? "YES" : "NO") . "' " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:{$srid}' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "'" . $dir . "/" . $tempFile . "' " .
        "-nln " . $workingSchema . "." . $randTableName . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    exec($cmd . ' 2>&1', $out, $err);
}

function getCmdPaging()
{
    global $randTableName, $type, $db, $workingSchema, $url, $grid, $id, $encoding, $downloadSchema, $table, $pass, $cellTemps, $numberOfFeatures, $out, $err;

    print "Start paged download...\n\n";

    $pass = true;
    $sql = "SELECT gid,ST_XMIN(st_fishnet), ST_YMIN(st_fishnet), ST_XMAX(st_fishnet), ST_YMAX(st_fishnet) FROM {$grid}";
    $res = $table->execQuery($sql);
    $cellTemps = [];

    function fetch($row, $url, $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $id)
    {
        global $pass, $count, $table, $cellTemps, $id, $numberOfFeatures, $out, $err;
        $out = [];
        $bbox = "{$row["st_xmin"]},{$row["st_ymin"]},{$row["st_xmax"]},{$row["st_ymax"]}";
        $wfsUrl = $url . "&BBOX=";
        $gmlName = $randTableName . "-" . $row["gid"] . ".gml";

        $cellTemp = "cell_" . md5(microtime() . rand());


        if (!file_put_contents("/var/www/geocloud2/public/logs/" . $gmlName, Util::wget($wfsUrl . $bbox . ",EPSG:25832"))) {
            echo "Error: could not get GML for cell #{$row["gid"]}\n";
            $pass = false;
        };

        $cmd = "PGCLIENTENCODING={$encoding} " . which("ogr2ogr") . " " .
            "-unsetFid " .
            "-nomd " .
            "-overwrite " .
            "-dim 2 " .
            "-oo 'REMOVE_UNUSED_LAYERS=YES' " .
            "-oo 'REMOVE_UNUSED_FIELDS=NO' " .
            "-lco 'GEOMETRY_NAME=the_geom' " .
            "-lco 'FID=gid' " .
            "-lco 'PRECISION=NO' " .
            "-a_srs 'EPSG:25832' " .
            "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . Connection::$param["postgisdb"] . "' " .
            "GMLAS:/var/www/geocloud2/public/logs/" . $gmlName . " " .
            "-nln {$workingSchema}.{$cellTemp} " .
            "-nlt {$type}";

        exec($cmd . ' 2>&1', $out, $err);


        if ($err) {
            $pass = false;
        }

        foreach ($out as $line) {
            if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
                $pass = false;
                break 1;
            }
        }

        if (!$pass) {
            if ($count > 2) {
                echo "Too many recursive tries to fetch cell #{$row["gid"]}\n";
                exit(1);
            }
            sleep(5);
            $count++;
            fetch($row, $url, $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $id);
            foreach ($out as $line) {
                echo $line . "\n";
            }
            echo "Request: " . $wfsUrl . $bbox . "\n\n";
            echo "\nOutputting the first few lines of the file:\n\n";
            $handle = @fopen("/var/www/geocloud2/public/logs/" . $gmlName, "r");
            if ($handle) {
                for ($i = 0; $i < 40; $i++) {
                    $buffer = fgets($handle, 4096);
                    echo $buffer;
                }
                if (!feof($handle)) {
                    echo "Error: unexpected fgets() fail\n";
                }
                fclose($handle);
            }
            unlink("/var/www/geocloud2/public/logs/" . $gmlName);
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
        echo $count;
    }

    while ($row = $table->fetchRow($res)) {
        global $count;
        $count = 1;
        fetch($row, $url, $randTableName, $encoding, $downloadSchema, $workingSchema, $type, $db, $id);
    }

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
                $gotFields = true;
                print_r($fields);
            }
        }
        $selects[] = "SELECT \"" . implode("\",\"", $fields) . "\" FROM {$workingSchema}.{$t}";
        $drops[] = "DROP TABLE {$workingSchema}.{$t}";
    }

    // Create UNION table
    $sql = "CREATE TABLE {$workingSchema}.{$randTableName} AS " . implode("\nUNION ALL\n", $selects);
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    } finally {
        // Clean cell tmp tables
        foreach ($drops as $d) {
            $res = $table->prepare($d);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                print_r($e->getMessage());
            }
        }
    }

    // If source has an "id" fields, it will be mapped to id2 by GMLAS driver
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
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    // Drop ogr_pkid
    $sql = "ALTER TABLE {$workingSchema}.{$randTableName} DROP ogr_pkid";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    rsort($numberOfFeatures);
    print "Highest number of features in cell: " . $numberOfFeatures[0] . "\n\n";

}

function getCmdFile()
{
    global $randTableName, $type, $db, $workingSchema, $url, $encoding, $srid, $out, $err;
    print "Staring inserting in temp table using file download...\n\n";

    $pass = true;

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
            print $file . "   ";
            // Delete files with errors
            unlink($path);
            print $e->getMessage() . "\n";
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
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "/var/www/geocloud2/public/logs/" . $fileSetName . " " .
        "-nln {$workingSchema}.{$randTableName} " .
        "-nlt {$type}";
    exec($cmd . ' 2>&1', $out, $err);

    array_map('unlink', glob("/var/www/geocloud2/public/logs/" . $randFileName . ".*"));
}

function getCmdZip()
{
    global $extCheck2, $dir, $url, $tempFile, $encoding, $srid, $type, $db, $workingSchema, $randTableName, $downloadSchema, $outFileName, $out, $err;

    print "Fetching remote zip...\n\n";
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
            echo "Could not unzip file";
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
            echo "Could not unrar file";
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
            echo "Could not gunzip file";
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
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "'" . $outFileName . "' " .
        "-nln " . $workingSchema . "." . $randTableName . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    exec($cmd . ' 2>&1', $out, $err);
}

\app\models\Database::setDb($db);
$table = new \app\models\Table($schema . "." . $safeName);

$sql = "CREATE SCHEMA IF NOT EXISTS {$workingSchema}";
$res = $table->prepare($sql);
try {
    $res->execute();
} catch (\PDOException $e) {
    print_r($e->getMessage());
    exit(1);
}


$tmpTable = new \app\models\Table($workingSchema . "." . $randTableName);

$getFunction();

if ($err) {
    print "Error " . $err . "\n\n";
    print_r($out);
    print "\n\n";
    // Output the first few lines of file
    if ($grid == null) {
        Print "Outputting the first few lines of the file:\n\n";
        $handle = @fopen($dir . "/" . $tempFile, "r");
        if ($handle) {
            for ($i = 0; $i < 40; $i++) {
                $buffer = fgets($handle, 4096);
                echo $buffer;
            }
            if (!feof($handle)) {
                echo "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        }
    }
    cleanUp();
    exit(1);

} else {
    foreach ($out as $line) {
        if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
            print_r($out);
            cleanUp();
            exit(1);
        }
    }
}

// Run for real if the dry run is passed.
print "Inserting in temp table done, proceeding...\n\n";
if ($deleteAppend == "1") {
    print "Delete/append is enabled.\n\n";
    if (!$table->exits) { // If table doesn't exists, when do not try to delete/append
        print "Table doesn't exists.\n\n";
        $o = "-overwrite";
    } else {
        print "Table exists.\n\n";
        $o = "-append";
    }
} else {
    print "Overwrite is enabled.\n\n";
    $o = "-overwrite";
}

$pkSql = null;
$idxSql = null;

// Begin transaction
// =================
$table->begin();

// Pre run SQL
// ============
if ($preSql) {
    foreach (explode(";", trim($preSql, ";")) as $q) {
        print "Running pre-SQL: {$q}\n";
        $res = $table->prepare($q);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            print_r($e->getMessage());
            $table->rollback();
            cleanUp();
            exit(1);
        }
    }
    print "\n";
}

// Delete/append
if ($o != "-overwrite") {
    $sql = "DELETE FROM {$schema}.{$safeName}";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }

    foreach ($table->getMetaData("{$schema}.{$safeName}") as $k => $v) {
        $fields[] = $k;
        if ($k == "the_geom") {
            break;
        }
    }

    print "Data in existing table deleted.\n\n";
    $sql = "INSERT INTO {$schema}.{$safeName} (SELECT \"" . implode("\",\"", $fields) . "\" FROM {$workingSchema}.{$randTableName})";

// Overwrite
} else {
    $sql = "DROP TABLE IF EXISTS {$schema}.{$safeName} CASCADE";
    $res = $table->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);
    }
    $sql = "SELECT * INTO {$schema}.{$safeName} FROM {$workingSchema}.{$randTableName}";
    $pkSql = "ALTER TABLE {$schema}.{$safeName} ADD PRIMARY KEY (gid)";
    $idxSql = "CREATE INDEX {$safeName}_gix ON {$schema}.{$safeName} USING GIST (the_geom)";
}

print "Create/update final table...\n\n";
print $sql . "\n\n";
$res = $table->prepare($sql);
try {
    $res->execute();
} catch (\PDOException $e) {
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
        print_r($e->getMessage());
    }
}

if ($idxSql) {
    $res = $table->prepare($idxSql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
    }
}

// Add extra field and insert values
// =================================
if ($extra) {
    $fieldObj = json_decode($extra);
    $fieldName = $fieldObj->name;
    $fieldType = isset($fieldObj->type) ? $fieldObj->type : "varchar";
    $fieldValue = isset($fieldObj->value) ? $fieldObj->value : null;
    $check = $table->doesColumnExist($schema . "." . $safeName, $fieldName);
    if (!$check["exists"]) {
        $sql = "ALTER TABLE \"{$schema}\".\"{$safeName}\" ADD COLUMN {$fieldName} {$fieldType}";
        print $sql . "\n\n";
        $res = $table->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            print_r($e->getMessage());
            $table->rollback();
            cleanUp();
            exit(1);
        }
    } else {
        print "Extra field already exists.\n\n";
    }
    $sql = "UPDATE \"{$schema}\".\"{$safeName}\" SET {$fieldName} =:value";
    print "Updating extra field...\n\n";
    $res = $table->prepare($sql);
    try {
        $res->execute(array(":value" => $fieldValue));
    } catch (\PDOException $e) {
        print_r($e->getMessage());
        $table->rollback();
        cleanUp();
        exit(1);

    }
}

// Post run SQL
// ============
if ($postSql) {
    foreach (explode(";", trim($postSql, ";")) as $q) {
        $q = str_replace("@TABLE@", $schema . "." . $safeName, $q);
        print "Running post-SQL: {$q}\n";
        $res = $table->prepare($q);
        try {
            $res->execute();
        } catch (\PDOException $e) {
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

print "Data imported into " . $schema . "." . $safeName . "\n\n";
print_r(\app\controllers\Tilecache::bust($schema . "." . $safeName));

// Clean up
// ========
function cleanUp($success = 0)
{
    global $schema, $workingSchema, $randTableName, $table, $jobId, $dir, $tempFile, $safeName, $db, $outFileName;

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
    $res = $job->prepare("UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id");
    try {
        $res->execute([":lastcheck" => $success, ":id" => $jobId]);
    } catch (\PDOException $e) {
        print_r($e->getMessage());
    }

    // lastrun
    $res = $job->prepare("UPDATE jobs SET lastrun=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
    try {
        $res->execute(["id" => $jobId]);
    } catch (\PDOException $e) {
        print_r($e->getMessage());
    }

    if ($success) {
        // lasttimestamp
        $res = $job->prepare("UPDATE jobs SET lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
        try {
            $res->execute(["id" => $jobId]);
        } catch (\PDOException $e) {
            print_r($e->getMessage());
        }
    }

    // Drop temp table
    $res = $table->prepare("DROP TABLE IF EXISTS {$workingSchema}.{$randTableName}");
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
    }
    print "\nTemp table dropped.\n\n";

    if ($success) {
        \app\models\Database::setDb($db);
        $layer = new \app\models\Layer();
        $res = $layer->updateLastmodified($schema . "." . $safeName . ".the_geom");
        print_r($res);
    }
}

cleanUp(1);
exit(0);


