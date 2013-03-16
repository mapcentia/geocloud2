<?php
include ("../header.php");
if (!$_SESSION['screen_name']) {

} else {
	$name = postgis::toAscii($_SESSION['screen_name'], NULL, "_");
	$db = new databases;
	$dbObj = $db -> createdb($name, $databaseTemplate, "UTF8");
	// databaseTemplate is set in conf/main.php
	if ($dbObj) {
		header("location: /user/login/p");
	} else {
		echo "<h2>Sorry, something went wrong. Try again</h2>";
		echo "<div><a href='/user/signup' class='btn btn-danger'>Go back</a></div>";
	}
}
