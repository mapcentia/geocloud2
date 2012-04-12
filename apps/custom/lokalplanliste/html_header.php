<?php
session_start();
ini_set("display_errors", "On");
error_reporting(3);

include '../../../conf/main.php';

include 'inc/user_name_from_uri.php';
$postgisdb = $parts[4];

?>
<!DOCTYPE html>
<html>
  <head>
    <title>MyGeoCloud - Online GIS - Store geographical data and make online maps - WFS and WMS</title>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="description" content="Store geographical data and make online maps" />
	<meta name="keywords" content="GIS, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC" />
	<meta name="author" content="Martin Hoegh" />
	
	<script type="text/javascript" src="/js/ext/adapter/ext/ext-base.js"></script>
	<script type="text/javascript" src="/js/ext/ext-all.js"></script>
	<script type="text/javascript" src="/js/jquery/1.6.4/jquery.min.js"></script>
	<script type="text/javascript" src="/apps/custom/lokalplanliste/js/lokalplangrid.js"></script>
	<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/ext-all.css"/>
	<link rel="stylesheet" type="text/css" href="/js/ext/resources/css/xtheme-gray.css" />
	<script type="text/javascript">var screenName='<?php echo $postgisdb;?>'</script>
</head>
