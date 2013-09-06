<?php
$table = new app\model\table($request[5]);

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}

switch ($request[4]){
	case "getrecords": // only geometrycolumns table
		$response = $table -> getRecords(true,"*",$whereClause="f_table_schema='". \Connection::$param["postgisschema"]."'");
		break;
	case "getgeojson": // only geometrycolumns table
		$response = $table -> getGeoJson();
		break;
	case "getallrecords": // All tables
		$response = $table -> getRecords(false,"gid,plannr,plannavn,distrikt,anvendelsegenerel,zonestatus,doklink",null);
		break;
	case "getgroupby": // All tables
		$response = $table -> getGroupBy($request[6]);
		break;
	case "updaterecord": // All tables
		$response = $table -> updateRecord($obj->data,$request[6]);
		makeMapFile($_SESSION['screen_name']);
		break;
		/*
	case "destroyrecord": // Geometry columns
		$response = $table -> destroyRecord($obj->data,$parts[6]);
		makeMapFile($_SESSION['screen_name']);
		break;
		 */
		 
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
		$response = $table -> updateColumn($obj->data,$request[6]);
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
