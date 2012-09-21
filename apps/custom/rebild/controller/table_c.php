<?php
session_start();
ini_set("display_errors", "On");
error_reporting(3);

include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'model/tables.php';
$postgisdb = $parts[3];

$table = new table("vandforsyningsplan.anlaeg_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","id='{$_REQUEST['id']}'");
$row = $response['data'][0];

$response = $table->getRecords(NULL,"id,navn_paa_vandvaerk,html","overordnet_id='{$row['overordnet_id']}'");
$rowOverordnet = $response['data'];
//print_r($rowOverordnet);

$table = new table("vandforsyningsplan.boringer");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","plant_id='{$_REQUEST['id']}'");
$rowBoringer = $response['data'];
//print_r($rowBoringer);

$table = new table("vandforsyningsplan.maengde");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$table->execQuery("set datestyle TO SQL,DMY","PDO");
//$response = $table->getRecords(NULL,"*","plantid='{$_REQUEST['id']}' AND startdate::date>='01/01/2010'");
$response = $table->getRecords(NULL,"*","plantid='{$row['overordnet_id']}'");
$rowMaengde = $response['data'];

$i=0;
foreach($rowMaengde as $value) {
	if (is_int($i/2)) $year[] = $value['time'];
	else $year[] = "";
	$amount[] = $value['amount'];
	$i++;
}

