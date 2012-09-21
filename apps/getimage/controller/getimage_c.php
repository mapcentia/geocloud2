<?php
include '../../conf/main.php';
include '../../libs/functions.php';
include '../../model/tables.php';
include '../../inc/user_name_from_uri.php';
//print_r($parts);
$postgisdb = $parts[3];
$postgisschema = $parts[4];
$table = new table($parts[4].".".$parts[5]);
$response = $table->getRecords(NULL,$parts[6],"gid={$parts[7]}");
$data = base64_decode($response['data'][0][$parts[6]]);
