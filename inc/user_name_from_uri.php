<?php
$parts = $parts = explode("/", $_SERVER['REDIRECT_URL']);

$userFromUri = $parts[2];
$srsFromUri = $parts[3];

//echo "<!-- Username from uri: ".$userFromUri."-->\n";
//echo "<!-- SRS from uri: ".$srsFromUri."-->\n";


