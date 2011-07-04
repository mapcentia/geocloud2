<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");


$wmslayer = new wmslayers();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}

switch ($parts[4]){
	case "get":
		$response = $wmslayer -> get($parts[5]);
	break;
	case "update":
		$response = $wmslayer -> update($parts[5],$_POST['data']);
		makeMapFile($_SESSION['screen_name']);
	break;
	case "getfields":
		$response = $wmslayer -> getfields($parts[5]);
	break;
}
include_once("../server_footer.inc");
