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


$cacheDir = $basePath."tmp/cache/";
