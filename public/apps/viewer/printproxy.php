<?php
ini_set("display_errors", "On");
use \app\conf\App;
include_once("../../../app/conf/App.php");
$url = App::$param["geoserverHost"]."/geoserver/pdf/info.json?var=printConfig";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4 );
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
echo curl_exec($ch);
curl_close($ch);
