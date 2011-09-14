<?php
session_start();

include_once("../conf/main.php");
include_once("../libs/functions.php");
include_once("../libs/phpgeometry_class.php");

$postgisdb = $_SESSION['screen_name'];
$postgisObject = & new postgis();



//foreach($wfs as $name=>$str){
	logfile::write("******* START:".$name." *******\n");

$str = "http://mygeocloud.com/wfs/mhoegh?request=getfeature&typename=polygon&propertyname=the_geom";

	$gmlCon = new gmlConverter;
	$gml = file_get_contents($str);
	//echo $gml;
	$name = "martin";
	logfile::write(date('l jS \of F Y h:i:s A')." GML fetched. ");
	$gmlParserObj = new gmlParser($gml,$gmlCon);
	$gmlParserObj -> unserializeGml();
	$gmlParserObj -> loadInDB(& $postgisObject,$name);
	logfile::write("Memory used: ".number_format(memory_get_usage())." bytes\n");
	logfile::write("******** END:".$name." ********\n\n");
	unset($gmlCon);
	unset($gmlParserObj);
//}
?>
