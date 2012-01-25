<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

$table = new table($parts[5]);

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
//print_r($obj);

switch ($parts[4]){
	case "getrecords": // only geometrycolumns table
		$response = $table -> getRecords(true,"*",$whereClause="f_table_schema='{$postgisschema}'");
		break;
	case "getallrecords": // All tables
		$response = $table -> getRecords(NULL,NULL,"gid,plannr,plannavn,distrikt,anvendelsegenerel,zonestatus,doklink");
		break;
	case "getgroupby": // All tables
		$response = $table -> getGroupBy($parts[6]);
		break;
	case "updaterecord": // All tables
		$response = $table -> updateRecord($obj->data,$parts[6]);
		makeMapFile($_SESSION['screen_name']);
		break;
	case "destroyrecord": // Geometry columns
		$response = $table -> destroyRecord($obj->data,$parts[6]);
		makeMapFile($_SESSION['screen_name']);
		break;
	case "destroy": // Geometry columns
		$response = $table -> destroy();
		makeMapFile($_SESSION['screen_name']);
		break;
	case 'getcolumns': // All tables
		$response = $table -> getColumnsForExtGridAndStore();
		break;
	case 'getcolumnswithkey': // All tables
		$response = $table -> getColumnsForExtGridAndStore(true);
		break;
	case 'getstructure': // All tables
		$response = $table -> getTableStructure();
		break;
	case 'updatecolumn':
		$response = $table -> updateColumn($obj->data,$parts[6]);
		makeMapFile($_SESSION['screen_name']);
		break;
	case 'createcolumn':
		$response = $table -> addColumn($_POST); // Is POSTED by a form
		makeMapFile($_SESSION['screen_name']);
		break;
	case 'destroycolumn':
		$response = $table -> deleteColumn($obj->data);
		makeMapFile($_SESSION['screen_name']);
		break;
	case 'addcolumn':
		$response = $table -> addColumn($obj->data);
		makeMapFile($_SESSION['screen_name']);
		break;
}
include_once("../server_footer.inc");
