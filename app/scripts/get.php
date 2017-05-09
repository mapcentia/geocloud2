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
    $cmd = "/usr/bin/which ogr2ogr";
    exec($cmd . ' 2>&1', $out, $err);
    return $out[0];
}


function getCmd($dryRun = false, $o)
{
    global $encoding, $srid, $dir, $tempFile, $safeName, $type, $db, $schema, $randTableName;
    $cmd = "PGCLIENTENCODING={$encoding} " . which() . " " .
        $o . " " .
        "-dim 2 " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:{$srid}' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . "' " .
        "'" . $dir . "/" . $tempFile . "' " .
        "-nln " . ($dryRun ? $schema . "." . $randTableName : $schema . "." . $safeName) . " " .
        ($type == "AUTO" ? "" : "-nlt {$type}") .
        "";
    return $cmd;
}

function getCmdPaging($dryRun = false, $o)
{
    global $safeName, $type, $db, $schema, $url, $grid, $id;


    if ($o == "-overwrite") {
        $o = 1;
    } else {
        $o = 0;
    }
    print "Staring paged download...\n\n";

    $cmd = "php -f /var/www/geocloud2/app/scripts/utils/importwfs.php {$db} {$schema} \"{$url}\" {$safeName} {$type} {$grid} {$o} {$id} 0";

    return $cmd;
}


$pass = true;

\app\models\Database::setDb($db);
$table = new \app\models\Table($schema . "." . $safeName);

# Dry run
if ($deleteAppend == "1" && $table->exits && $grid == null) {

    print "Starting dry run...\n\n";

    # clone table
    $sql = "CREATE TABLE {$schema}.{$randTableName} AS SELECT * FROM {$schema}.{$safeName}";
    $res = $table->prepare($sql);

    print "SQL run:\n";
    print $sql . "\n\n";

    try {
        $res->execute();

    } catch (\PDOException $e) {
        print_r($e);
        $pass = false;
        exit("Could not create temporary table.");
    }

    if ($pass) {
        exec($cmd = getCmd(true, "-append") . ' 2>&1', $out, $err);

        if ($err) {
            print "Error " . $err . "\n\n";
            $pass = false;
        } else {

            print "Dry run command:\n";
            print $cmd . "\n\n";
            foreach ($out as $line) {
                if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
                    $pass = false;
                    break;
                }
            }
        }
    }
    # Clean up
    $sql = "DROP TABLE {$schema}.{$randTableName}";
    $res = $table->prepare($sql);
    print "SQL run:\n";
    print $sql . "\n\n";
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e);
    }
}


# Run for real if the dry run is passed.
if ($pass) {

    print "Passed, proceeding...\n\n";

    if ($deleteAppend == "1") {

        print "Delete/append is enabled.\n\n";

        if (!$table->exits) { // If table doesn't exists, when do not try to delete/append

            print "Table doesn't exists.\n\n";

            $o = "-overwrite";

        } else {

            print "Table exists.\n\n";

            $o = "-append";
            $sql = "DELETE FROM {$schema}.{$safeName}";
            print "SQL run:\n";
            print $sql . "\n\n";
            $res = $table->prepare($sql);
            try {
                $res->execute();
            } catch (\PDOException $e) {
                // Set the  success of the job to false
                print_r($e);
                \app\models\Database::setDb("gc2scheduler");
                $model = new \app\inc\Model();
                $sql = "UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id";
                $values = array(":lastcheck" => 0, ":id" => $jobId);
                $res = $model->prepare($sql);
                try {
                    $res->execute($values);
                } catch (\PDOException $e) {
                    print_r($e);
                }
                exit();
            }

            print "Data in existing table deleted.\n\n";
        }
    } else {

        print "Overwrite is enabled.\n\n";

        $o = "-overwrite";
    }

    //die($getFunction(false, $o));

    exec($cmd = $getFunction(false, $o) . ' 2>&1', $out, $err);

    if ($err) {
        print "Error " . $err . "\n\n";
        $pass = false;
    } else {

        print "Wet run command:\n";

        print $cmd . "\n\n";

        if ($grid == null) {
            foreach ($out as $line) {
                if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
                    $pass = false;
                    break;
                }
            }
        }
    }
}

if ($pass) {
    print $url . " imported to " . $schema . "." . $safeName . "\n\n";
    $sql = "UPDATE jobs SET lastcheck=:lastcheck, lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id";
    $values = array(":lastcheck" => 1, ":id" => $jobId);
    print_r(\app\controllers\Tilecache::bust($schema . "." . $safeName));


} else {
    print_r(array_slice($out, -20, 20, true));

    print "\n\n";

    $sql = "UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id";
    $values = array(":lastcheck" => 0, ":id" => $jobId);

    # Output the first few lines of file
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

    try {
        $client = new PostmarkClient("55a76fbe-5b67-43a2-ba27-2104b5866330");

    } catch (PostmarkException $ex) {
        echo $ex->httpStatusCode;
        echo $ex->postmarkApiErrorCode;

    } catch (Exception $generalException) {
        // A general exception is thown if the API
    }

    if (App::$param["notification"]) {

        $text =
        "Job id: {$jobId}\n" .
        "Database: {$db}\n" .
        "Schema: {$schema}\n" .
        "table: {$safeName}\n".
        "Url: {$url}\n";

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

# Add extra field and insert values
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