<?php
include '../header.php';
// Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
	die("<script>window.location='http://{$domain}/user/login'</script>");
}
// We set the 
$postgisdb = $nodeDbIPs[$_SESSION['zone']];
$db = new databases();
if ($db -> doesDbExist(postgis::toAscii($_SESSION['screen_name'], NULL, "_"))) {
	echo "<a style='margin-top:40px' href='http://{$_SESSION['zone']}.{$domain}/store/{$_SESSION['screen_name']}' class='btn btn-large btn-info'>Start MyGeoCloud</a>";
} else {
	echo "<a style='margin-top:40px' href='http://{$_SESSION['zone']}.{$domain}/createstore' class='btn btn-large btn-info'>Create MyGeoCloud</a>";
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