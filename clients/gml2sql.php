<?php
session_start();
set_time_limit(100000000);
include_once("../conf/main.php");
include_once("../libs/functions.php");
include_once("../libs/gmlparser.php");
include_once("../libs/phpgeometry_class.php");

$postgisdb = "mydb";

print("******* START:".$name." *******\n");

$str = "http://kortforsyningen.kms.dk/service?servicename=mat_gml2&client=MapInfo&request=GetFeature&service=WFS&login=Kommune840&password=Wsderft10&typename=kms:Jordstykke";

	$gml = file_get_contents($str);
	//echo $gml;
	$name = "public.jordstykke";
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
