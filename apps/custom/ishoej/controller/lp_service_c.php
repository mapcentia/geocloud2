<?php
preg_match('/(?<=\s)[0-9|\.]+/',$_GET['plannr'],$matches);
$plannr = $matches[0];
$table = new table("lokalplaner.lpplandk2_join");
$table->execQuery("set client_encoding='LATIN1'","PDO");
$response = $table->getRecords(NULL,"*","plannr='{$plannr}'");
$row = $response['data'][0];
$time = time();