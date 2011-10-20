<?php
include("../conf/main.php");
include("../libs/functions.php");
include("../model/databases.php");
include("../model/dbchecks.php");

$dbList = new databases();
try {
	$arr = $dbList->listAllDbs();
} catch (Exception $e) {
	echo $e->getMessage()."\n";
	die();
}


echo "<table border='1'>";
echo "<tr><td></td><td>PostGIS</td><td>MyGeoCloud</td></tr>";
foreach($arr['data'] as $db) {

	if ($db!="template1" AND $db!="template0" AND $db!="postgres" AND $db!="postgis_template") {
		echo "<tr><td>{$db}</td>";
		$postgisdb = $db;
		$dbc = new dbcheck();

		// Check if postgis is installed
		$checkPostGIS = $dbc->isPostGISInstalled();
		if ($checkPostGIS['success']) {
			echo "<td style='color:green'>V</td>";
		}
		else {
			echo "<td style='color:red'>X</td>";
		}

		// Check if schema "settings" is loaded
		$checkMy = $dbc->isSchemaInstalled();
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

}
echo "<table>";
// We check if "tmp" is writeable
$ourFileName = "../tmp/testFile.txt";
$ourFileHandle = fopen($ourFileName, 'w');
if ($ourFileHandle) {
	echo "tmp dir is writeable";
	fclose($ourFileHandle);
	unlink($ourFileName);
}
else {
	echo "tmp dir is not writeable. You must set permissions so the webserver can write in the tmp dir.";
}




