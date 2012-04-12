<?php
ini_set("display_errors", "On");
error_reporting(3);
session_start();
include '../../../conf/main.php';
include 'libs/oauth/EpiCurl.php';
include 'libs/oauth/EpiOAuth.php';
include 'libs/oauth/EpiTwitter.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/tables.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
$postgisdb = $parts[3];
echo $postgisdb;
?>
<!DOCTYPE html>
<html >
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
    <meta property="fb:app_id" content="102083723254088">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
	<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script>
	<script type="text/javascript">

	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', 'UA-28178450-1']);
	  _gaq.push(['_setDomainName', 'mygeocloud.com']);
	  _gaq.push(['_trackPageview']);
	
	  (function() {
	    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	
	</script>