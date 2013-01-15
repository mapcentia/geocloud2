<?php
set_time_limit(0);
include_once '../server_header.inc';
include_once 'libs/PEAR/Cache_Lite/Lite.php';
include_once 'libs/SQL_Tokenizer.php';

$settings_viewer = new Settings_viewer();
$response = $settings_viewer->get();
$apiKey = $response['data']['api_key'];

$parsedSQL = SqlParser::ParseString($_REQUEST['q'])->getArray();
if ($parsedSQL['drop']){
	$response['success'] = false;
	$response['message'] = "DROP is not allowed through the API";
	echo json_encode($response);
	die();
}
if ($parsedSQL['alter']){
	$response['success'] = false;
	$response['message'] = "ALTER is not allowed through the API";
	echo json_encode($response);
	die();
}
if ($parsedSQL['create']){
	$response['success'] = false;
	$response['message'] = "CREATE is not allowed through the API";
	echo json_encode($response);
	die();
}
if ($parsedSQL['update'] || $parsedSQL['insert'] || $parsedSQL['delete']){
	if ($apiKey == $_REQUEST['key']) {
		echo "ja";
	} else {
		echo "nej";
	}
}
if ($parsedSQL['select']) {
	parse_str(urldecode($_SERVER['QUERY_STRING']), $args);
	$id = $args['q'];
	if (!$args['lifetime']) {
		$args['lifetime'] = 0;
	}
	$options = array('cacheDir' => "{$basePath}/tmp/", 'lifeTime' => $args['lifetime']);
	$Cache_Lite = new Cache_Lite($options);
	if ($data = $Cache_Lite -> get($id)) {
		//echo "cached";
	} else {
		ob_start();
		if ($_REQUEST['srs']) {
			$srs = $_REQUEST['srs'];
		} else {
			$srs = "900913";
		}
		$api = new sqlapi($srs);
		$api -> execQuery("set client_encoding='UTF8'", "PDO");
		$response = $api -> sql($_REQUEST['q']);
		echo $json -> encode($response);
		// Cache script
		$data = ob_get_contents();
		$Cache_Lite -> save($data, $id);
		ob_get_clean();
	}
	$callback = $_GET['jsonp_callback'];
	if ($callback) {
		echo $callback . '(' . $data . ');';
	} else {
		echo $data;
	}
}