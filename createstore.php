<?php
// Start HTML doc
include("html_header.php");
?>
<body>
<?php 
if (!$_REQUEST['name']) {
	die("Need a name!");
}
else {
	$name = postgis::toAscii($_REQUEST['name'],NULL,"_");
}
$db = new databases;
	$dbObj = $db -> createdb($name,$databaseTemplate); // databaseTemplate is set in conf/main.php
	echo "<div id='stylized' class='myform'>";
	if ($dbObj) {
		
		echo "<h1>Your geocloud \"{$name}\" was created!</h1>"; 
		echo "<p>When asked type in \"{$name}\" and use \"1234\" for password. Remember to change it!</p>"; 
		echo "<button onclick=\"window.location.href='/store/{$name}'\">Take me to my cloud</button>";
	}
	else {
		echo "<h1>Sorry, something went wrong. Try again</h1>";
		echo "<p> </p>";
	}
echo "<div class='spacer'></div>";
echo "</div>";
include("html_footer.php");
