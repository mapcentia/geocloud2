<?php
include("../conf/main.php");
include("../libs/functions.php");
include("../models/Database.php");
include("sql.php");
$dbList = new databases();
$arr = $dbList->listAllDbs();


foreach($arr['data'] as $db) {
	if ($db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template") {
		if (1===1) { 
			$postgisdb = $db;
			$conn = new postgis();
			foreach($sqls as $sql) {
				$result = $conn->execQuery($sql,"PDO","transaction");
				echo "{$conn->PDOerror[0]} SQL loaded in {$db}\n";
					
				$conn->PDOerror = NULL;
			}
			echo "---------------------\n<br>";
			$conn->db=NULL;
			$conn = NULL;
		}
	}
}
