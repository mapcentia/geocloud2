<?php
app\inc\Input::getPath();
$settings_viewer = new app\model\Settings_viewer();

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}

switch ($request[4]){
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
