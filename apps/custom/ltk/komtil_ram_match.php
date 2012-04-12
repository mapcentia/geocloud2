<?php
ini_set("display_errors", "On");
error_reporting(3);
session_start();
include '../../../conf/main.php';
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'libs/FirePHPCore/FirePHP.class.php';
include 'libs/FirePHPCore/fb.php';
include 'model/tables.php';
$postgisdb = $parts[3];

$postgisObject = & new postgis();

$query = "SELECT planid,aendringer FROM kommuneplan.komtildk2_join WHERE planid='".($_REQUEST['planid'])."'";
//echo $query;
$result = $postgisObject -> execQuery($query,"PG");
$row = pg_fetch_array($result);

$query = "SELECT planid,plannr,html FROM kommuneplan.kpplandk2_join WHERE komtil_id='".$row['planid']."'";
$result = $postgisObject -> execQuery($query,"PG");

if ($row['aendringer']){
	$split = explode(",",$row['aendringer']);
	echo "<table cellpadding='7' cellspacing='0'>";
	echo "<tr><td><b>F&oslash;lgende rammeomr&aring;der bliver aflyst ved till&aelig;ggets endelige vedtagelse:</b></td></tr>";
	foreach ($split as $value){
		$query2 = "SELECT html,plannr FROM kommuneplan.kpplandk2_view WHERE planid='".$value."'";
		$result2 = $postgisObject -> execQuery($query2,"PG");
		$row2 = pg_fetch_array($result2);
		echo "<tr><td><a href='".$row2['html']."'>".$row2['plannr']."</a></td></tr>";

	}
	echo "</table>";
}

//echo $query."<br>";

$num_results = pg_numrows($result);

if ($num_results){
	echo "<table cellpadding='7' cellspacing='0'>";
	echo "<tr><td><b>Nye rammeomr&aring;der, som bliver udlagt i till&aelig;gget:</b></td></tr>";
}

for ($i=0;$i<$num_results;$i++) {
	$row = pg_fetch_array($result);
	echo "<tr><td><a href='".$row['html']."'>".$row['plannr']."</a></td></tr>";
}
if ($num_results){
	echo "</table>";
}