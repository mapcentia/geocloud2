<?php
include("../conf/main.php");
include("../libs/functions.php");
include("../model/databases.php");
include("sql.php");
//echo "test";
$dbList = new databases();
$arr = $dbList->listAllDbs();
foreach($arr['data'] as $db) {
	if ($db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template" AND $db!="mhoegh" AND $db!="mygeocloud") {
		$postgisdb = $db;
		$conn = new postgis();
		foreach($sqls as $sql) {
			$result = $conn->execQuery($sql,"PDO","transaction");
			echo "<p>{$conn->PDOerror[0]} SQL loaded in {$db}</p>";
			
			$conn->PDOerror = NULL;
		}
		echo "<p>---------------------</p>";
		$conn->db=NULL;
		$conn = NULL;
	}
	
}
?>