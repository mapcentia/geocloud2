<?php
include('../header.php');
include('../vdaemon/vdaemon.php');
include('../html_header.php');
?>
    <div class="container">
        <div id="main">
            <div class="signup-hero">
                <?php echo \app\conf\App::$param['heroText']; ?>
            </div>
            <div class="dialog dialog-narrow">
                <div class="center">
                    <img src="<?php echo \app\conf\App::$param['loginLogo']; ?>" id="logo">
                </div>
                <form id="Register" action="p" method="POST" runat="vdaemon" disablebuttons="all">
                    <div class="form-group first">
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
                                         errmsg="<span class='label label-important'>User name already exist</span>"
                                         function="UserIDCheck"/>
                            <input name="UserID" type="text" class="form-control" id="UserID" placeholder="User name"/>
                            <span class="help-inline"></span>
                        </div>

                        <div class="controls">

                            <vllabel errclass="error" validators="Email1,Email2" for="Email"
                                     errtext="<span class='label label-warning'>Hmm, that does not look like an email</span>">
                                &nbsp;
                            </vllabel>

                            <input name="Email" type="TEXT" class="form-control" id="Email" size="15"
                                   placeholder="E-mail">
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
                            <input name="Password2" type="PASSWORD" class="form-control last" id="Password2"
                                   placeholder="Confirm password">
                            <vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
                                         operator="e" validtype="string">
                        </div>
                        <?php if (isset(\app\conf\App::$param['domain'])) { ?>
                            <div class="alert alert-info center">
                                Choose between data center locations
                            </div>
                            <div class="controls last">
                                <label class="radio-inline">
                                    <input type="radio" name="Zone" value="us1" checked>
                                    North America </label>
                                <label class="radio-inline">
                                    <input type="radio" name="Zone" value="eu1">
                                    Europe </label>
                                <!--<label class="radio">
                                    <input type="radio" name="Zone" value="local2">
                                    Local </label>-->
                            </div>
                        <?php } ?>
                        <div class="controls">
                            <input type="submit" class="btn btn-danger full-width" value="Create Account">
                        </div>
                        <div class="controls">
                            <label class="checkbox">
                                <input name="Agreement" type="checkbox" id="Agreement" value="1">
                                <vllabel errclass="error" validators="Agreement" for="Agreement"
                                         errtext="<span class='label label-warning'>Agreement must be checked</span>">
                                    I agree with the <a target="_blank"
                                                        href="http://www.mapcentia.com/en/geocloud/geocloud.htm#terms">terms
                                        of service</a>
                                </vllabel>
                            </label>
                            <vlvalidator type="required" name="Agreement" control="Agreement">
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    </body>
    </html>
<?php VDEnd(); ?>