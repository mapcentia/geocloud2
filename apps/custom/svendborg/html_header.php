<?php
ini_set("display_errors", "On");
error_reporting(3);
session_start();
include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'model/tables.php';
include 'inc/fields.php';
$postgisdb = "mobreg";
?>
<!DOCTYPE html>
<html >
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
	
	<script src="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dojo/dojo.xd.js"
        djConfig="parseOnLoad: true"></script>
	<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js">
	</script>
	<script type="text/javascript" src="/js/ext/ext-all.js">
	</script>
	<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js">
	</script>
	<link rel="stylesheet" type="text/css" href="http://ajax.googleapis.com/ajax/libs/dojo/1.6/dijit/themes/claro/claro.css">
	<link rel="StyleSheet" href="/css/themecss.php?strThemes=115240000000005,115240000000007,115240000000006,115240000000005,115240000000007" type="text/css">
	<style>
	td.content-cell {border-top:1px solid #dddddd;padding:5px 20px 5px 5px;font-size:80%;vertical-align:text-top;}
	body {font-family:arial,verdana;padding:0px;margin:0px;background-color: #EFF0F0;}
	</style>
  </head>