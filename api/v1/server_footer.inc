<?php
$callback = $_GET['jsonp_callback'];

if ($callback) {
	echo $callback.'('.$json->encode($response).');';
}
else {
	echo $json->encode($response);
}
