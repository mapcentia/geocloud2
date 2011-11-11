<?php
include("../conf/main.php");
include("../libs/functions.php");
include("../model/databases.php");
include("sqls.php");

$postgisdb = $_REQUEST['db'];
$conn = new postgis();
try {
	$conn->connect();
} catch (Exception $e) {
	echo $e->getMessage()."\n";
	die();
}

$conn->begin();
//Try to drop schema
/*
$result = $conn->execQuery("DROP SCHEMA settings CASCADE","PDO","transaction");
if (!$conn->PDOerror[0]) {
	echo "Schema dropped<br/>";
}
else {
	echo "Something went wrong; {$conn->PDOerror[0]}";
	$conn->rollback();
}
*/

//Try to create schema
$result = $conn->execQuery($sqls['schema'],"PDO","transaction");
if (!$conn->PDOerror[0]) {
	echo "Schema created<br/>";
}
else {
	echo "Something went wrong; {$conn->PDOerror[0]}";
	$conn->rollback();
}

//Try to create view
$result = $conn->execQuery($sqls['view'],"PDO","transaction");
if (!$conn->PDOerror[0]) {
	echo "View created<br/>";
}
else {
	echo "Something went wrong; {$conn->PDOerror[0]}";
	$conn->rollback();

}
if (!$conn->PDOerror[0]) {
	$conn->commit();
}

