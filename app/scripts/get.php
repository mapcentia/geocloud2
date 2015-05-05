<?php
include_once(__DIR__ . "/../conf/App.php");
new \app\conf\App();

use \app\conf\App;
use \app\conf\Connection;



$db = $argv[1];
$schema = $argv[2];
$safeName = $argv[3];
$url = $argv[4];
$srid = $argv[5];
$type = $argv[6];
$encoding = $argv[7];
$jobId = $argv[8];
$deleteAppend = $argv[9];
$extra = base64_decode($argv[10]);

$dir = App::$param['path'] . "app/tmp/" . $db . "/__vectors";
$tempFile = md5(microtime() . rand()) . ".gml";

if (!file_exists(App::$param['path'] . "app/tmp/" . $db)) {
    @mkdir(App::$param['path'] . "app/tmp/" . $db);
}

if (!file_exists($dir)) {
    @mkdir($dir);
}

if (is_numeric($safeName[0])) {
    $safeName = "_" . $safeName;
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
$fp = fopen($dir . "/" . $tempFile, 'w+');
curl_setopt($ch, CURLOPT_FILE, $fp);
curl_exec($ch);
curl_close($ch);
fclose($fp);

if ($deleteAppend == "1") {
    \app\models\Database::setDb($db);
    $table = new \app\models\Table($schema . "." . $safeName);
    if (!$table->exits) { // If table doesn't exists, when do not try to delete/append
        $o = "-overwrite";
    } else {
        $o = "-append";
        $sql = "DELETE FROM {$schema}.{$safeName}";
        $res = $table->prepare($sql);
        try {
            $res->execute($values);
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
    }
} else {
    $o = "-overwrite";
}

$cmd = "PGCLIENTENCODING={$encoding} ogr2ogr " .
    $o . " " .
    "-dim 2 " .
    "-lco 'GEOMETRY_NAME=the_geom' " .
    "-lco 'FID=gid' " .
    "-lco 'PRECISION=NO' " .
    "-a_srs 'EPSG:{$srid}' " .
    "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . " active_schema=" . $schema . "' " .
    "'" . $dir . "/" . $tempFile . "' " .
    "-nln {$safeName} " .
    (($type == "AUTO") ? "" : "-nlt {$type}") .
    "";

exec($cmd . ' 2>&1', $out, $err);
$pass = true;
foreach ($out as $line) {
    if (strpos($line, "FAILURE") !== false || strpos($line, "ERROR") !== false) {
        $pass = false;
        break;
    }
}

if ($pass) {
    print $cmd . "\n";
    print $url . " imported to " . $schema . "." . $safeName;
    $sql = "UPDATE jobs SET lastcheck=:lastcheck, lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id";
    $values = array(":lastcheck" => 1, ":id" => $jobId);

} else {
    print $cmd . "\n";
    print_r($out);
    $sql = "UPDATE jobs SET lastcheck=:lastcheck WHERE id=:id";
    $values = array(":lastcheck" => 0, ":id" => $jobId);
}
\app\models\Database::setDb("gc2scheduler");
$model = new \app\inc\Model();
$res = $model->prepare($sql);
try {
    $res->execute($values);
} catch (\PDOException $e) {
    print_r($e);
}

if ($extra) {
    \app\models\Database::setDb($db);
    $model = new \app\inc\Model();
    $fieldObj = json_decode($extra);

    $fieldName = $fieldObj->name;
    $fieldType = $fieldObj->type ?: "varchar";
    $fieldValue = $fieldObj->value;
    $sql = "ALTER TABLE \"{$schema}\".\"{$safeName}\" ADD COLUMN {$fieldName} {$fieldType}";
    $res = $model->prepare($sql);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        print_r($e);
    }
    $sql = "UPDATE \"{$schema}\".\"{$safeName}\" SET {$fieldName} =:value";
    $res = $model->prepare($sql);
    try {
        $res->execute(array(":value" => $fieldValue));
    } catch (\PDOException $e) {
        print_r($e);
    }
}