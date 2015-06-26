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
$sNewPassword = VDFormat($_POST['Password'], true);
$sNewPassword = Setting::encryptPw($sNewPassword);
$sNewGroup = VDFormat($_POST['Usergroup'], true);
$sUser = VDFormat($_POST['user'], true);
$oStatus->bValid = false;
if ($_POST['Password']) {
    $sQuery = "UPDATE {$sTable} SET usergroup = :sNewGroup, pw = :sNewPassword WHERE screenname = :sUserID";
    $res = $postgisObject->prepare($sQuery);
    if ($res->execute(array(":sUserID" => $sUser, ":sNewGroup" => $sNewGroup, ":sNewPassword" => $sNewPassword))) {
        $oStatus->bValid = 1;
    }
} else {
    $sQuery = "UPDATE {$sTable} SET usergroup = :sNewGroup WHERE screenname = :sUserID";
    $res = $postgisObject->prepare($sQuery);
    if ($res->execute(array(":sUserID" => $sUser, ":sNewGroup" => $sNewGroup))) {
        $oStatus->bValid = 1;
    }
}

if ($oVDaemonStatus && $oVDaemonStatus->bValid) {
    ?>
    <div id="alert" class="center alert alert-success"
         style="width: 400px;margin-right: auto; margin-left: auto;margin-top: 100px; display: none">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h3>Sub-user settings are changed</h3>
    </div>
    <script>
        $('#alert').bind('closed', function () {
            alert()
            window.location = '/user/login/p';
        });
        var hostName = "<?php echo $host ?>";
        $.ajax({
            url: hostName + '/controllers/setting/usergroups',
            dataType: 'jsonp',
            jsonp: 'jsonp_callback',
            data: 'q={"data":{"<?php echo $sUser;?>":"<?php echo $sNewGroup;?>"}}',
            success: function (response) {
                if (response.success === true) {
                    $("#alert").show();
                }
                else {
                    $('#schema-failure').modal({backdrop: 'static', keyboard: false})
                }
            }
        });
    </script>
<?php
}
