<?php
ini_set("display_errors", "On");
error_reporting(3);

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;
use \app\inc\Util;
use \app\models\Database;
use \app\models\Layer;


header("Content-type: text/plain");


include_once(__DIR__ . "/../../conf/App.php");

$db = "mydb";
$schema = "fot_test";
$inputRel = "kommuner";
$outputTable = "grid";
$grid = "grid2";
$typeName = "VANDLOEBSMIDTE";
$importTable = "vandloebsmidte";
$useGfs = true;
$geomType = "Linestring";
$size = 2000;

new \app\conf\App();
Database::setDb($db);
$database = new Model();


print_r(\app\conf\Connection::$param);

$sql = "DROP TABLE {$schema}.{$outputTable}";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "DROP TABLE {$schema}.{$grid}";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "CREATE TABLE {$schema}.{$outputTable} AS SELECT st_fishnet('{$schema}.{$inputRel}','the_geom',{$size}, 25832)";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "ALTER TABLE {$schema}.{$outputTable} ALTER st_fishnet TYPE geometry('Polygon', 25832)";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "ALTER TABLE {$schema}.{$outputTable} ADD gid SERIAL";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "ALTER TABLE {$schema}.{$outputTable} ADD gid SERIAL";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "CREATE TABLE {$schema}.{$grid} AS SELECT $schema.grid.*
            FROM
              {$schema}.{$outputTable} LEFT JOIN
              {$schema}.{$inputRel} AS {$inputRel} ON
              st_intersects(grid.st_fishnet,{$inputRel}.the_geom)
            WHERE {$inputRel}.gid IS NOT NULL";
echo $sql . "\n";
$database->execQuery($sql);

$sql = "SELECT gid,ST_XMIN(st_fishnet), ST_YMIN(st_fishnet), ST_XMAX(st_fishnet), ST_YMAX(st_fishnet) FROM {$schema}.{$grid}";
//echo $sql . "\n";
$res = $database->execQuery($sql);

while ($row = $database->fetchRow($res)) {
    print_r($row);
    $bbox = "{$row["st_xmin"]},{$row["st_ymin"]},{$row["st_xmax"]},{$row["st_ymax"]}";
    $wfsUrl = "http://kortforsyningen.kms.dk/fot2007_nohistory_test?LOGIN=Kommune461&PASSWORD=Jkertyu10&SERVICE=WFS&VERSION=1.0.0&REQUEST=GetFeature&TYPENAME={$typeName}&SRSNAME=urn:ogc:def:crs:EPSG::25832&BBOX=";
    print_r($wfsUrl . $bbox);
    Util::wget($wfsUrl . $bbox);

    file_put_contents("/var/www/geocloud2/public/logs/" . $row["gid"] . ".gml", Util::wget($wfsUrl . $bbox));
    if ($useGfs) {
        file_put_contents("/var/www/geocloud2/public/logs/" . $row["gid"] . ".gfs", file_get_contents("/var/www/geocloud2/app/conf/{$typeName}.gfs"));
    }

    $cmd = "PGCLIENTENCODING={$encoding} ogr2ogr " .
        "-skipfailures " .
        "-append " .
        "-dim 2 " .
        "-lco 'GEOMETRY_NAME=the_geom' " .
        "-lco 'FID=gid' " .
        "-lco 'PRECISION=NO' " .
        "-a_srs 'EPSG:25832' " .
        "-f 'PostgreSQL' PG:'host=" . Connection::$param["postgishost"] . " user=" . Connection::$param["postgisuser"] . " password=" . Connection::$param["postgispw"] . " dbname=" . Connection::$param["postgisdb"] . "' " .
        "/var/www/geocloud2/public/logs/" . $row["gid"] . ".gml " .
        "-nln {$schema}.{$importTable} " .
        "-nlt {$geomType}";
    exec($cmd, $out, $err);
    print_r($out);
    unlink("/var/www/geocloud2/public/logs/" . $row["gid"] . ".gml");

}

die("END\n");