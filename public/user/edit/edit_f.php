<?php
include('../header.php');
include('../vdaemon/vdaemon.php');
include('../html_header.php');
//  Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
    die("<script>window.location='{$userHostName}/user/login'</script>");
}
?>
<div class="container">
    <div id="main">
        <div class="dialog">
            <form action="/user/edit/p" method="post" id="SelfSubmit" runat="vdaemon" class="">
                <div class="form-group">
                    <div class="center">
                        <div class="alert alert-info first" style="margin-bottom: 0">
                            <h3>Change password</h3>
                        </div>
                    </div>
                    <div class="controls">
                        <vllabel
                            errtext="<span class='label label-warning'>Password incorrect</span>"
                            validators="OldPassword,OldPasswordRight">
                            &nbsp;
                        </vllabel>
                        <input name="OldPassword" type="password" class="form-control" placeholder="Old password">
                    </div>
                    <vlvalidator type="required" name="OldPassword" control="OldPassword"/>
                    <vlvalidator type="custom" name="OldPasswordRight" control="OldPassword"
                                 function="PasswordCheck"/>
                    <div class="controls">
                        <vllabel errclass="error" validators="Password" for="Password"
                                 errtext="<span class='label label-warning'>You must type a password</span>">
                            &nbsp;
                        </vllabel>
                        <input name="Password" type="password" class="form-control" id="Password"
                               placeholder="Password"/>
                        <vlvalidator type="required" name="Password" control="Password"/>
                    </div>

                    <div class="controls">
                        <vllabel validators="Password,PassCmp" errclass="error" for="Password2"
                                 errtext="<span class='label label-warning'>Both Password fields must be equal</span>">
                            &nbsp;
                        </vllabel>
                        <input name="Password2" type="password" class="form-control last" id="Password2"
                               placeholder="Confirm Password">
                        <vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
                                     operator="e" validtype="string">
                    </div>
                    <input name="submit" type="submit" class="btn btn-danger full-width"
                           value="Change password">
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
<?php VDEnd(); ?>
