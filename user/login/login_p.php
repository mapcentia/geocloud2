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
<p>Hi <?php echo $_SESSION['screen_name']; ?></p>
<?php
$db = new databases();
if ($db -> doesDbExist(postgis::toAscii($_SESSION['screen_name'], NULL, "_"))) {
	echo "<a href='/store/{$_SESSION['screen_name']}' class='btn btn-info'>Start MyGeoCloud</a>";
} else {
	echo "<a href='/createstore' class='btn btn-info'>Create MyGeoCloud</a>";
}
?>
</div>
</div>
</div>
</body>
</html>