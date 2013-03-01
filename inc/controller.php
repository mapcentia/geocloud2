<?php
/**
 *
 */
//include '../../libs/oauth/EpiCurl.php';
//include '../../libs/oauth/EpiOAuth.php';
//include '../../libs/oauth/EpiTwitter.php';
include '../../conf/main.php';
include 'libs/functions.php';
include 'model/users.php';
include 'model/classes.php';
include 'model/wmslayers.php';
include 'model/databases.php';
include 'model/tables.php';
include 'model/settings_viewer.php';
include 'wms/mapfile.map.php';
include 'model/shapefile.php';
include 'model/sqlapi.php';
include 'model/geometry_columns.php';
session_name($sessionName);
session_set_cookie_params(0, '/', "." . $domain);
session_start();
header('Content-Type: text/plain');
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");// Date in the past

class Controller {
	public $urlParts;
	function __construct() {

	}

	public function getUrlParts() {
		return explode("/", str_replace("?" . $_SERVER['QUERY_STRING'], "", $_SERVER['REQUEST_URI']));
	}

	public function auth($user) {
		global $userHostName;
		if (!$_SESSION['auth'] || ($_SESSION['screen_name'] != $user)) {
			$_SESSION['auth'] = null;
			$_SESSION['screen_name'] = null;
			die("<script>window.location='{$userHostName}/user/login'</script>");
		}
	}

	public function toJSON($response) {
		$callback = $_GET['jsonp_callback'];
		if ($callback) {
			echo $callback . '(' . json_encode($response) . ');';
		} else {
			echo json_encode($response);
		}
	}
}
