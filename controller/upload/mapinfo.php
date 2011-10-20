<?php
include("../server_header.inc");
$targetPath = "{$_SERVER["DOCUMENT_ROOT"]}/tmp/";
$fileName = $_SESSION['screen_name']."_".time();
$file = $targetPath.$fileName;

$response['uploaded'] = true;

$SafeFile = $_FILES['tab']['name']; 
$SafeFile = str_replace("#", "No.", $SafeFile); 
$SafeFile = str_replace("-", "_", $SafeFile); 
$SafeFile = str_replace("$", "Dollar", $SafeFile); 
$SafeFile = str_replace("%", "Percent", $SafeFile); 
$SafeFile = str_replace("^", "", $SafeFile); 
$SafeFile = str_replace("&", "and", $SafeFile); 
$SafeFile = str_replace("*", "", $SafeFile); 
$SafeFile = str_replace("?", "", $SafeFile); 
$SafeFile = str_replace(".TAB", "", $SafeFile);
$SafeFile = strtolower ($SafeFile);


if(move_uploaded_file($_FILES['tab']['tmp_name'], $file.".tab")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['map']['tmp_name'], $file.".map")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['id']['tmp_name'], $file.".id")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['dat']['tmp_name'], $file.".dat")) {
} else{$response['uploaded'] = false;}

 
if ($response['uploaded']) {

	switch ($_REQUEST['type']) {
		case "Point":
		$type = "point";
		break;
		case "Polygon":
		$type = "multipolygon";
		break;
		case "Line":
		$type = "multilinestring";
		break;
	}
	
	$cmd = "ogr2ogr  -nlt '{$type}' -a_srs 'EPSG:{$_REQUEST['srid']}' -f 'PostgreSQL' PG:'user=postgres dbname={$postgisdb}' {$file}.tab -nln ".$SafeFile."_".$type;
	$result = exec($cmd);
	$response['success'] = true;
	$response['message'] = $result;
	/*
	$cmd = "ogr2ogr -skipfailures -f 'ESRI Shapefile' '{$file}_arc.shp' -lco 'SHPT=ARC' '{$file}.tab'";
	$result = exec($cmd, $output);
	$cmd = "ogr2ogr -skipfailures -f 'ESRI Shapefile' '{$file}_point.shp' -lco 'SHPT=POINT' '{$file}.tab'";
	$result = exec($cmd, $output);
	$cmd = "ogr2ogr -skipfailures -f 'ESRI Shapefile' '{$file}_polygon.shp' -lco 'SHPT=POLYGON' '{$file}.tab'";
	$result = exec($cmd, $output);
	
	$shapeFile = new shapefile($SafeFile,$_REQUEST['srid'],$file."_polygon",$_REQUEST['pdo']);
	$response = $shapeFile->loadInDb();
	*/
}

include_once("../server_footer.inc");