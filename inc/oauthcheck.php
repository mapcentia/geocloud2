<?php
$postgisdb = $parts[2];
if ($_SESSION["oauth_token"]){ // User signed with Twitter
	if ($_SESSION['screen_name']!=$parts[2]) {
		include('http_basic_authen.php');
	}	
}
elseif ($_SESSION['screen_name']=="_".postgis::toAscii($_SERVER['REMOTE_ADDR'])) { // User signin with IP
	$_SESSION["oauth_token"] = true; // We set to true, so it will pass tests
}
else { // User not sign
	include('http_basic_authen.php');
}

$_SESSION['screen_name'] = $parts[2];