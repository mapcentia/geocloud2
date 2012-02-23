<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

$table = new databases();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
//print_r($obj);

switch ($parts[4]){
	case "addschema":
		$response = $table->createSchema($_POST['schema']);
		break;
	case "getschemas":
		$response = $table->listAllSchemas($_POST['schema']);
		break;
}
include_once("../server_footer.inc");
