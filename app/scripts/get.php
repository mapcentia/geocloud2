<?php
include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

new \app\conf\App();

use \app\conf\App;
use \app\conf\Connection;
use \Postmark\PostmarkClient;
use \Postmark\Models\PostmarkException;

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
$extra = isset($argv[10]) ? base64_decode($argv[10]) : null;

if (sizeof(explode("|", $url)) > 1) {
    $grid = explode("|", $url)[0];
    $url = explode("|", $url)[1];

    if (sizeof(explode(",", $grid)) > 1) {
        $id = explode(",", $grid)[1];
        $grid = explode(",", $grid)[0];
    } else {
        $id = "gml_id";
    }

    $getFunction = "getCmdPaging";
} else {
    $grid = null;
    $getFunction = "getCmd";

}


$dir = App::$param['path'] . "app/tmp/" . $db . "/__vectors";
$tempFile = md5(microtime() . rand()) . ".gml";
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

    print "Fetching remote data...\n\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    $fp = fopen($dir . "/" . $tempFile, 'w+');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_exec($ch);
    curl_close($ch);
    fclose($fp);
}

function which()
{
    return "/usr/local/bin/ogr2ogr";
}


function getCmd()
{
    global $encoding, $srid, $dir, $tempFile, $type, $db, $schema, $randTableName;

    print "Staring inserting in temp table using ogr2ogr...\n\n";

    $cmd = "PGCLIENTENCODING={$encoding} " . which() . " " .
        "-overwrite " .
        "-dim 2 " .
        ($db == "mydb" ? "-oo 'DOWNLOAD_SCHEMA=NO' " : "") .
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
    global $randTableName, $type, $db, $schema, $url, $grid, $id;

    print "Staring inserting in temp table using paginated download...\n\n";

    $cmd = "php -f /var/www/geocloud2/app/scripts/utils/importwfs.php {$db} {$schema} \"{$url}\" {$randTableName} {$type} {$grid} 1 {$id} 0";

    return $cmd;
}

$pass = true;

\app\models\Database::setDb($db);
$table = new \app\models\Table($schema . "." . $safeName);

exec($cmd = $getFunction() . ' 2>&1', $out, $err);

if ($err) {
    print "Error " . $err . "\n\n";
    $pass = false;
} else {
    print "Commando:\n";
    print $cmd . "\n\n";
    foreach ($out as $line) {
        if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
            $pass = false;
            break;
        }
    }

}


// Run for real if the dry run is passed.
if ($pass) {

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

    $table->begin();

    if ($o != "-overwrite") {

        $sql = "DELETE FROM {$schema}.{$safeName}";
        $res = $table->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {

        }

        print "Data in existing table deleted.\n\n";

        $sql = "INSERT INTO {$schema}.{$safeName} (SELECT * FROM {$schema}.{$randTableName})";

    } else {

        $sql = "DROP TABLE IF EXISTS {$schema}.{$safeName} CASCADE";
        $res = $table->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
        }

        $sql = "SELECT * INTO {$schema}.{$safeName} FROM {$schema}.{$randTableName}";
        $pkSql = "ALTER TABLE {$schema}.{$safeName} ADD PRIMARY KEY (gid)";
        $idxSql = "CREATE INDEX {$safeName}_gix ON {$schema}.{$safeName} USING GIST (the_geom)";

    }

    $res = $table->prepare($sql);

    try {

        $res->execute();

    } catch (\PDOException $e) {

        print_r($e->getMessage());
        $pass = false;

    }

    $table->commit();

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

    # Clean up
    $sql = "DROP TABLE IF EXISTS {$schema}.{$randTableName}";
    $res = $table->prepare($sql);
    print "Existing table dropped.\n\n";

    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e->getMessage());
    }

}

if ($pass) {

    print "Data imported into " . $schema . "." . $safeName . "\n\n";
    $sql = "UPDATE jobs SET lastcheck=:lastcheck, lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id";
    $values = array(":lastcheck" => 1, ":id" => $jobId);
    print_r(\app\controllers\Tilecache::bust($schema . "." . $safeName));

} else {

    print_r($out);

    print "\n\n";

    $sql = "UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id";
    $values = array(":lastcheck" => 0, ":id" => $jobId);

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


    if (App::$param["notification"]) {

        try {
            $client = new PostmarkClient(App::$param["notification"]["key"]);

        } catch (PostmarkException $ex) {
            echo $ex->httpStatusCode;
            echo $ex->postmarkApiErrorCode;

        } catch (Exception $generalException) {
            // A general exception is thown if the API
        }

        $text =
            "Job id: {$jobId}\n" .
            "Database: {$db}\n" .
            "Schema: {$schema}\n" .
            "table: {$safeName}\n" .
            "Log: https://geofyn.mapcentia.com/logs/{$jobId}_scheduler.log\n";

        $message = [
            'To' => implode(",", App::$param["notification"]["to"]),
            'From' => App::$param["notification"]["from"],
            'TrackOpens' => false,
            'Subject' => "GC2Scheduler job with error",
            'TextBody' => $text,
        ];
        $sendResult = $client->sendEmailBatch([$message]);
    }

}

\app\models\Database::setDb("gc2scheduler");
$model = new \app\inc\Model();
$res = $model->prepare($sql);
try {
    $res->execute($values);
} catch (\PDOException $e) {
    print_r($e);
}

// Add extra field and insert values
if ($extra && $pass) {
    \app\models\Database::setDb($db);
    $model = new \app\inc\Model();
    $fieldObj = json_decode($extra);

    $fieldName = $fieldObj->name;
    $fieldType = isset($fieldObj->type) ?: "varchar";
    $fieldValue = $fieldObj->value;

    $check = $model->doesColumnExist($schema . "." . $safeName, $fieldName);

    if (!$check["exists"]) {
        $sql = "ALTER TABLE \"{$schema}\".\"{$safeName}\" ADD COLUMN {$fieldName} {$fieldType}";
        print "SQL run:\n";
        print $sql . "\n\n";
        $res = $model->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            print_r($e);
        }
    } else {
        print "Extra field already exists.\n\n";
    }
    $sql = "UPDATE \"{$schema}\".\"{$safeName}\" SET {$fieldName} =:value";
    print "SQL run:\n";
    print $sql . "\n\n";
    $res = $model->prepare($sql);
    try {
        $res->execute(array(":value" => $fieldValue));
    } catch (\PDOException $e) {
        print_r($e);
    }
}

// Unlink temp file
@unlink($dir . "/" . $tempFile);
