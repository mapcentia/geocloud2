<?php
session_start();

include_once("../conf/main.php");
include_once("../libs/functions.php");
include_once("../libs/gmlparser.php");
include_once("../libs/phpgeometry_class.php");

//$postgisdb = $_SESSION['screen_name'];
$postgisdb = "mhoegh";
$postgisschema = "test";

//foreach($wfs as $name=>$str){
	print("******* START:".$name." *******\n");

$str = "http://beta.mygeocloud.com/wfs/mhoegh?request=getfeature&typename=test";

	$gml = file_get_contents($str);
	//echo $gml;
	$name = "martin";
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
