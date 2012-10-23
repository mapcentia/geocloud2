<?php
include '../header.html'; 
include('../../libs/vdaemon/vdaemon.php'); ?>

<form id="Register" action="registration_p.php" method="POST" runat="vdaemon" disablebuttons="all">
	<div style="height: 2em"><vlsummary class="error" headertext="" displaymode="paragraph"></div>
	<div class="control-group">
		<div class="controls">
			<vllabel validators="UserID,UserIDExist" errclass="error" for="UserID" cerrclass="controlerror">User ID</vllabel>
			<vlvalidator name="UserID" type="required" control="UserID" errmsg="<span class='label label-warning'>User ID required</span>">
		    <vlvalidator name="UserIDExist" type="custom" control="UserID" errmsg="<span class='label label-warning'>User ID already exist</span>" function="UserIDCheck">
	        <input name="UserID" type="text" cladss="control" id="UserID" sizde="15">
	        <span class="help-inline"></span>
	    </div>    
    
	    <div class="controls">
	        <vllabel errclass="error" validators="Password,PassCmp" for="Password" cerrclass="controlerror">Password</vllabel>
	        <input name="Password" type="password" class="control" id="Password" size="15">
	        <vlvalidator type="required" name="Password" control="Password" errmsg="<span class='label label-warning'>Password required</span>">
	        <vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
	          operator="e" validtype="string" errmsg="<span class='label label-warning'>Both Password fields must be equal</span>">
	    </div>
    
	    <div class="controls">
	        <vllabel validators="Password,PassCmp" errclass="error" for="Password2" cerrclass="controlerror">Confirm Password</vllabel>
	        <input name="Password2" type="PASSWORD" class="control" id="Password2" size="15">
	    </div>
 
	<!--
    <tr>
      <td>
        <vllabel errclass="error" validators="NameReq,NameRegExp" for="Name" cerrclass="controlerror">Name:</vllabel>
      </td>
      <td>
        <input name="Name" type="text" class="control" id="Name" size="15">
        <vlvalidator type="required" name="NameReq" control="Name" errmsg="Name required">
        <vlvalidator type="regexp" name="NameRegExp" control="Name" regexp="/^[A-Za-z'\s]*$/" errmsg="Invalid Name">
      </td>
    </tr>
    <tr>
      <td>
        <vllabel errclass="error" validators="EmailReq,Email" for="Email" cerrclass="controlerror">E-mail:</vllabel>
      </td>
      <td>
        <input name="Email" type="TEXT" class="control" id="Email" size="15">
        <vlvalidator type="required" name="EmailReq" control="Email" errmsg="E-mail required">
        <vlvalidator type="format" format="email" name="Email" control="Email" errmsg="Invalid E-mail">
      </td>
    </tr>
    <tr>
      <td colspan=2>
        <input name="Agreement" type="checkbox" id="Agreement" value="1">
        <vllabel errclass="error" validators="Agreement" for="Agreement">I agree with the terms of service</vllabel>
        <vlvalidator type="required" name="Agreement" control="Agreement" errmsg="Agreement checkbox must be selected">
      </td>
    </tr>
	-->
	 <div class="control-group">
	 	<div class="controls">
	        <input type="submit" class="btn btn-info" value="Sign up">
	        <input type="reset" class="btn" value="Reset">
		</div>
       </div>
      </div>
</form>
</div>
</div>
</div>
</body>
</html>
<?php VDEnd(); ?>