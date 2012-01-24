<?php
// Start HTML doc
include("html_header.php");
?>
<link rel="stylesheet" href="/css/style.css" type="text/css" media="screen" />
</head>
<body>
<div id="outercontainer">
<div id="innercontainer">
<div id='stylized' class="myform">
<?php 
if (!$_REQUEST['name']) {
	echo "<h1>Need a name!</h1>";
	echo "<p></p>";
}
else {
	$name = postgis::toAscii($_REQUEST['name'],NULL,"_");
	$db = new databases;
	$dbObj = $db -> createdb($name,$databaseTemplate); // databaseTemplate is set in conf/main.php
	if ($dbObj) {
		
		echo "<h1>Your geocloud \"{$name}\" was created!</h1>"; 
		echo "<p>When asked type in \"{$name}\" and use \"1234\" for password. Remember to change it!</p>"; 
		echo "<button onclick=\"window.location.href='/store/{$name}'\">Take me to my cloud</button>";
	}
	else {
		echo "<h1>Sorry, something went wrong. Try again</h1>";
	}
}
?>
<div class="spacer"></div>
</div>
</div>
</div>
<?php
include("html_footer.php");
