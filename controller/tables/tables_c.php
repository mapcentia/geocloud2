<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");


$table = new table($parts[5]);

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
//print_r($obj);

switch ($parts[4]){
	case "getrecords": // All tables
		$response = $table -> getRecords();
	break;
	case "updaterecord": // All tables
		$response = $table -> updateRecord($obj->data,$parts[6]);
	break;
	case "destroyrecord": // Geometry columns
		$response = $table -> destroyRecord($obj->data,$parts[6]);
	break;
	case 'getcolumns': // All tables
		$response = $table -> getColumnsForExtGridAndStore();
	break;
	case 'getstructure': // All tables
		$response = $table -> getTableStructure();
	break;
	case 'updatecolumn':
		$response = $table -> updateColumn($obj->data);
		break;
	case 'createcolumn':
		$response = $table -> addColumn($_POST); // Is POSTED by a form
		break;
	case 'destroycolumn':
		$response = $table -> deleteColumn($obj->data);
		break;
	case 'addcolumn':
		$response = $table -> addColumn($obj->data);
		break;
}
include_once("../server_footer.inc");
