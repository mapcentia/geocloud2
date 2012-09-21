<?php
$plannr = trim(preg_replace('/[a-z]|[A-Z]/',"",$_GET['plannr']));

$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","plannr='{$plannr}'");
$row = $response['data'][0];
$time = time();