<?php
session_start();
// Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
	die("<script>window.location='/user/login'</script>");
}
include '../header.html';
include '../../conf/main.php';
include '../../libs/functions.php';
include 'model/databases.php';
//print_r($_SESSION);
?>
<!--<p>Hi <?php echo $_SESSION['screen_name']; ?></p>-->
<?php
$db = new databases();
if ($db -> doesDbExist(postgis::toAscii($_SESSION['screen_name'], NULL, "_"))) {
	echo "<a style='margin-top:40px' href='/store/{$_SESSION['screen_name']}' class='btn btn-large btn-info'>Start MyGeoCloud</a>";
} else {
	echo "<a style='margin-top:40px' href='/createstore' class='btn btn-large btn-info'>Create MyGeoCloud</a>";
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