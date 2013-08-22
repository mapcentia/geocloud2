<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");


$wmslayer = new wmslayers($parts[5]);

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}

switch ($parts[4]){
	case "get":
		$response = $wmslayer -> get();
	break;
	case "update":
		$response = $wmslayer -> update($_POST['data']);
		makeMapFile($_SESSION['screen_name']);
	break;
	case "getfields":
		$response = $wmslayer -> getfields();
	break;
}
include_once("../server_footer.inc");
