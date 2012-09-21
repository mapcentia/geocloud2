<?php
include("../server_header.inc");
//include("../../inc/oauthcheck.php");

if ($HTTP_RAW_POST_DATA) {
	$obj = json_decode($HTTP_RAW_POST_DATA);
}
$gc = new GeometryColumns();
//print_r($obj);

switch ($parts[4]){
	case 'getcartomobilesettings':
		$response = $gc -> getCartoMobileSettings($parts[5]);
		break;
	case 'updatecolumn':
		$response = $gc -> updateColumn($obj->data,$parts[6]);
		break;
	case 'updatecartomobilesettings':
		$response = $gc -> updateCartoMobileSettings($obj->data,$parts[6]);
		break;
}
include_once("../server_footer.inc");
