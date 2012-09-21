<?php
ini_set("display_errors", "On");
error_reporting(3);

include '../../../../../conf/main.php';
include 'libs/functions.php';
$postgisdb="cowitrack";
$db = new postgis();
$sql = "INSERT INTO dhl (id,mytime,the_geom,speed,accuracy) VALUES('".strtolower($_POST['id'])."',{$_POST['mytime']},geomfromtext('POINT({$_POST['lon']} {$_POST['lat']})',4326),{$_POST['speed']},{$_POST['accuracy']})";
if ($_POST['id']) {
	$db->execQuery($sql,"PDO");
}
echo $sql;

