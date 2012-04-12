<?php
$table = new table("public.ways");
//$table->execQuery("set client_encoding='latin1'","PDO");
$response = $table->getRecords(NULL,"*","id=$parts[2]");
//print_r($response['data'][0]);
$row = $response['data'][0];