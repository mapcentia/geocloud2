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
include ("inc/lp_fields.php");
include ("inc/lp_ref.php");

preg_match('/[0-9]+/',$_REQUEST['plannr'],$matches);
$_REQUEST['plannr'] = $matches[0];
//$_REQUEST['plannr'] = "5033";

$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","plannr='{$_REQUEST['plannr']}'");
$row = $response['data'][0];

$table = new table("lokalplaner.lpdelplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","lokplan_id='{$row['planid']}' ORDER by delnr");
$rowsLpDel = $response['data'];
if (!is_array($rowsLpDel)) $rowsLpDel = array();
//print_r($rowsLpDel);
