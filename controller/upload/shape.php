<?php
include("../server_header.inc");
$targetPath = "/var/www/mygeocloud/tmp/shape/";
$fileName = $_SESSION['screen_name']."_".time();
$file = $targetPath.$fileName;

$response['uploaded'] = true;

$SafeFile = $_FILES['shp']['name']; 
$SafeFile = str_replace("#", "No.", $SafeFile); 
$SafeFile = str_replace("-", "_", $SafeFile); 
$SafeFile = str_replace("$", "Dollar", $SafeFile); 
$SafeFile = str_replace("%", "Percent", $SafeFile); 
$SafeFile = str_replace("^", "", $SafeFile); 
$SafeFile = str_replace("&", "and", $SafeFile); 
$SafeFile = str_replace("*", "", $SafeFile); 
$SafeFile = str_replace("?", "", $SafeFile); 
$SafeFile = str_replace(".shp", "", $SafeFile);
$SafeFile = strtolower ($SafeFile);


if(move_uploaded_file($_FILES['shp']['tmp_name'], $file.".shp")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['dbf']['tmp_name'], $file.".dbf")) {
} else{$response['uploaded'] = false;}
if(move_uploaded_file($_FILES['shx']['tmp_name'], $file.".shx")) {
} else{$response['uploaded'] = false;}

$table = new table($SafeFile);
$postgisObject->connect("PDO");

if ($response['uploaded']) {
	// The psql way
	// Create block begin
	$postgisObject->begin();
	if ($table->exits) {
		$table->destroy();
	}
	$cmd = "shp2pgsql -D -c -s {$_REQUEST['srid']} ".$file.".shp {$SafeFile}|psql {$postgisdb} postgres";
	$result = exec($cmd);
	if ($result=="COMMIT"){
		if (!$table->exits) { // no need to re-init table object if table exits
			$table = new table($SafeFile);
		}
		else {
			$overWriteTxt = " An exiting layer was overwriten";
		}
		//$table->point2multipoint();
		$postgisObject->commit();
		
		// rename column 'state' if such exits
		$postgisObject->connect("PDO");
		$postgisObject->begin();
		$sql = "ALTER TABLE {$SafeFile} RENAME state to _state";
		$postgisObject->execQuery($sql);
		$postgisObject->commit();
			
		$response['success'] = true;
		$response['message'] = "Your shape file was uploaded and processed. You can find new layer i your geocloud.".$overWriteTxt;
	}
	else {
		$postgisObject->rollback();
		$response['success'] = false;
		$response['message'] = "Something went wrong!";
	}
}
/*
if ($response['uploaded']) {
	//The PDO way
	$cmd = "shp2pgsql -c -s {$_REQUEST['srid']} ".$file.".shp {$SafeFile}";
	$result = exec($cmd,$output);
	
	ob_start();
	print_r($output);
	$out = ob_get_clean();
	logfile::write($out."\n\n");

	$sql_total = implode("",$output);

	// Create block begin
	$postgisObject->begin();
	if ($table->exits) {
		$table->destroy();
	}
	$postgisObject->execQuery($sql_total,"PDO","transaction");
	$postgisObject->commit();
	//Create block end

	if (!$postgisObject->PDOerror) {
		if (!$table->exits) { // no need to re-init table object if table exits
			$table = new table($SafeFile);
		}
		else {
			$overWriteTxt = " An exiting layer was overwriten";
		}
		$table->point2multipoint();
		$response['success'] = true;
		$response['message'] = "Your shape file was uploaded and processed. You can find new layer i your geocloud.".$overWriteTxt;
	}
	else {
		$response['success'] = false;
		$response['message'] = $postgisObject->PDOerror;
		$postgisObject->rollback();
	}
}
*/
$table = new table('settings.geometry_columns_join');
$obj = json_decode('{"data":{"f_table_name":"'.$SafeFile.'","f_table_title":""}}');
$response2 = $table->updateRecord($obj->data,'f_table_name');

// If layer is new (inserted) then insert a new class for it
if ($response2['operation'] == "inserted") {
	$class = new _class();
	$class->insert($SafeFile,array(),"_");
}
makeMapFile($_SESSION['screen_name']);
$response['cmd'] = $cmd;
include_once("../server_footer.inc");