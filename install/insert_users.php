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
<<<<<<< HEAD
}

$postgisdb = "mygeocloud";
$postgis = new postgis();
=======
}AS<DFZGJK,  
>>>>>>> 356c5f7460ef52f2232faf125b07e743dffbf5d9
$i=1;
foreach($arr['data'] as $db) {

	if ($db!="mygeocloud" AND $db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template") {
		echo "{$i}<br/>";
		$postgisdb = $db;
		//$dbc = new dbcheck();
		$viewer = new Settings_viewer();
		$arr = $viewer->get();
		//print_r($arr);
		$sql = "INSERT INTO users(screenname,pw) VALUES('{$db}','{$arr['data']['pw']}')";
		echo $sql."<br>";
		$postgis->execQuery($sql);
		$postgis->PDOerror[0];
		$i++;
	}
//if ($i>10) die();
}



 
