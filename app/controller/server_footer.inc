<?php
$callback = $_GET['jsonp_callback'];

if ($callback) {
	echo $callback.'('.json_encode($response).');';
}
else {
	echo json_encode($response);
}
