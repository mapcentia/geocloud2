<?php
// Start HTML doc
include("html_header.php");
?>
<body>
<div class="desc">
<?php 
include("inc/topbar.php");
$db = new databases;
/*if ($_SESSION['screen_name']) { // User already signed
	$user = new users($_SESSION['screen_name'],$_SESSION['id'],$_SESSION["tok"],$_SESSION["sec"]);
}
else*/
if ($parts[2]) {
	$_SESSION['screen_name'] = $parts[2];
//	$user = new users($_SESSION['screen_name']);
	$_SESSION["oauth_token"] = true; // We set to true, so it will pass tests
}
else { //User not signed. Use IP signin.
	$_SESSION['screen_name'] = "_".postgis::toAscii($_SERVER['REMOTE_ADDR']);
	$user = new users($_SESSION['screen_name']);
}

//if (!$user -> getHasCloud()){
	$dbObj = $db -> createdb($_SESSION['screen_name']);
	if ($dbObj) {
		echo "<p class='desc'>Your geocloud was created!</p>"; 
		echo "<form>
		  <input class=\"btn\" type=\"button\" value=\"Take me to my geo cloud\" onclick=\"window.location.href='/store/{$_SESSION['screen_name']}'\"> 
		</form>";
	}
	else {
		echo "<p class='desc'>Sorry, something went wrong. Try again</p>";
	}
//}
//else {
//	echo "<p class='desc'>Hey! You've already a cloud!</p>";
//}

echo "</div>";
include("html_footer.php");
