<?php
die("What are u doing?");
include("../conf/main.php");
include("../libs/functions.php");
include("../models/Database.php");
//echo "test";
$dbList = new databases();
$arr = $dbList->listAllDbs();
$postgisdb = "mygeocloud";
$conn = new postgis();

foreach($arr['data'] as $db) {
	if ($db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template" AND $db!="mhoegh" AND $db!="mygeocloud") {
		$sql = "DROP DATABASE {$db}";
			$result = $conn->execQuery($sql,"PDO","transaction");
			echo "<p>{$conn->PDOerror[0]} SQL loaded in {$db}</p>";
			
			$conn->PDOerror = NULL;
		echo "<p>---------------------</p>";
		$conn->db=NULL;
		$conn = NULL;
	}
	
}