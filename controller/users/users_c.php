<?php
include("../../header.php");
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

$user = new users($parts[3]);

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
//print_r($parts);
//print_r($obj);
switch ($parts[4]){
	case "updatepw": // All tables
		$response = $user->updatePw($_POST['pw']);
	break;
}
include_once("../server_footer.inc");
