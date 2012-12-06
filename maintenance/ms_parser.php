<table border=1>
<?php
ini_set("display_errors", "On");
error_reporting(3);
include '../conf/main.php';
$map = ms_newMapobj($basePath."/wms/mapfiles/".$_GET['mapfile']);


$layerArr = $map -> getAllLayerNames();
//asort($layerArr);
$i = 0;
foreach($layerArr  as $layer)
{
	$layerObj = $mapscriptObject -> map -> getlayerbyname($layer);
	if ($layerObj -> getMetaData("appformap_loader") == "true") {
		
		echo "<tr><td>".($layer)."</td><td>".$layerObj -> getMetaData("wms_title")."</td></tr>\n";
	}
}
?>
</table>