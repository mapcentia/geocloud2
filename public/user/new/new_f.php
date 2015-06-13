<?php
include('../header.php');
include('../vdaemon/vdaemon.php');
include('../html_header.php');
if (!$_SESSION['auth'] || !$_SESSION['screen_name'] || $_SESSION['subuser'] != false) {
    die("<script>window.location='/user/login'</script>");
}
?>
    <div class="container">
        <div id="main">
            <div class="dialog-center">
                <form id="Register" action="p" method="POST" runat="vdaemon" disablebuttons="all">
                    <div class="form-group">
                        <div class="center">
                            <div class="alert alert-info first" style="margin-bottom: 0">
                                <h3>Create a new sub-user</h3>
                                The sub-user can log into this database in the same way as the parent user, which can
                                grant
                                privileges to sub-users on layer level. The sub-user name must be globally unique.
                            </div>
                        </div>
                        <div class="controls">
                            <label>
                                <vlsummary class="error" headertext="" displaymode="paragraph">
                            </label>
                            <vllabel validators="UserID" errclass="error"
                                     errtext="<span class='label label-warning'>User name required</span>" for="UserID">
                                &nbsp;
                            </vllabel>
                            <vlvalidator name="UserID" type="required" control="UserID"/>
                            <vlvalidator name="UserIDExist" type="custom" control="UserID"
                                         errmsg="<span class='label label-warning'>User name already exist</span>"
                                         function="UserIDCheck"/>
                            <input name="UserID" type="text" id="UserID" class="form-control"
                                   placeholder="Sub-user name"/>
                            <span class="help-inline"></span>
                        </div>
                        <div class="controls">
                            <vllabel errclass="error" validators="Email1,Email2" for="Email"
                                     errtext="<span class='label label-warning'>Hmm, that does not look like an email</span>">
                                &nbsp;
                            </vllabel>

                            <input name="Email" type="TEXT" class="form-control" id="Email" placeholder="Email">
                            <vlvalidator type="required" format="email" name="Email1" control="Email"/>
                            <vlvalidator type="format" format="email" name="Email2" control="Email"/>

                        </div>
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
                            <input name="Password2" type="PASSWORD" class="form-control" id="Password2"
                                   placeholder="Confirm Password">
                            <vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
                                         operator="e" validtype="string">
                        </div>
                        <div class="center">
                            <div class="alert alert-info first" style="margin-bottom: 0">
                                You can choose to create a schema for the new sub-user. The sub-user will be able to
                                create new layers with all privileges inside the schema. The sub-user will also be able
                                to grant privileges on layers inside the schema to other sub-users. You can also create
                                the schema later - just use the name of the sub-user.
                            </div>
                        </div>
                        <div class="controls">
                            <label class="checkbox">
                                <input name="schema" type="checkbox" id="schema" value="1">
                                <label>
                                    Create schema for new user
                                </label>
                            </label>
                        </div>
                        <div>
                        <select name="Usergroup" class="form-control">
                        <option value=\""></option>
                        <?php
foreach($_SESSION['subusers'] as $subuser) {
        echo "<option value=\"{$subuser}\">{$subuser}</option>";
}

?>
</select>
</div>
                        <div class="controls">
                            <input type="submit" class="btn btn-danger full-width" value="Create Sub-User">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </body>
    </html>
<?php VDEnd(); ?>