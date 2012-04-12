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
?>