<?php
include '../../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';

header('Content-Type: text/html');
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

$postgisdb = $parts[6]; // We change the db to user
$db = new postgis();
$db->execQuery("set client_encoding='UTF8'","PDO");
$response = $db->sql("SELECT gid,plannr,plannavn,anvendelsegenerel,zonestatus,planstatus,doklink from lokalplaner.lokalplan_vedtaget union select gid,plannr,plannavn,anvgen,zone,status,html from lokalplaner.lpplandk2_view order by planstatus");

echo json_encode($response);