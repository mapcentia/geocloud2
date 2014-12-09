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

switch ($type) {
    case "Point":
        $type = "point";
        break;
    case "Polygon":
        $type = "multipolygon";
        break;
    case "Line":
        $type = "multilinestring";
        break;
    case "Geometry":
        $type = "geometry";
        break;
    default:
        $type = "PROMOTE_TO_MULTI";
        break;
}
$cmd = "PGCLIENTENCODING={$encoding} ogr2ogr " .
    //(($_REQUEST["ignoreerrors"] == "true") ? "-skipfailures " : "") .
    "-overwrite " .
    "-dim 2 " .
    "-lco 'GEOMETRY_NAME=the_geom' " .
    "-lco 'FID=gid' " .
    "-lco 'PRECISION=NO' " .
    "-a_srs 'EPSG:{$srid}' " .
    "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . $db . " active_schema=" . $schema . "' " .
    "'" . $dir . "/" . $tempFile . "' " .
    "-nln {$safeName} " .
    "-nlt {$type}";

exec($cmd . ' 2>&1', $out, $err);

if (strpos($out[0], "FAILURE") === false && strpos($out[0], "ERROR") === false ) {
    print $url . " imported to " . $schema . "." . $safeName;
    $sql = "UPDATE jobs SET lastcheck=:lastcheck, lasttimestamp=('now'::TEXT)::TIMESTAMP(0) WHERE id=:id";
    $values = array(":lastcheck" => 1, ":id" => $jobId);

} else {
    print_r($cmd);
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
