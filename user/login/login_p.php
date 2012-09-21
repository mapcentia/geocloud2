<?php
session_start();
include("../../conf/main.php");
include '../../libs/functions.php';
include 'model/databases.php';
print_r($_SESSION);
if ($_SESSION['screen_name']!=$_REQUEST['screen_name']){
	die("What?");
}
?>
<html>
<head>
<title>Self-submitting Form Sample</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link href="samples.css" rel="stylesheet" type="text/css">
</head>
<body>
<h1>Self-submitting Form Sample</h1>
<p>Congratulation! You have successfully logged in.</p>
<?php
	$db = new databases();
		if ($db->doesDbExist(postgis::toAscii($_SESSION['screen_name'],NULL,"_"))) {
			echo "DB exist";
		}
		else{
			echo "DB does not exist";
		}
	
?>
</body>
</html>