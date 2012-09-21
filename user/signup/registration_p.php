<?php
session_start();
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
    $oStatus->sErrMsg = "User ID '$sValue' already exist";
    
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
echo $sQuery;
$postgisObject->execQuery($sQuery);

$_SESSION['auth'] = true;
$_SESSION['screen_name'] = $sUserID;
print_r($_SESSION);
?>
<html>
<head>
<title>Registration Form Sample</title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<link href="samples.css" rel="stylesheet" type="text/css">
</head>
<body>
<h1>Registration Form Sample</h1>
<p>Your data has been written to the database</p>
<table cellpadding="0" cellspacing="0" border="0">
  <tr>
    <td width="100">User ID:</td>
    <td width="300"><?php echo $_POST['UserID']; ?></td>
  </tr>
  <tr>
    <td>Password:</td>
    <td><?php echo $_POST['Password']; ?></td>
  </tr>
  <tr>
    <td>Name:</td>
    <td><?php echo $_POST['Name']; ?></td>
  </tr>
  <tr>
    <td>E-mail:</td>
    <td><?php echo $_POST['Email']; ?></td>
  </tr>
</table>
</body>
</html>