<?php
//ini_set("display_errors", "On");
error_reporting(3);

// URL and path information. Must be the web root folder! 
$hostName = "http://mysite.com";
$basePath = "/var/www/";

// Windows
//$basePath = "C:\\Program Files\\ms4w\\Apache\\htdocs\\";


// PostGreSQL connection
$postgishost="127.0.0.1";
$postgisdb="database";
$postgisuser="user";
$postgisport="";
$postgispw="1234";

// Database template for creating new databases
$databaseTemplate = "postgis";

// Use PostGIS or PHP to export GML
$useWktToGmlInPHP = false;

// Your Google Maps API key
$gMapsApiKey = "ABQIAAAAixUaqWcOfE1cqF2LJyDYCdTww2B3bmOd5Of57BUV-HZKowzURRTDiOeJ4A8o-OZoiMfdrJzdG3POiw";

// Include path setting. You may not need to alter this
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath . PATH_SEPARATOR . $basePath."libs" . PATH_SEPARATOR . $basePath."inc" . PATH_SEPARATOR . $basePath."libs/PEAR/" . PATH_SEPARATOR . $basePath."conf");