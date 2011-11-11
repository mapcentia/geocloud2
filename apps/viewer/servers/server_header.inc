<?php
session_start();
include_once("../../../conf/main.php");
include("../inc/uri_parts.php");

$postgisdb = $parts[5];
$postgisschema = $parts[6];


switch($_REQUEST['type'])
{
	case "xml":
		header('Content-Type: text/xml');
	break;
	
	case "json":
		header('Content-Type: text/plain');
	break;
	case "text":
		header('Content-Type: text/plain');
	break;
	default:
		header('Content-Type: text/xml');
}

header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // Date in the past

include_once("../../../libs/phpgeometry_class.php");
include_once("../functions.php");
include_once("../../../libs/JSON.php");

$maplink = new maplink();
$postgisObject = & new postgis(); 

$json = &new Services_JSON();
?>
