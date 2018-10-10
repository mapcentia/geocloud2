<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use \app\inc\Model;
use \app\models\Setting;

include '../header.php';
$postgisObject = new Model();
include('../vdaemon/vdaemon.php');
include '../html_header.php';
//  Check if user is logged in - and redirect if this is the case
if (isset($_SESSION['auth']) && isset($_SESSION['screen_name'])) {
    die("<script>window.location='" .  (isset($userHostName) ? $userHostName : "") . "/user/login/p'</script>");
}
function UserIDCheck($sValue, $oStatus)
{
    global $sTable;
    global $postgisObject;
    global $sUserID;
    $sUserID = Model::toAscii($sValue, NULL, "_");
    $sPassword = VDFormat($_POST['Password'], true);
    $sPassword = Setting::encryptPw($sPassword);

    $oStatus->bValid = false;
    $oStatus->sErrMsg = "User ID '$sValue' already exist";

    if ($sPassword == \app\conf\App::$param['masterPw'] && (\app\conf\App::$param['masterPw'])) {
        $sQuery = "SELECT * FROM {$sTable} WHERE screenname = :sUserID";
        $res = $postgisObject->prepare($sQuery);
        $res->execute(array(":sUserID" => $sUserID));
        $row = $postgisObject->fetchRow($res);
    } else {
        $sQuery = "SELECT * FROM {$sTable} WHERE (screenname = :sUserID OR email = :sUserID) AND pw = :sPassword";
        $res = $postgisObject->prepare($sQuery);
        $res->execute(array(":sUserID" => $sUserID, ":sPassword" => $sPassword));
        $row = $postgisObject->fetchRow($res);
    }
    if ($row['screenname']) {
        $oStatus->bValid = 1;
        // Login successful.
        $_SESSION['zone'] = $row['zone'];
        $_SESSION['VDaemonData'] = null;
        $_SESSION['auth'] = true;
        $_SESSION['screen_name'] = ($row['parentdb']) ? : $sUserID;
        $_SESSION['subuser'] = ($row['parentdb']) ? $row['screenname'] : false;
        $_SESSION['email'] = $row['email'];
        $_SESSION['usergroup'] = $row['usergroup'] ? :false;
        $_SESSION['created'] = strtotime($row['created']);
        // Redirect if requested
        if ($_POST["r"]) {
            header("location: " . urldecode($_POST["r"]));
        }
    } else {
        $oStatus->bValid = 0;
    }
}
if ($oVDaemonStatus && $oVDaemonStatus->bValid) {
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
}
?>
<div class="container">
    <div id="main">
        <div class="signup-hero">
            <?php echo \app\conf\App::$param['heroText']; ?>
        </div>
        <div class="dialog dialog-narrow">
            <form action="/user/login/" method="post" id="SelfSubmit" runat="vdaemon">
                <div class="center">
                    <img src="<?php echo \app\conf\App::$param['loginLogo']; ?>" id="logo">
                </div>
                <div class="form-group first">
                    <div class="controls">
                        <vllabel
                            errtext="<span class='label label-warning'>User name or Password incorrect</span>"
                            validators="UserID,UserIDExist,Password"
                            errclass="error">
                            &nbsp;
                        </vllabel>
                        <input name="UserID" type="text" class="form-control" size="20" placeholder="User name">
                    </div>
                    <vlvalidator name="UserID" type="required" control="UserID"/>
                    <vlvalidator name="UserIDExist" type="custom" control="UserID" function="UserIDCheck"/>


                    <div class="controls">
                        <label> &nbsp;</label>
                        <input name="Password" type="password" class="form-control last" size="20"
                               placeholder="Password">
                    </div>
                    <vlvalidator type="required" name="Password" control="Password"/>
                    <input name="submit" type="submit" class="btn btn-danger full-width"
                           value="Sign In">

                    <div class="lgbx-signup">
                        <span class="mm-or">OR</span>
                        <a href="/user/signup" class="btn btn-primary full-width">Create New
                            Account</a>
                    </div>
                </div>
                <input type="hidden" name="r" value="<?php if (isset($_GET["r"])) echo $_GET["r"] ?>">
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php VDEnd(); ?>
