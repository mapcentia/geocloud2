<?php
ini_set("display_errors", "On");
error_reporting(3);
session_start();
include 'osm_conf/osm_main.php';
include '../../../conf/main.php';
include 'libs/oauth/EpiCurl.php';
include 'libs/oauth/EpiOAuth.php';
include 'libs/oauth/EpiTwitter.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/tables.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'libs/PgHStore.php';
$postgisdb = $db;
?>
<!DOCTYPE html>
<html lang="en"><head>
	<meta http-equiv="content-type" content="text/html; charset=UTF-8">
    <title>World Trail Map -- Free trails world wide</title>
    <meta name="keywords" content="route, track, waypoint, gps, gpx, trail, road, map, gis, hike, hiking, mountain hiking, alpine hiking, walk, walking, training, sports, measure, distance, maps, earth, gml, kml, georss" />
    <meta name="description" content="Web site with free hiking trails world wide">
    <meta name="author" content="">
    <meta property="fb:app_id" content="102083723254088">
	<script src="http://connect.facebook.net/en_US/all.js#xfbml=1"></script>

    <!-- Le HTML5 shim, for IE6-8 support of HTML elements -->
    <!--[if lt IE 9]>
      <script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
    <![endif]-->

    <!-- Le styles -->
    <link href="http://test.mygeocloud.com/js/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style type="text/css">
      body {
        padding-top: 60px;
        padding-bottom: 40px;
      }
    </style>
    <link href="http://test.mygeocloud.com/js/bootstrap/css/bootstrap-responsive.min.css" rel="stylesheet">
    <script type="text/javascript">

	  var _gaq = _gaq || [];
	  _gaq.push(['_setAccount', 'UA-29155525-1']);
	  _gaq.push(['_setDomainName', 'worldtrailmap.com']);
	  _gaq.push(['_trackPageview']);
	
	  (function() {
	    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
	    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
	    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
	  })();
	
	</script>
  </head>
 <body>
 <div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>