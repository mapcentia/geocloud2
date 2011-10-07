<?php
$parts = explode("/", str_replace("?".$_SERVER['QUERY_STRING'],"",$_SERVER['REQUEST_URI']));
//$parts = $parts = explode("/", $_SERVER['REDIRECT_URL']);

$userFromUri = $parts[2];
$srsFromUri = $parts[3];

//echo "<!-- Username from uri: ".$userFromUri."-->\n";
//echo "<!-- SRS from uri: ".$srsFromUri."-->\n";


