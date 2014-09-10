<?php
use \app\inc\Model;
use \app\models\Setting;
use \app\conf\App;

include('../header.php');
$postgisObject = new Model();
include('../vdaemon/vdaemon.php');
include('../html_header.php');
//  Check if user is logged in - and redirect if this is the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name'] || $_SESSION['subuser'] != false) {
    die("<script>window.location='{$userHostName}/user/login'</script>");
}
function UserIDCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    global $host;
    $sUserID = Model::toAscii($sValue, NULL, "_");

    $oStatus->bValid = false;

    $sQuery = "SELECT COUNT(*) AS count FROM $sTable WHERE screenname = :sUserID";
    $res = $postgisObject->prepare($sQuery);
    $res->execute(array(":sUserID" => $sUserID));
    $rowScreenname = $postgisObject->fetchRow($res);

    if ($rowScreenname['count'] > 0) {
        $oStatus->sErrMsg = "<span class='label label-warning'>User name already taken</span>";
    } else {
        $oStatus->bValid = 1;
        $prefix = ($_SESSION['zone']) ? App::$param['domainPrefix'] . $_SESSION['zone'] . "." : "";
        if (App::$param['domain']) {
            $host = "//" . $prefix . App::$param['domain'];
        } else {
            if (!\app\conf\App::$param['host']) {
                include_once("../../../app/conf/hosts.php");
            }
            $host = App::$param['host'];
        }
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
$res->execute(array(":sUserID" => $sUserID, ":sPassword" => $sPassword, ":sEmail" => $sEmail, ":sZone" => $_SESSION['zone'], ":sParentDb" => $_SESSION['screen_name']));
$row = $res->fetch();

if (!$row['created']) {
    die("Some thing went wrong! Try again.");
}

if ($oVDaemonStatus && $oVDaemonStatus->bValid) {
    if ($_POST['schema']) {
        ?>
        <script>
            var hostName = "<?php echo $host ?>";
            $(window).ready(function () {
                $.ajax({
                    url: hostName + '/controllers/database/createschema?schema=<?php echo $sUserID ?>',
                    dataType: 'jsonp',
                    jsonp: 'jsonp_callback',
                    success: function (response) {
                        if (response.success === true) {
                            $("#alert-schema").show();
                        }
                        else {
                            $('#schema-failure').modal({ backdrop: 'static', keyboard: false })
                        }
                    }
                });
            });
        </script>
    <?php } ?>
    <div id="schema-failure" class="modal fade">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title">Schema was not created</h4>
                </div>
                <div class="modal-body">
                    <p>Schema '<?php echo $sUserID ?>' was not created. It may already exist. If not, you can always
                        create it manually from Admin</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Ok</button>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div><!-- /.modal -->
    <div id="alert" class="center alert alert-success"
         style="width: 300px;margin-right: auto; margin-left: auto;margin-top: 100px">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h3>User <?php echo $sUserID ?> is created</h3>
    </div>
    <div id="alert-schema" class="center alert alert-success"
         style="width: 300px;margin-right: auto; margin-left: auto;margin-top: 10px;display: none">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        <h3>Schema <?php echo $sUserID ?> is created</h3>
    </div>
    <script>
        $('#alert').bind('closed', function () {
            window.location = '/user/login/p';
        });
        $('#alert-schema').bind('closed', function () {
            window.location = '/user/login/p';
        });
    </script>
<?php
}
