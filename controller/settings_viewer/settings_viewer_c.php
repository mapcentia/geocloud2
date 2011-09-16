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
	case "update_extent": // All tables
		$response = $settings_viewer->update_extent($_POST['layer']);
		makeMapFile($_SESSION['screen_name'],$_POST['layer']);
	break;
	case "updatepw": // All tables
		$response = $settings_viewer->updatePw($_POST['pw']);
	break;
}
include_once("../server_footer.inc");
