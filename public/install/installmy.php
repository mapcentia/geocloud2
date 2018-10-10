<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *  
 */

include("../../app/conf/Connection.php");
include("../../app/inc/Model.php");
include("../../app/models/Database.php");
include("../../app/models/Dbchecks.php");
include("sqls.php");

\app\conf\Connection::$param["postgisdb"]  = $_REQUEST['db'];
$conn = new \app\inc\Model();
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
	echo "Something went wrrtong; {$conn->PDOerror[0]}";
	$conn->rollback();

}
if (!$conn->PDOerror[0]) {
	$conn->commit();
}

