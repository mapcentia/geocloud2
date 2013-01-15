<?php
//include("../../header.php");
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

$settings_viewer = new Settings_viewer();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
//print_r($parts);
//print_r($obj);
switch ($parts[4]){
	case "get": // All tables
		$response = $settings_viewer->get();
	break;
	case "update": // All tables
		$response = $settings_viewer->update($_POST);
	break;
	case "updatepw": // All tables
		$response = $settings_viewer->updatePw($_POST['pw']);
	break;
	case "updateapikey": // All tables
		$response = $settings_viewer->updateApiKey();
	break;
	
}
include_once("../server_footer.inc");
