<?php
use \app\inc\Model;
use \app\models\Setting;

include '../header.php';
$postgisObject = new Model();
define('VDAEMON_PARSE', false);
include('../vdaemon/vdaemon.php');

function UserIDCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    $sUserID = Model::toAscii($sValue, NULL, "_");
    $sEmail = VDFormat($_POST['Email'], true);

    $oStatus->bValid = false;

    $sQuery = "SELECT COUNT(*) AS count FROM $sTable WHERE screenname = '{$sUserID}'";
    $res = $postgisObject->execQuery($sQuery);
    $rowScreenname = $postgisObject->fetchRow($res);

    $sQuery = "SELECT COUNT(*) AS count FROM $sTable WHERE email = '{$sEmail}'";
    $res = $postgisObject->execQuery($sQuery);
    $rowEmail = $postgisObject->fetchRow($res);

    if ($rowScreenname['count'] > 0 && $rowEmail['count'] == 0) {
        $oStatus->sErrMsg = "<span class='label label-warning'>User name already taken</span>";
    } elseif ($rowEmail['count'] > 0 && $rowScreenname['count'] == 0) {
        $oStatus->sErrMsg = "<span class='label label-warning'>Email already is use</span>";
    } elseif ($rowScreenname['count'] > 0 && $rowEmail['count'] > 0) {
        $oStatus->sErrMsg = "<span class='label label-warning'>User name taken and email in use</span>";
    } else {
        $oStatus->bValid = 1;
    }
}

$sUserID = VDFormat($_POST['UserID'], true);
$sPassword = VDFormat($_POST['Password'], true);
$sEmail = VDFormat($_POST['Email'], true);
$sZone = VDFormat($_POST['Zone'], true);

$sUserID = Model::toAscii($sUserID, NULL, "_");
$sPassword = Setting::encryptPw($sPassword);

$sQuery = "INSERT INTO {$sTable} (screenname,pw,email,zone) VALUES( :sUserID, :sPassword, :sEmail, :sZone) RETURNING created";

$res = $postgisObject->prepare($sQuery);
$res->execute(array(":sUserID" => $sUserID, ":sPassword" => $sPassword, ":sEmail" => $sEmail, ":sZone" => $sZone));
$row = $res->fetch();

if ($row['created']) {
    $_SESSION['auth'] = true;
    $_SESSION['screen_name'] = $sUserID;
    $_SESSION['zone'] = $sZone;
    $_SESSION['email'] = $sEmail;
    $_SESSION['created'] = strtotime($row['created']);
} else {
    die("Some thing went wrong!");
}

if ($_SESSION['auth'] && $_SESSION['screen_name']) {
    header("location: /user/login/p");
}
