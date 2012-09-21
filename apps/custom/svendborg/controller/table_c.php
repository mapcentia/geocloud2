<?php

$table = new table("{$_REQUEST['schema']}.tforms{$_REQUEST['formid']}_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
$response = $table->getRecords(NULL,"*","irowid='{$_REQUEST['id']}'");
$row = $response['data'][0];
if (empty($row)) {
	die("empty");
}
//print_r($row);
$checkTable = new table("public.save_check");
$checkTable->execQuery("set client_encoding='UTF8'","PDO");
$checkResponse = $checkTable->getRecords(NULL,"*","irowid={$_REQUEST['id']}");
$checkRow = $checkResponse['data'][0];
//print_r($checkRow);