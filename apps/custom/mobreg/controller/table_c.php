<?php
$table = new table("public.tforms115770000000094_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
//$response_INI_bygning = $table->getRecords(NULL,"*");
$response_INI_bygning = $table->getRecords(NULL,"*","irowid='{$parts[4]}'");
$row_INI_bygning = $response_INI_bygning['data'][0];

$table = new table("public.tforms115770000001933_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
$response_INI_bnr = $table->getRecords(NULL,"*","fieldkey='{$row_INI_bygning['bnr_nuuk']}'");
$row_INI_bnr = $response_INI_bnr['data'][0];

$table = new table("public.tforms115770000000004_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
$response_INI_aktiviteter = $table->getRecords(NULL,"*","bygning_nuuk='{$row_INI_bnr['fieldkey']}'");
$rows_INI_aktiviteter = $response_INI_aktiviteter['data'];

$table = new table("public.tforms115770000000108_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
$response_INI_standardbygningsdele = $table->getRecords(NULL,"*");
$rows_INI_standardbygningsdele = $response_INI_standardbygningsdele['data'];

$table = new table("public.tforms115770000000324_join");
$table->execQuery("set client_encoding='UTF8'","PDO");
$response_INI_standardgrupper_af_bygningsdele = $table->getRecords(NULL,"*");
$rows_INI_standardgrupper_af_bygningsdele = $response_INI_standardgrupper_af_bygningsdele['data'];





