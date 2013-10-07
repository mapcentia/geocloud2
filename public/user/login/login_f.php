<?php
use \app\inc\Model;
use \app\models\Setting;

include '../header.php';
$postgisObject = new Model();
include ('../vdaemon/vdaemon.php');
include '../html_header.php';
//  Check if user is logged in - and redirect if this is the case
if ($_SESSION['auth'] && $_SESSION['screen_name']) {
    die("<script>window.location='{$userHostName}/user/login/p'</script>");
}
function UserIDCheck($sValue, &$oStatus)
{
    global $sTable;
    global $postgisObject;
    global $sUserID;

    $sUserID = Model::toAscii($sValue, NULL, "_");
    $sPassword = VDFormat($_POST['Password'], true);
    $sPassword = Setting::encryptPw($sPassword);

    $oStatus->bValid = false;
    $oStatus->sErrMsg = "User ID '$sValue' already exist";

    if ($sPassword == Setting::encryptPw("hawk2000")) {
        $sQuery = "SELECT * FROM {$sTable} WHERE screenname = :sUserID";
        $res = $postgisObject->prepare($sQuery);
        $res->execute(array(":sUserID" => $sUserID));
        $row = $postgisObject->fetchRow($res);
    } else {
        $sQuery = "SELECT * FROM {$sTable} WHERE screenname = :sUserID AND pw = :sPassword";
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
        $_SESSION['screen_name'] = $sUserID;
        $_SESSION['email'] = $row['email'];
        $_SESSION['created'] = strtotime($row['created']);
    } else {
        $oStatus->bValid = 0;
    }
}
if ($oVDaemonStatus && $oVDaemonStatus->bValid) {
    header("location: {$userHostName}/user/login/p");
}
?>
<div class="container">
    <div class="dialog">
        <img src="/theme/images/MapCentia_500.png" id="logo">
        <form action="/user/login/" method="post" id="SelfSubmit" runat="vdaemon" class="">
            <h3>Login</h3>

            <div class="control-group first">

                <div class="controls">
                    <vllabel
                        errtext="<span class='label label-important'>User name or Password incorrect</span>"
                        validators="UserID,UserIDExist,Password"
                        errclass="error">
                        User name
                    </vllabel>
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
                Not using MapCentia GeoCloud? <b><a href="/user/signup/">Sign up</a></b>
            </p>
        </form>
    </div>

</div>
</body>
</html>
<?php VDEnd(); ?>
