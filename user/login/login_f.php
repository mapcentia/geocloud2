<?php
include '../header.php';
$postgisObject = new postgis();
include ('../../libs/vdaemon/vdaemon.php');
// Check if user is logged in - and redirect if this is the case
if ($_SESSION['auth'] && $_SESSION['screen_name']) {
	die("<script>window.location='/user/login/p'</script>");
}
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

	$sQuery = "SELECT * FROM {$sTable} WHERE screenname = :sUserID AND pw = :sPassword";
	$res = $postgisObject -> prepare($sQuery);
	$res -> execute(array(":sUserID" => $sUserID, ":sPassword" => $sPassword));
	$row = $postgisObject -> fetchRow($res);
	//echo($sQuery);
	//die();
	if ($row['screenname']) {
		$oStatus -> bValid = 1;
		// Login successful.
		$_SESSION['zone'] = $row['zone'];
		$_SESSION['VDaemonData'] = null;
		$_SESSION['auth'] = true;
		$_SESSION['screen_name'] = $sUserID;
	} else {
		$oStatus -> bValid = 0;
	}
}

if ($oVDaemonStatus && $oVDaemonStatus -> bValid) {
	header("location: p");
}
?>

<form action="/user/login/" method="post" id="SelfSubmit" runat="vdaemon" class="">

	<div class="control-group" style="margin-top: 40px">
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

	<p>
		Not using MyGeoCloud? <b><a href="/user/signup/">Sign up</a></b>
	</p>
	</div>
	<div class="span4" style="border-left:4px solid #F1F1F1;display: block;height: 250px;margin-top: 0px;padding-left: 40px;padding-top: 40px">
		<h1>Log in to MyGeoCloud</h1>
		<div style="height: 2em">
			<vllabel
			errtext="<span class='label label-warning'>User ID or Password incorrect</span>"
			validators="UserID,UserIDExist,Password"
			errclass="error">
				&nbsp;
			</vllabel>
		</div>
	</div>
</form>

</div>
</div>

</body>
</html>
<?php VDEnd(); ?>