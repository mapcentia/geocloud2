<?php

$db = new app\model\databases();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}

switch ($request[4]){
	case "addschema":
		$response = $db->createSchema($_POST['schema']);
		break;
	case "getschemas":
		$response = $db->listAllSchemas($_POST['schema']);
		break;
	case "doesdbexist":
		$response = $db->doesDbExist($request[5]);
	break;
}
