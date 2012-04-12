<?php
session_start();
ini_set("display_errors", "On");
error_reporting(3);

include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'model/tables.php';
$postgisdb = $parts[3];
?>
<!DOCTYPE html>
<html>
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
	
	<script src="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojo/dojo.xd.js" djConfig="parseOnLoad: true"></script>
	<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dijit/themes/claro/claro.css">
	<!--
	<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/js/ext/adapter/ext/ext-base.js"></script>
	<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/js/ext/ext-all.js"></script>
	
	<script type="text/javascript" src="beta.mygeocloud.cowi.webhouse.dk/js/jquery/1.6.4/jquery.min.js"></script>
	
	<script type="text/javascript" src="http://beta.mygeocloud.cowi.webhouse.dk/js/bootstrap/js/bootstrap.min.js"></script>
	<link rel="stylesheet" type="text/css" href="http://beta.mygeocloud.cowi.webhouse.dk/js/bootstrap/css/bootstrap.min.css">
	-->
 </head>