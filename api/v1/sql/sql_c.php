<?php
set_time_limit(0);
include ("../server_header.inc");

include_once 'libs/PEAR/Cache_Lite/Lite.php';

parse_str(urldecode($_SERVER['QUERY_STRING']),$args);
$id = $args['q'];
if (!$args['lifetime']) $args['lifetime']=0;
//logfile::write($id);
//print_r($args);
//echo $id;
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
	echo $json->encode($response);
	// Cache script
	$data = ob_get_contents();
	$Cache_Lite -> save($data, $id);
	ob_get_clean();
}
$callback = $_GET['jsonp_callback'];
if ($callback) {
	echo $callback.'('.$data.');';
}
else {
	echo $data;
}