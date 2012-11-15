 <link href="/js/bootstrap/css/bootstrap.css" rel="stylesheet">
<?php

include("../conf/main.php");
include("../libs/functions.php");
include("../model/databases.php");
include("../model/dbchecks.php");
echo "<div>PHP version ".phpversion ()." ";
if (function_exists(apache_get_modules)){
	echo " running as mod_apache</div>";
	$mod_apache=true;
}
else {
	echo " running as CGI/FastCGI</div>";
	$mod_apache=false;
}

// We check if "wms/mapfiles" is writeable
$ourFileName = "../wms/mapfiles/testFile.txt";
$ourFileHandle = @fopen($ourFileName, 'w');
if ($ourFileHandle) {
	echo "<div class='alert alert-success'>wms/mapfiles dir is writeable</div>";
	@fclose($ourFileHandle);
	@unlink($ourFileName);
}
else {
	echo "<div class='alert alert-error'>wms/mapfiles dir is not writeable. You must set permissions so the webserver can write in the wms/mapfiles dir.</div>";
}
$ourFileName = "../wms/cfgfiles/testFile.txt";
$ourFileHandle = @fopen($ourFileName, 'w');

if ($ourFileHandle) {
	echo "<div class='alert alert-success'>wms/cfgfiles dir is writeable</div>";
	@fclose($ourFileHandle);
	@unlink($ourFileName);
}
else {
	echo "<div class='alert alert-error'>wms/cfgfiles dir is not writeable. You must set permissions so the webserver can write in the wms/cfgfiles dir.</div>";
}

$mod_rewrite = FALSE;
if (function_exists("apache_get_modules")) {
   $modules = apache_get_modules();
   $mod_rewrite = in_array("mod_rewrite",$modules);
}
if (!isset($mod_rewrite) && isset($_SERVER["HTTP_MOD_REWRITE"])) {
   $mod_rewrite = ($_SERVER["HTTP_MOD_REWRITE"]=="on" ? TRUE : FALSE); 
}
if (!$mod_rewrite) {
   // last solution; call a specific page as "mod-rewrite" have been enabled; based on result, we decide.
   $result = @file_get_contents("{$hostName}/mod_rewrite");
   $mod_rewrite  = ($result=="ok" ? TRUE : FALSE);
}

if ($mod_rewrite) {
	echo "<div class='alert alert-success'>Apache mod_rewrite is installed</div>";
}
else {
	echo "<div class='alert alert-error'>Apache mod_rewrite is not installed<div>";
}

if (function_exists(ms_newMapobj)){
	echo "<div class='alert alert-success'>MapScript is installed</div>";
	$mod_apache=true;
}
else {
	echo "<div class='alert alert-error'>MapScript is not installed</div>";
	$mod_apache=false;
}
$headers = get_headers("{$hostName}/cgi/tilecache.fcgi",1);

if ($headers['Content-Type']=="text/html"){
	echo "<div class='alert alert-success'>It seems that Python is installed</div>";
}
else{
	echo "<div class='alert alert-error	'>It seems that Python is not installed</div>";
}

$dbList = new databases();
try {
	$arr = $dbList->listAllDbs();
} catch (Exception $e) {
	echo $e->getMessage()."\n";
	die();
}

$i=1;
echo "<table border='1'>";
echo "<tr><td></td><td>PostGIS</td><td>MyGeoCloud</td></tr>";
foreach($arr['data'] as $db) {

	if ($db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template") {
		echo "<tr><td>{$i} {$db}</td>";
		$postgisdb = $db;
		$dbc = new dbcheck();

		// Check if postgis is installed
		//$checkPostGIS = $dbc->isPostGISInstalled();
		if ($checkPostGIS['success']) {
			echo "<td style='color:green'>V</td>";
		}
		else {
			echo "<td style='color:red'>X</td>";
		}

		// Check if schema "settings" is loaded
		//$checkMy = $dbc->isSchemaInstalled();
		if ($checkMy['success']) {
			echo "<td style='color:green'>V";
			$checkView = $dbc->isViewInstalled();
			if (!$checkView['success']) {
				echo "<span style='margin-left:20px'>But view is missing</span>";
			}
			echo "</td>";
		}
		else {
			echo "<td style='color:red'>X";
			if ($checkPostGIS['success']){
				echo "<span style='margin-left:20px'><a href='installmy.php?db={$postgisdb}'>Install</a></span>";
			}
			echo "</td>";
		}

		echo "</tr>";


	}
$i++;
}
echo "<table>";







