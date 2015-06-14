<?php
use \app\inc\Model;

include('../header.php');
include('../vdaemon/vdaemon.php');
include('../html_header.php');
//  Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
    die("<script>window.location='{$userHostName}/user/login'</script>");
}
$postgisObject = new Model();
$user = $_GET["user"];
?>
<div class="container">
    <div id="main">
        <div class="dialog-center" style="width: 500px">
            <form action="/user/edit/pu" method="post" id="SelfSubmit" runat="vdaemon" class="">
                <div class="form-group">
                    <div class="center">
                        <div class="alert alert-info first" style="margin-bottom: 0">
                            <h3>Change sub-user settings</h3>

                            <div class="center">
                                Leave password field empty for keeping the current one.
                            </div>
                        </div>
                    </div>
                    <div class="controls" style="margin-top: 20px">
                        <input name="Password" type="password" class="form-control" id="Password"
                               placeholder="New password"/>
                    </div>

                    <div class="controls">
                        <vllabel validators="PassCmp" errclass="error" for="Password2"
                                 errtext="<span class='label label-warning'>Both Password fields must be equal</span>">
                            &nbsp;
                        </vllabel>
                        <input name="Password2" type="password" class="form-control last" id="Password2"
                               placeholder="Confirm Password">
                        <vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
                                     operator="e" validtype="string">
                    </div>
                    <div class="center">
                        <div class="alert alert-info first">
                            Select empty user group for setting no group.
                        </div>
                    </div>
                    <div class="controls" style="margin-bottom: 20px">
                        <select name="Usergroup" class="form-control">
                            <option value=""></option>
                            <?php
                            $sQuery = "SELECT usergroup FROM {$sTable} WHERE screenname = :sUserID";
                            $res = $postgisObject->prepare($sQuery);
                            $res->execute(array(":sUserID" => $user));
                            $row = $postgisObject->fetchRow($res);
                            foreach ($_SESSION['subusers'] as $subuser) {
                                if ($subuser != $user) {
                                    echo "<option value=\"{$subuser}\"";
                                    if ($subuser == $row["usergroup"]) {
                                        echo " SELECTED";
                                    }
                                    echo ">{$subuser}</option>";
                                }
                            }
                            ?>
                        </select>
                    </div>
                    <div class="controls">
                        <input name="submit" type="submit" class="btn btn-danger full-width"
                               value="Change">
                    </div>
                </div>
                <input type="hidden" name="user" value="<?php echo $user; ?>">
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php VDEnd(); ?>
