<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");



if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
$class = new _class($parts[5]);
//print_r($obj);

switch ($parts[4]){
	case "getall":
		$response = $class -> getAll();
	break;
	case "get":
		$response = $class -> get($parts[6]);
	break;
	case "update":
		$response = $class -> update($parts[6],$obj->data);
		makeMapFile($_SESSION['screen_name']);
	break;
	case "insert":
		$response = $class -> insert();
		makeMapFile($_SESSION['screen_name']);
	break;
	case "destroy":
		$response = $class -> destroy($obj->data);
		makeMapFile($_SESSION['screen_name']);
	break;
}
include_once("../server_footer.inc");
