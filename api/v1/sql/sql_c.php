<?php
set_time_limit(0);
include_once '../server_header.inc';
include_once 'libs/PEAR/Cache_Lite/Lite.php';
include_once 'libs/SQL_Tokenizer.php';

$_REQUEST['q'] = rawurldecode($_REQUEST['q']);

$settings_viewer = new Settings_viewer();
$res = $settings_viewer -> get();
$apiKey = $res['data']['api_key'];
$callback = $_GET['jsonp_callback'];

//$array = preg_split("/(\r\n|\n|\r)/", $string);

$response = transaction($_REQUEST['q']);

// Check if $data is set in SELECT section
if (!$data) {
	$data = json_encode($response);
}
if ($callback) {
	echo $callback . '(' . $data . ');';
} else {
	echo $data;
}

/**/
function transaction($sql) {
	global $response, $apiKey, $data, $basePath;
	$parsedSQL = SqlParser::ParseString($sql) -> getArray();
	//$tokens = SqlParser::Tokenize($sql, true);
	if (strpos($sql, ';') !== false) {
		$response['success'] = false;
		$response['message'] = "You can't use ';'. Use the bulk transaction API instead";
	} elseif (strpos($sql, '--') !== false) {
		$response['success'] = false;
		$response['message'] = "SQL comments '--' are not allowed";
	} elseif ($parsedSQL['drop']) {
		$response['success'] = false;
		$response['message'] = "DROP is not allowed through the API";
	} elseif ($parsedSQL['alter']) {
		$response['success'] = false;
		$response['message'] = "ALTER is not allowed through the API";
	} elseif ($parsedSQL['create']) {
		$response['success'] = false;
		$response['message'] = "CREATE is not allowed through the API";
	} elseif ($parsedSQL['update'] || $parsedSQL['insert'] || $parsedSQL['delete']) {
		if ($apiKey == $_REQUEST['key'] || $apiKey==false) {
			$api = new sqlapi();
			$response = $api -> transaction($_REQUEST['q']);
		} else {
			$response['success'] = false;
			$response['message'] = "Not the right key!";
		}
	} elseif ($parsedSQL['select']) {

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
			echo json_encode($response);
			// Cache script
			$data = ob_get_contents();
			$Cache_Lite -> save($data, $id);
			ob_get_clean();
		}
	} else {
		$response['success'] = false;
		$response['message'] = "Check your SQL. Could not recognise it as either SELECT, INSERT, UPDATE or DELETE";
	}
	return $response;
}
