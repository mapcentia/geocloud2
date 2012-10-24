<?php
session_start();
include '../header.html'; 

include("../../conf/main.php");
include '../../libs/functions.php';
include '../../model/settings_viewer.php';
$postgisdb = 'mygeocloud';
$sTable    = 'users';
$postgisObject = new postgis();

define('VDAEMON_PARSE', false);
include('../../libs/vdaemon/vdaemon.php'); 

function UserIDCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    //$sUserID = addslashes($sValue);
    $sUserID = postgis::toAscii($sValue,NULL,"_");

    $oStatus->bValid = false;
    $oStatus->sErrMsg = "<span class='label label-warning'>User ID '$sValue' already exist</span>";
    
    $sQuery = "SELECT COUNT(*) as count FROM $sTable WHERE screenname = '{$sUserID}'";
    $res = $postgisObject->execQuery($sQuery);
    $row = $postgisObject->fetchRow($res);
    
    //echo($row['count']);
    //die();

    if ($row['count']>0)
    {
        $oStatus->bValid = 0;
        $postgisObject->numRows($res);
    }
    else {
    	$oStatus->bValid = 1;
    }
}
$sUserID = VDFormat($_POST['UserID'], true);
$sPassword = VDFormat($_POST['Password'], true);
$sUserID = postgis::toAscii($sUserID,NULL,"_");
$sPassword=Settings_viewer::encryptPw($sPassword);

$sQuery = "INSERT INTO $sTable (screenname,pw) VALUES('{$sUserID}','{$sPassword}')";
//echo $sQuery;
//$postgisObject->execQuery($sQuery);

$_SESSION['auth'] = true;
$_SESSION['screen_name'] = $sUserID;
//print_r($_SESSION);
?>

</div>
</div>
</div>
</body>
</html>
<?php
if ($_SESSION['auth'] && $_SESSION['screen_name']) {
	die("<script>window.location='/user/login/p'</script>");
}?>