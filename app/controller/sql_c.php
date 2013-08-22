<?php
include("../server_header.inc");
$db = new postgis();
$db->execQuery("set client_encoding='UTF8'","PDO");
$response = $db->sql($_REQUEST['q']);
include_once("../server_footer.inc");