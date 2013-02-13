<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

$db = new databases();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}

switch ($parts[4]){
	case "addschema":
		$response = $db->createSchema($_POST['schema']);
		break;
	case "getschemas":
		$response = $db->listAllSchemas($_POST['schema']);
		break;
	case "doesdbexist":
		$response = $db->doesDbExist($parts[5]);
	break;
}
include_once("../server_footer.inc");
