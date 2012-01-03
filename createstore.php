<?php
// Start HTML doc
include("html_header.php");
?>
<body>
<div class="desc">
<?php 
if (!$parts[2]) {
	die("Need a user name in URL.");
}
$db = new databases;


	$dbObj = $db -> createdb($parts[2]);
	if ($dbObj) {
		echo "<p class='desc'>Your geocloud was created!</p>"; 
		echo "<form>
		  <input class=\"btn\" type=\"button\" value=\"Take me to my geo cloud\" onclick=\"window.location.href='/store/{$parts[2]}'\"> 
		</form>";
	}
	else {
		echo "<p class='desc'>Sorry, something went wrong. Try again</p>";
	}

echo "</div>";
include("html_footer.php");
