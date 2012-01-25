<?php
ini_set("display_errors", "On");
error_reporting(3);

// URL and path information. Must be the web root folder! 
$hostName = "http://test.mygeocloud.com";
$basePath = "/var/www/mygeocloud_rc1/";


// PostGreSQL connection
if (!$postgishost) $postgishost="127.0.0.1";
if (!$postgisdb) $postgisdb="postgres";
if (!$postgisuser) $postgisuser="postgres";
if (!$postgisport) $postgisport="";
if (!$postgispw) $postgispw="";

// Database template for creating new databases
$databaseTemplate = "template_mygeocloud";

// Use PostGIS or PHP to export GML
$useWktToGmlInPHP = false;

// Your Google Maps API key
$gMapsApiKey = "ABQIAAAAixUaqWcOE1cqF2LJyDYCdhS4p9AtMz66nyqFUaziGHLM44rOahQ1vHhpXeGXl_ifkSE8O1eT_foV2w";

// Include path setting. You may not need to alter this
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath . PATH_SEPARATOR . $basePath."libs" . PATH_SEPARATOR . $basePath."inc" . PATH_SEPARATOR . $basePath."libs/PEAR/" . PATH_SEPARATOR . $basePath."conf");
