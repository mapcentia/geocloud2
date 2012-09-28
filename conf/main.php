<?php
ini_set("display_errors", "On");
error_reporting(3);

// URL and path information. Must be the web root folder!! 
$hostName = "http://beta.mygeocloud.cowi.webhouse.dk";
$ODEUMhostName = "http://samsoe-lp.odeum.com";
$basePath = "/srv/odeum/sites/betamygeocloud/";


// PostGreSQL connection
if (!$postgishost) $postgishost="127.0.0.1";
if (!$postgisdb) $postgisdb="postgres";
if (!$postgisuser) $postgisuser="postgres";
if (!$postgisport) $postgisport="";
if (!$postgispw) $postgispw="1234";

// Database template for creating new databases
$databaseTemplate = "mygeocloud_template";

// Use PostGIS or PHP to export GML
$useWktToGmlInPHP = false;

// Your Google Maps API key
$gMapsApiKey = "ABQIAAAAixUaqWcOE1cqF2LJyDYCdhTww2B3bmOd5Of57BUV-HZKowzURRTDiOeJ4A8o-OZoiMfdrJzdG3POiw";

// Include path setting. You may not need to alter this
set_include_path(get_include_path() . PATH_SEPARATOR . $basePath . PATH_SEPARATOR . $basePath."libs" . PATH_SEPARATOR . $basePath."inc" . PATH_SEPARATOR . $basePath."libs/PEAR/" . PATH_SEPARATOR . $basePath."conf");

if ($_REQUEST['client'] == "plan") {
	$gmlNameSpace = "pdk";
	$gmlNameSpaceUri = "http://www.lifa.dk/PlanDK2";
	$gmlNameSpaceGeom = "gml";
	$gmlFeatureCollection = "pdk:Plan";
	
	$gmlGeomFieldName['lpplandk2_view'] = "multiPolygonProperty";
	$gmlFeature['lpplandk2_view'] = "LokalPlan";
	
	$gmlGeomFieldName['lpplandk2_join'] = "multiPolygonProperty";
	$gmlFeature['lpplandk2_join'] = "LokalPlan";
	
	$gmlGeomFieldName['kpplandk2_view'] = "multiPolygonProperty";
	$gmlFeature['kpplandk2_view'] = "KommunePlanRamme";
	
	$gmlGeomFieldName['kpplandk2_join'] = "multiPolygonProperty";
	$gmlFeature['kpplandk2_join'] = "KommunePlanRamme";
	
	$gmlGeomFieldName['komtildk2_join'] = "multiPolygonProperty";
	$gmlFeature['komtildk2_join'] = "KommunePlanTillaeg";
	
	$gmlSchemaLocation = "http://www.opengis.net/wfs http://wfs.plansystem.dk:80/geoserver/schemas/wfs/1.0.0/WFS-basic.xsd http://www.lifa.dk/PlanDK2 http://soap.plansystem.dk/pdk_schemas/PLANDK2.xsd";
	$defaultBoundedBox =
		'<gml:boundedBy>
			  <gml:Box srsName="EPSG:25832">
			    <gml:coordinates decimal="." cs="," ts=" ">709075,6174859 730509,6199648</gml:coordinates>
			  </gml:Box>
	    </gml:boundedBy>';
	$gmlUseAltFunctions['changeFieldName'] = true;
	$gmlUseAltFunctions['altFieldValue'] = true;
	$gmlUseAltFunctions['altFieldNameToUpper'] = true;
}
