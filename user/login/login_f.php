<?php
session_start();
include '../header.html';
include("../../conf/main.php");
include '../../libs/functions.php';
include '../../model/settings_viewer.php';
$postgisdb = 'mygeocloud';
$sTable    = 'users';
$postgisObject = new postgis();


include('../../libs/vdaemon/vdaemon.php'); 


function UserIDCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    global $sUserID;
    $sUserID = postgis::toAscii($sValue,NULL,"_");
    $sPassword = VDFormat($_POST['Password'], true);
	$sPassword=md5($sPassword);ings_viewerssword;

    $oStatus->bValid = false;
    $oStatus->sErrMsg = "User ID '$sValue' already exist";
    
    $sQuery = "SELECT COUNT(*) as count FROM {$sTable} WHERE screenname = '{$sUserID}' AND pw='{$sPassword}'";
    $res = $postgisObject->execQuery($sQuery);
    $row = $postgisObject->fetchRow($res);
    
    //echo($sQuery);
    //die();

    if ($row['count']>0)
    {
        $oStatus->bValid = 1;
        $postgisObject->numRows($res);
    }
    else {
    	$oStatus->bValid = 0;

    }
}

if ($oVDaemonStatus && $oVDaemonStatus->bValid)
{
    // Login successful.
    $_SESSION['VDaemonData']=null;
    $_SESSION['auth'] = true;
	$_SESSION['screen_name'] = $sUserID;
    header("location: login_p?screen_name={$_SESSION['screen_name']}");
}
?>


<form action="/user/login/" method="post" id="SelfSubmit" runat="vdaemon">
  <table border="0" cellpadding="2" cellspacing="0">
    <tr>
      <td colspan="2">
        <vllabel
          errtext="User ID or Password incorrect"
          validators="UserID,UserIDExist,Password"
          errclass="error">&nbsp;
        </vllabel>
      </td>
    </tr>
    <tr>
      <td width="80">User name:</td>
      <td width="200">
        <input name="UserID" type="text" class="control" size="20">
        <vlvalidator name="UserID" type="required" control="UserID">
        <vlvalidator name="UserIDExist" type="custom" control="UserID" function="UserIDCheck">
      </td>
    </tr>
    <tr>
      <td>Password:</td>
      <td>
        <input name="Password" type="password" class="control" size="20">
        <vlvalidator type="required" name="Password" control="Password">
      </td>
    </tr>
    <tr>
      <td colspan="2">
        <input name="submit" type="submit" class="control" value="Login">
      </td>
    </tr>
  </table>
</form>
<p>Not a user yet? <a href="/user/signup/">Signup</a></p>
</div>
</div>
</div>

</body>
</html>
<?php VDEnd(); ?>