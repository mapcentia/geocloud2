<?php
session_start();
// Check if user is logged in - and redirect if this is the case
if ($_SESSION['auth'] && $_SESSION['screen_name']) {
	die("<script>window.location='/user/login/p'</script>");
}
include '../header.html';
include '../../conf/main.php';
include '../../libs/functions.php';
include '../../model/settings_viewer.php';
$postgisdb = 'mygeocloud';
$sTable = 'users';
$postgisObject = new postgis();
include ('../../libs/vdaemon/vdaemon.php');
function UserIDCheck($sValue, &$oStatus) {
	global $sTable;
	global $postgisObject;
	global $sUserID;
	$sUserID = postgis::toAscii($sValue, NULL, "_");
	$sPassword = VDFormat($_POST['Password'], true);
	$sPassword = Settings_viewer::encryptPw($sPassword);
	ings_viewerssword;

	$oStatus -> bValid = false;
	$oStatus -> sErrMsg = "User ID '$sValue' already exist";

	$sQuery = "SELECT COUNT(*) as count FROM {$sTable} WHERE screenname = '{$sUserID}' AND pw='{$sPassword}'";
	$res = $postgisObject -> execQuery($sQuery);
	$row = $postgisObject -> fetchRow($res);
	//echo($sQuery);
	//die();
	if ($row['count'] > 0) {
		$oStatus -> bValid = 1;
		$postgisObject -> numRows($res);
	} else {
		$oStatus -> bValid = 0;
	}
}
if ($oVDaemonStatus && $oVDaemonStatus -> bValid) {
	// Login successful.
	$_SESSION['VDaemonData'] = null;
	$_SESSION['auth'] = true;
	$_SESSION['screen_name'] = $sUserID;
	header("location: p");
}
?>

<form action="/user/login/" method="post" id="SelfSubmit" runat="vdaemon" class="">
	<div style="height: 2em">
		<vllabel
		errtext="<span class='label label-warning'>User ID or Password incorrect</span>"
		validators="UserID,UserIDExist,Password"
		errclass="error">
			&nbsp;
		</vllabel>
	</div>
	<div class="control-group">
		<label class="control-label" for="inputEmail">User ID</label>
		<div class="controls">
			<input name="UserID" type="text" class="control" size="20">
		</div>
		<vlvalidator name="UserID" type="required" control="UserID">
			<vlvalidator name="UserIDExist" type="custom" control="UserID" function="UserIDCheck">
	</div>

	<div class="control-group">
		<label class="control-label" for="inputEmail">Password</label>
		<div class="controls">
			<input name="Password" type="password" class="control" size="20">
		</div>
		<vlvalidator type="required" name="Password" control="Password">
	</div>
	<div class="control-group">
		<input name="submit" type="submit" class="btn btn-info" value="Log in">
	</div>

</form>
<p>
	Not using MyGeoCloud? <b><a href="/user/signup/">Sign up</a></b>
</p>
</div>
</div>
</div>

</body>
</html>
<?php VDEnd(); ?>