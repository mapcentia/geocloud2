<?php
//session_start();
include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/settings_viewer.php';
include 'model/sqlapi.php';
header('Content-Type: text/plain; charset=utf-8');
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

$postgisdb = $parts[4]; 
$postgisObject = new postgis();
