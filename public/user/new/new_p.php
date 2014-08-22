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
function UserIDCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    $sUserID = Model::toAscii($sValue, NULL, "_");
    $sEmail = VDFormat($_POST['Email'], true);

    $oStatus->bValid = false;

    $sQuery = "SELECT COUNT(*) AS count FROM $sTable WHERE screenname = :sUserID";
    $res = $postgisObject->prepare($sQuery);
    $res->execute(array(":sUserID" => $sUserID));
    $rowScreenname = $postgisObject->fetchRow($res);

    /*$sQuery = "SELECT COUNT(*) AS count FROM $sTable WHERE email = :sEmail";
    $res = $postgisObject->prepare($sQuery);
    $res->execute(array(":sEmail" => $sEmail));
    $rowEmail = $postgisObject->fetchRow($res);*/

    if ($rowScreenname['count'] > 0 && $rowEmail['count'] == 0) {
        $oStatus->sErrMsg = "<span class='label label-warning'>User name already taken</span>";
    } /*elseif ($rowEmail['count'] > 0 && $rowScreenname['count'] == 0) {
        $oStatus->sErrMsg = "<span class='label label-warning'>Email already is use</span>";
    }*/ elseif ($rowScreenname['count'] > 0 && $rowEmail['count'] > 0) {
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

$sQuery = "INSERT INTO {$sTable} (screenname,pw,email,zone,parentdb) VALUES( :sUserID, :sPassword, :sEmail, :sZone, :sParentDb) RETURNING created";

$res = $postgisObject->prepare($sQuery);
$res->execute(array(":sUserID" => $sUserID, ":sPassword" => $sPassword, ":sEmail" => $sEmail, ":sZone" => $_SESSION['zone'], ":sParentDb"=>$_SESSION['screen_name']));
$row = $res->fetch();

if (!$row['created']) {
    die("Some thing went wrong! Try again.");
}

if ($oVDaemonStatus && $oVDaemonStatus->bValid) {
    ?>
    <div id="alert" class="center alert alert-success"
         style="width: 300px;margin-right: auto; margin-left: auto;margin-top: 100px">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h3>User <?php echo $sUserID?> is created</3>
    </div>
    <script>$('#alert').bind('closed', function () {
            window.location = '/user/login/p';
        })</script>
<?php
}
