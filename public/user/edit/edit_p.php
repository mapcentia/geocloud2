<?php
use \app\inc\Model;
use \app\models\Setting;

include('../header.php');
$postgisObject = new Model();
include('../vdaemon/vdaemon.php');
include('../html_header.php');
//  Check if user is logged in - and redirect if this is the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
    die("<script>window.location='{$userHostName}/user/login'</script>");
}
function PasswordCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    global $passwordChanged;

    $sOldPassword = VDFormat($_POST['OldPassword'], true);
    $sOldPassword = Setting::encryptPw($sOldPassword);

    $sNewPassword = VDFormat($_POST['Password'], true);
    $sNewPassword = Setting::encryptPw($sNewPassword);

    $oStatus->bValid = false;
    $oStatus->sErrMsg = "User ID '$sValue' already exist";

    $sQuery = "SELECT * FROM {$sTable} WHERE screenname = :sUserID AND pw = :sPassword";
    $res = $postgisObject->prepare($sQuery);
    $res->execute(array(":sUserID" => ($_SESSION['subuser']) ? : $_SESSION['screen_name'], ":sPassword" => $sOldPassword));
    $row = $postgisObject->fetchRow($res);

    if ($row['screenname']) {
        $sQuery = "UPDATE {$sTable} SET pw = :sNewPassword WHERE screenname = :sUserID";
        $res = $postgisObject->prepare($sQuery);
        if ($res->execute(array(":sUserID" => ($_SESSION['subuser']) ? : $_SESSION['screen_name'], ":sNewPassword" => $sNewPassword))) {
            $oStatus->bValid = 1;
        }
    } else {
        $oStatus->bValid = 0;
    }
}

if ($oVDaemonStatus && $oVDaemonStatus->bValid) {
    ?>
    <div id="alert" class="alert alert-success"
         style="width: 200px;margin-right: auto; margin-left: auto;margin-top: 100px">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        Your password is changed
    </div>
    <script>$('#alert').bind('closed', function () {
            window.location = '/user/login/p';
        })</script>
<?php
}
