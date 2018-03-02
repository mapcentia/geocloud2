<?php
include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

new \app\conf\App();

use \app\conf\App;
use \app\conf\Connection;

echo date(DATE_RFC822) . "\n\n";

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


// Check if Paging should be used
if (sizeof(explode("|", $url)) > 1) {
    $grid = explode("|", $url)[0];
    $url = explode("|", $url)[1];
    if (sizeof(explode(",", $grid)) > 1) {
        $id = explode(",", $grid)[1];
        $grid = explode(",", $grid)[0];
    } else {
        $id = "gml_id";
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
    } elseif (strtolower($extCheck2[0]) == "zip" || strtolower($extCheck2[0]) == "rar") {
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
    global $encoding, $srid, $dir, $tempFile, $type, $db, $schema, $randTableName, $downloadSchema, $url;

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
        "-nln " . $schema . "." . $randTableName . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    return $cmd;
}

function getCmdPaging()
{
    global $randTableName, $type, $db, $schema, $url, $grid, $id, $encoding, $downloadSchema;
    print "Staring inserting in temp table using paginated download...\n\n";
    $cmd = "php -f /var/www/geocloud2/app/scripts/utils/importwfs.php {$db} {$schema} \"{$url}\" {$randTableName} {$type} {$grid} 1 {$id} 0 {$encoding} {$downloadSchema}";
    return $cmd;
}

function getCmdFile()
{
    global $randTableName, $type, $db, $schema, $url, $encoding, $srid;
    print "Staring inserting in temp table using file download...\n\n";
    $cmd = "php -f /var/www/geocloud2/app/scripts/utils/importfile.php {$db} {$schema} \"{$url}\" {$randTableName} {$type} 1 {$encoding} {$srid}";
    return $cmd;
}

function getCmdZip()
{
    global $extCheck2, $dir, $url, $tempFile, $encoding, $srid, $type, $db, $schema, $randTableName, $downloadSchema;

    $file = "";

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
            $response['success'] = false;
            $response['message'] = "Could not unzip file";
            return Response::json($response);
        }
        $zip->extractTo($dir . "/" . $tempFile);
        $zip->close();
    }

    // RAR start
    // =========
    if (strtolower($extCheck2[0]) == "rar") {
        $rar_file = rar_open($dir . "/" . $tempFile . "." . $extCheck2[0]);
        if (!$rar_file) {
            $response['success'] = false;
            $response['message'] = "Could not unrar file";
            return Response::json($response);
        }

        $list = rar_list($rar_file);
        foreach ($list as $file) {
            $entry = rar_entry_get($rar_file, $file);
            $file->extract($dir . "/" . $tempFile); // extract to the current dir
        }
        rar_close($rar_file);
    }

    if ($handle = opendir($dir . "/" . $tempFile)) {
        while (false !== ($entry = readdir($handle))) {
            if ($entry !== "." && $entry !== "..") {
                $zipCheck1 = explode(".", $entry);
                $zipCheck2 = array_reverse($zipCheck1);
                if (in_array(strtolower($zipCheck2[0]), $ext)) {
                    $file = $entry;
                    break;
                }
            }
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
        "'" . $dir . "/" . $tempFile . "/" . $file . "' " .
        "-nln " . $schema . "." . $randTableName . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    return $cmd;
}

\app\models\Database::setDb($db);
$table = new \app\models\Table($schema . "." . $safeName);

exec($cmd = $getFunction() . ' 2>&1', $out, $err);

$tmpTable = new \app\models\Table($schema . "." . $randTableName);

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
    print "Commando:\n";
    print $cmd . "\n\n";
    foreach ($out as $line) {
        if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
            print $line . "\n";
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
    foreach (explode(";", $preSql) as $q) {
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

// Overwrite
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
    print "Data in existing table deleted.\n\n";
    $sql = "INSERT INTO {$schema}.{$safeName} (SELECT * FROM {$schema}.{$randTableName})";

// Delete/append
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
    $sql = "SELECT * INTO {$schema}.{$safeName} FROM {$schema}.{$randTableName}";
    $pkSql = "ALTER TABLE {$schema}.{$safeName} ADD PRIMARY KEY (gid)";
    $idxSql = "CREATE INDEX {$safeName}_gix ON {$schema}.{$safeName} USING GIST (the_geom)";
}

print "Insert/update final table...\n\n";
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
    foreach (explode(";", $postSql) as $q) {
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
    global $schema, $randTableName, $table, $jobId, $dir, $tempFile;

    // Unlink temp file
    // ================
    //unlink($dir . "/" . $tempFile);

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

    // lasttimestamp
    if ($success) {
        $res = $job->prepare("UPDATE jobs SET lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id");
        try {
            $res->execute(["id" => $jobId]);
        } catch (\PDOException $e) {
            print_r($e->getMessage());
        }
    }

    // Drop temp table
    $res = $table->prepare("DROP TABLE IF EXISTS {$schema}.{$randTableName}");
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
    }
    print "\nTemp table dropped.\n\n";
}

cleanUp(1);
exit(0);


