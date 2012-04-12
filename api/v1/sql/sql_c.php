<?php
include("../server_header.inc");
$api = new sqlapi();
$api->execQuery("set client_encoding='UTF8'","PDO");
$response = $api->sql($_REQUEST['q']);
include_once("../server_footer.inc");