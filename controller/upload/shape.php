<?php
include("../server_header.inc");
$targetPath = "{$_SERVER["DOCUMENT_ROOT"]}/tmp/";
$fileName = $_SESSION['screen_name']."_".time();
$file = $targetPath.$fileName;

$response['uploaded'] = true;

$SafeFile = $_FILES['shp']['name']; 
$SafeFile = str_replace("#", "No.", $SafeFile); 
$SafeFile = str_replace("-", "_", $SafeFile); 
$SafeFile = str_replace("$", "Dollar", $SafeFile); 
$SafeFile = str_replace("%", "Percent", $SafeFile); 
$SafeFile = str_replace("^", "", $SafeFile); 
$SafeFile = str_replace("&", "and", $SafeFile); 
$SafeFile = str_replace("*", "", $SafeFile); 
$SafeFile = str_replace("?", "", $SafeFile); 
$SafeFile = str_replace(".shp", "", $SafeFile);
$SafeFile = strtolower($SafeFile);
$SafeFile = $postgisschema.".".$SafeFile;
$SafeFile = postgis::toAscii($SafeFile,array(),"_");

if(move_uploaded_file($_FILES['shp']['tmp_name'], $file.".shp")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['dbf']['tmp_name'], $file.".dbf")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['shx']['tmp_name'], $file.".shx")) {
} else{$response['uploaded'] = false;}

if ($response['uploaded']) {
	$shapeFile = new shapefile($SafeFile,$_REQUEST['srid'],$file,$_REQUEST['pdo']);
	$response = $shapeFile->loadInDb();
	
}
include_once("../server_footer.inc");