<?php
include 'conf/main.php';
session_name($sessionName);
session_set_cookie_params(0, '/',".".$domain);
session_start();
include 'libs/oauth/EpiCurl.php';
include 'libs/oauth/EpiOAuth.php';
include 'libs/oauth/EpiTwitter.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/users.php';
include 'model/databases.php';
include 'model/classes.php';
include 'model/wmslayers.php';
include 'model/settings_viewer.php'; // we need to get pw for http authentication
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
 
//$_SESSION['screen_name'] = $parts[2];
$postgisdb=$_SESSION['screen_name'];

if ($parts[1]=="store" || $parts[1]=="editor") {
	$db = new databases();
	if (!$parts[2]) {
		die("<script>window.location='/?db=false'</script>");
	}
	if (!$db->doesDbExist($parts[2])) {
		if ($db->doesDbExist(postgis::toAscii($parts[2],NULL,"_"))) {
			die("<script>window.location='/store/".postgis::toAscii($parts[2])."'</script>");
		}
		else{
			die("<script>window.location='/?db=false'</script>");
		}
	}
	include("inc/oauthcheck.php");
}
?>
<!DOCTYPE html>
<html >
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Analyze and visualize your data. Use a powerful API for adding maps to your own apps." />
	<meta name="description" content="The core component of MyGeoCloud is the rock solid PostGIS database with endless possibilities." />
	<meta name="description" content="With a powerful adminstration tool you can manage your data online." />
	<meta name="keywords" content="map, visualize, geo, cloud, analyze, gis, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
