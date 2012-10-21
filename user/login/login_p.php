<?php
session_start();
include '../header.html';
include("../../conf/main.php");
include '../../libs/functions.php';
include 'model/databases.php';
//print_r($_SESSION);
if ($_SESSION['screen_name']!=$_REQUEST['screen_name']){
	die("What?");
}
?>
<p>Hi <?php echo $_SESSION['screen_name'];?></p>
<?php
	$db = new databases();
		if ($db->doesDbExist(postgis::toAscii($_SESSION['screen_name'],NULL,"_"))) {
			echo "DB exist";
		}
		else{
			echo "DB does not exist";
		}
	
?>
</div>
</div>
</div>
</body>
</html>