<?php
session_start();

include_once("../conf/main.php");
include_once("../libs/functions.php");
include_once("../libs/gmlparser.php");
include_once("../libs/phpgeometry_class.php");

//$postgisdb = $_SESSION['screen_name'];
$postgisdb = "odder";
$postgisschema = "lokalplaner";

//foreach($wfs as $name=>$str){
	print("******* START:".$name." *******\n");

$str = "http://wfs.plansystem.dk/geoserver/wfs?service=WFS&REQUEST=GETFEATURE&TYPENAME=pdk:theme_pdk_lokalplan_vedtaget_v&filter=%3CFilter%3E%3CPropertyIsEqualTo%3E%3CPropertyName%3Ekomnr%3C/PropertyName%3E%3CLiteral%3E727%3C/Literal%3E%3C/PropertyIsEqualTo%3E%3C/Filter%3E";
	
	$gml = file_get_contents($str);
	//echo $gml;
	$name = "lokalplaner.lokalplan_vedtaget";
	print(date('l jS \of F Y h:i:s A')." GML fetched. ");
	$gmlParserObj = new GmlParser($gml);
	$gmlParserObj -> unserializeGml();
	$gmlParserObj -> loadInDB($name);
	print("Memory used: ".number_format(memory_get_usage())." bytes\n");
	print("******** END:".$name." ********\n\n");
	unset($gmlCon);
	unset($gmlParserObj);
//}
?>
