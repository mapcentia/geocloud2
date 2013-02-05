<?php
include '../header.php';
include ('../../libs/vdaemon/vdaemon.php');
?>

<form id="Register" action="p" method="POST" runat="vdaemon" disablebuttons="all">
	<div class="control-group" style="margin-top: 40px">
		<div class="controls">
			<vllabel validators="UserID,UserIDExist" errclass="error" for="UserID" cerrclass="controlerror">
				User ID
			</vllabel>
			<vlvalidator name="UserID" type="required" control="UserID" errmsg="<span class='label label-warning'>User ID required</span>">
				<vlvalidator name="UserIDExist" type="custom" control="UserID" errmsg="<span class='label label-warning'>User ID already exist</span>" function="UserIDCheck">
					<input name="UserID" type="text" cladss="control" id="UserID" sizde="15">
					<span class="help-inline"></span>
		</div>

		<div class="controls">

			<vllabel errclass="error" validators="Email" for="Email" cerrclass="controlerror">
				E-mail:
			</vllabel>

			<input name="Email" type="TEXT" class="control" id="Email" size="15">
			<vlvalidator type="format" format="email" name="Email" control="Email" errmsg="<span class='label label-warning'>Invalid E-mail</span>">
		</div>

		<div class="controls">
			<vllabel errclass="error" validators="Password,PassCmp" for="Password" cerrclass="controlerror">
				Password
			</vllabel>
			<input name="Password" type="password" class="control" id="Password" size="15">
			<vlvalidator type="required" name="Password" control="Password" errmsg="<span class='label label-warning'>Password required</span>">
				<vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
				operator="e" validtype="string" errmsg="<span class='label label-warning'>Both Password fields must be equal</span>">
		</div>

		<div class="controls">
			<vllabel validators="Password,PassCmp" errclass="error" for="Password2" cerrclass="controlerror">
				Confirm Password
			</vllabel>
			<input name="Password2" type="PASSWORD" class="control" id="Password2" size="15">
		</div>
		<div class="controls">
			Availability Zones
			<label class="radio">
				<input type="radio" name="Zone" value="us1" checked>
				North America </label>
			<label class="radio">
				<input type="radio" name="Zone" value="eu1">
				Europe </label>
		</div>
		
		<br>

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
		<div class="controls">
			<label class="checkbox">
				<input name="Agreement" type="checkbox" id="Agreement" value="1">
				<vllabel errclass="error" validators="Agreement" for="Agreement">
					I agree with the terms of service
				</vllabel> </label>

			<vlvalidator type="required" name="Agreement" control="Agreement" errmsg="<span class='label label-warning'>Agreement must be checked</span>">

		</div>
	</div>

	</div>
	<div class="span4" style="border-left:4px solid #F1F1F1;display: block;height: 250px;margin-top: 0px;padding-left: 40px;padding-top: 40px">
		<h1>Sign up to MyGeoCloud</h1>
		<div style="height: 2em">
			<vlsummary class="error" headertext="" displaymode="bulletlist">
		</div>
	</div>
</form>
</div>
</div>
</body>
</html>
<?php VDEnd(); ?>