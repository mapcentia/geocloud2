<?php
include '../header.php';
// Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
	die("<script>window.location='http://{$domain}/user/login'</script>");
}
($_SESSION['zone']) ? $prefix=$_SESSION['zone']."." : $prefix="";
$checkDb = json_decode(file_get_contents("http://{$prefix}{$domain}/controller/databases/postgis/doesdbexist/{$_SESSION['screen_name']}"));
//$checkDb = json_decode(file_get_contents("http://127.0.0.1/controller/databases/postgis/doesdbexist/{$_SESSION['screen_name']}"));

if ($checkDb->success) {
	echo "<a style='margin-top:40px' href='http://{$prefix}{$domain}/store/{$_SESSION['screen_name']}' class='btn btn-large btn-info'>Start MyGeoCloud</a>";
} else {
	echo "<a style='margin-top:40px' href='http://{$prefix}{$domain}/createstore' class='btn btn-large btn-info'>Create MyGeoCloud</a>";
	echo "<p>It will take a minute.</p>";
}
?>
</div>
<div class="span4" style="border-left:4px solid #F1F1F1;display: block;height: 250px;margin-top: 0px;padding-left: 40px;padding-top: 40px">
	<h1></h1>
</div>
</div>
</div>
</body>
</html>