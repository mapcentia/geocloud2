<?php
ini_set("display_errors", "On");
error_reporting(3);

$hostName = "http://127.0.0.1";

$basePath = "/var/www/mygeocloud/";
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath . PATH_SEPARATOR . $basePath."libs" . PATH_SEPARATOR . $basePath."inc" . PATH_SEPARATOR . $basePath."libs/PEAR" . PATH_SEPARATOR . $basePath."conf");

// PostGIS connection
if (!$postgishost) $postgishost="127.0.0.1";
if (!$postgisdb) $postgisdb="mygeocloud";
if (!$postgisuser) $postgisuser="postgres";
if (!$postgisport) $postgisport="";
if (!$postgispw) $postgispw="";

$useWktToGmlInPHP = false;

$gMapsApiKey = "ABQIAAAAixUaqWcOE1cqF2LJyDYCdhS4p9AtMz66nyqFUaziGHLM44rOahQ1vHhpXeGXl_ifkSE8O1eT_foV2w";

$cacheDir = $basePath."tmp/cache/";
