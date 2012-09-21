<?php
set_time_limit(0);
include("../server_header.inc");
if ($_REQUEST['srs']){
	$srs = $_REQUEST['srs'];
}
else {
	$srs = "900913";
}
$api = new sqlapi($srs);
$api->execQuery("set client_encoding='UTF8'","PDO");
$response = $api->sql($_REQUEST['q']);
include_once("../server_footer.inc");