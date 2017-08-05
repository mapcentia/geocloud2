<?php
ini_set("display_errors", "Off");
error_reporting(3);

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;
use \app\inc\Util;
use \app\models\Database;
use \app\models\Layer;


header("Content-type: text/plain");


include_once(__DIR__ . "/../../conf/App.php");

$db = $argv[1];
$schema = $argv[2];
$url = $argv[3];
$importTable = $argv[4];
$geomType = $argv[5];
$grid = $argv[6];
$overwrite = $argv[7];
$id = $argv[8] ?: "gml_id";
$gfs = $argv[9];



new \app\conf\App();
Database::setDb($db);
$database = new Model();
$database->connect();

$sql = "SELECT 1 FROM {$grid} LIMIT 1";
$database->execQuery($sql);
if ($database->PDOerror) {
    throw new Exception("Grid table \"{$grid}\" does not exist.");
}

if ($overwrite) {
    $sql = "DROP TABLE {$schema}.{$importTable}";
    $res = $database->prepare($sql);
    //print "SQL run:\n";
    //print $sql . "\n\n";
    try {
        $res->execute();
    } catch (\PDOException $e) {

    }
}

$sql = "SELECT gid,ST_XMIN(st_fishnet), ST_YMIN(st_fishnet), ST_XMAX(st_fishnet), ST_YMAX(st_fishnet) FROM {$grid}";
//echo $sql . "\n";
$res = $database->execQuery($sql);

function which($cmd)
{
    $cmd = "/usr/bin/which {$cmd}";
    exec($cmd . ' 2>&1', $out, $err);
    return $out[0];
}

$pass = true;

while ($row = $database->fetchRow($res)) {
    $bbox = "{$row["st_xmin"]},{$row["st_ymin"]},{$row["st_xmax"]},{$row["st_ymax"]}";
    $wfsUrl = $url . "&BBOX=";
    $gmlName = $importTable . "-" . $row["gid"] . ".gml";

    Util::wget($wfsUrl . $bbox);

    file_put_contents("/var/www/geocloud2/public/logs/" . $gmlName, Util::wget($wfsUrl . $bbox));
    if ($gfs) {
        file_put_contents("/var/www/geocloud2/public/logs/" . $importTable . "-" . $row["gid"] . ".gfs", file_get_contents($gfs));
    }

    $cmd = "PGCLIENTENCODING={$encoding} " . which("ogr2ogr") ." " .
        "-skipfailures " .
        "-append " .
        "-dim 2 " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:25832' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . Connection::$param["postgisdb"] . "' " .
        "/var/www/geocloud2/public/logs/" . $gmlName . " " .
        "-nln {$schema}.{$importTable} " .
        "-nlt {$geomType}";
    exec($cmd . ' 2>&1', $out, $err);

    foreach ($out as $line) {
        if (strpos($line, "FAILURE") !== false) {
            $pass = false;
            break;
        }
    }

    if (!$pass) {

        foreach($out as $line) {
            echo $line."\n";
        }

        echo  "\nOutputting the first few lines of the file:\n\n";

        $handle = @fopen("/var/www/geocloud2/public/logs/" . $gmlName, "r");
        if ($handle) {
            for ($i = 0; $i < 40; $i++) {
                $buffer = fgets($handle, 4096);
                echo $buffer;
            }
            if (!feof($handle)) {
                echo  "Error: unexpected fgets() fail\n";
            }
            fclose($handle);
        }

        unlink("/var/www/geocloud2/public/logs/" . $gmlName);

        exit(1);
    }

    unlink("/var/www/geocloud2/public/logs/" . $gmlName);

    $sql = "ALTER TABLE {$schema}.{$importTable} DROP CONSTRAINT IF EXISTS {$schema}_{$importTable}_unique_id";
    //echo $sql . "\n";
    $database->execQuery($sql);
    print_r($database->PDOerror);

    $sql = "ALTER TABLE {$schema}.{$importTable} ADD CONSTRAINT {$schema}_{$importTable}_unique_id UNIQUE ({$id})";
    //echo $sql . "\n";
    $database->execQuery($sql);
    print_r($database->PDOerror);

    echo ".";
}
echo "\n";
exit(0);


