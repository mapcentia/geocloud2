<?php
include ("inc/lp_fields.php");
include ("inc/lp_ref.php");
$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","planid='{$_REQUEST['planid']}'");
$row = $response['data'][0];

if (empty($row)) {
	die();
}

$table = new table("lokalplaner.lpdelplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","lokplan_id='{$row['planid']}'");
$rowsLpDel = $response['data'];
if (!is_array($rowsLpDel)) $rowsLpDel = array();
//print_r($rowsLpDel);
