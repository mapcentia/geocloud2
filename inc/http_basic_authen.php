<?php
$settings_viewer = new Settings_viewer();
$response = $settings_viewer->get();

// mod_php
if (isset($_SERVER['PHP_AUTH_USER'])) {
	$username = $_SERVER['PHP_AUTH_USER'];
	$password = $_SERVER['PHP_AUTH_PW'];

	// most other servers
} elseif (isset($_SERVER['HTTP_AUTHENTICATION'])) {

	if (strpos(strtolower($_SERVER['HTTP_AUTHENTICATION']), 'basic')===0)
		list($username, $password) = explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6)));
}
if (is_null($username)) {
	header('WWW-Authenticate: Basic realm="My Realm"');
	header('HTTP/1.0 401 Unauthorized');
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	// Text to send if user hits Cancel button
	die("Could not authenticate you 1");

} elseif (md5($password)!=$response['data']['pw']) {
	header('WWW-Authenticate: Basic realm="My Realm"');
	header('HTTP/1.0 401 Unauthorized');
	header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
	header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past
	die("Could not authenticate you 2");
}
else {


}

?>
