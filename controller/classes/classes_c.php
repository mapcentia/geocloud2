<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

$class = new _class();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
//print_r($obj);

switch ($parts[4]){
	case "getall":
		$response = $class -> getAll($parts[5]);
	break;
	case "get":
		$response = $class -> get($parts[5]);
	break;
	case "update":
		$response = $class -> update($parts[5],$_POST['data']);
		//makeMapFile($_SESSION['screen_name']);
	break;
	case "insert":
		$response = $class -> insert($parts[5]);
		//makeMapFile($_SESSION['screen_name']);
	break;
	case "destroy":
		$response = $class -> destroy($obj->data);
		//makeMapFile($_SESSION['screen_name']);
	break;
}
include_once("../server_footer.inc");
