<?php
include("../conf/main.php");
include("../libs/functions.php");
include("../model/databases.php");
include("../model/dbchecks.php");
include '../model/settings_viewer.php';

$dbList = new databases();
try {
	$arr = $dbList->listAllDbs();
} catch (Exception $e) {
	echo $e->getMessage()."\n";
	die();
}  
$postgisdb="mygeocloud";
$postgis = new postgis();
$i=1;
foreach($arr['data'] as $db) {

	if ($db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template") {
		$postgisdb = $db;
		//$dbc = new dbcheck();
		$viewer = new Settings_viewer();
		$arr = $viewer->get();
		$sql = "INSERT INTO users(screenname,pw) VALUES('{$db}','{$arr['data']['pw']}')";
		$postgis->execQuery($sql);
		echo $sql."\n";
		
		$i++;
	}
//if ($i>10) die();
}



 
