<?php
include('../header.php');
include('../vdaemon/vdaemon.php');
include('../html_header.php');
?>
<div class="container">
    <div class="dialog">
        <img src="../assets/images/MapCentia_500.png" id="logo">

        <form action="/user/edit/p" method="post" id="SelfSubmit" runat="vdaemon" class="">
            <h3>Change password</h3>

            <div class="control-group first">

                <div class="controls">
                    <vllabel
                        errtext="<span class='label label-important'>Password incorrect</span>"
                        validators="OldPassword,OldPasswordRight"
                        errclass="error">
                        Old password
                    </vllabel>

                    <input name="OldPassword" type="password" class="control" size="20">
                </div>
                <vlvalidator type="required" name="OldPassword" control="OldPassword">
                    <vlvalidator type="custom" name="OldPasswordRight" control="OldPassword" function="PasswordCheck">
            </div>


            <div class="controls">
                <vllabel errclass="error" validators="Password" for="Password" cerrclass="controlerror"
                         errtext="<span class='label label-important'>You must type a password</span>">
                    Password
                </vllabel>
                <input name="Password" type="password" class="control" id="Password" size="15"/>
                <vlvalidator type="required" name="Password" control="Password"/>
            </div>

            <div class="controls">
                <vllabel validators="Password,PassCmp" errclass="error" for="Password2" cerrclass="controlerror"
                         errtext="<span class='label label-important'>Both Password fields must be equal</span>">
                    Confirm Password
                </vllabel>
                <input name="Password2" type="password" class="control" id="Password2" size="15">
                <vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
                             operator="e" validtype="string">
            </div>
            <div class="control-group">
                <input name="submit" type="submit" class="btn btn-info" value="Change password">
            </div>
        </form>
    </div>
</div>
</body>
</html>
<?php VDEnd(); ?>
