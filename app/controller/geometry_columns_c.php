<?php

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
$gc = new app\model\GeometryColumns();

switch ($request[4]) {
	case 'getcartomobilesettings' :
		$response = $gc -> getCartoMobileSettings($request[5]);
		break;
	case 'updatecolumn' :
		$response = $gc -> updateColumn($obj -> data, $request[6]);
		break;
	case 'updatecartomobilesettings' :
		$response = $gc -> updateCartoMobileSettings($obj -> data, $request[6]);
		break;
	case 'getall' :
		$response = $gc -> getAll($request[5],$_SESSION['auth']);
		break;
	case 'getschemas' :
		$response = $gc -> getSchemas();
		break;
}
