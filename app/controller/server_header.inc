<?php
include 'app/conf/main.php';
session_name($sessionName);
session_set_cookie_params(0, '/',".".$domain);
session_start();

include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/users.php';
include 'model/classes.php';
include 'model/wmslayers.php';
include 'model/databases.php';

include 'model/tables.php';
include 'model/settings_viewer.php';
include 'wms/mapfile.php.map';
include 'wms/tilecache.cfg.php';
include 'model/shapefile.php';
include 'model/sqlapi.php';
include 'model/geometry_columns.php';



$postgisdb = $parts[3]; // We change the db to user
$postgisschema = $_SESSION['schema'];
//$postgisuser = $_SESSION['screen_name']; // We change the user

switch($_REQUEST['type'])
{
	case "xml":
		header('Content-Type: text/xml');
	break;
	
	case "json":
		header('Content-Type: text/plain');
	break;
	case "text":
		header('Content-Type: text/plain');
	break;
	default:
		header('Content-Type: text/html');
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past


$postgisObject = new postgis();
