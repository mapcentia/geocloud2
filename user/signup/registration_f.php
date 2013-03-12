<?php
include '../header.php';
include ('../../libs/vdaemon/vdaemon.php');
include '../html_header.php';
?>
<div class="container">
	<div class="dialog">
		<form id="Register" action="p" method="POST" runat="vdaemon" disablebuttons="all">
			<div class="control-group">
				<h3>Sign up for MapCentia GeoCloud</h3>
				<h5>Start with a free acount. No credit card needed.</h5>
				<div class="controls first">
					<vllabel validators="UserID,UserIDExist" errclass="error" errtext="<span class='label label-important'>User name required</span>" for="UserID" cerrclass="controlerror">
						User name
					</vllabel>
					<vlvalidator name="UserID" type="required" control="UserID">
						<vlvalidator name="UserIDExist" type="custom" control="UserID" errmsg="<span class='label label-important'>User name already exist</span>" function="UserIDCheck">
							<input name="UserID" type="text" id="UserID">
							<span class="help-inline"></span>
				</div>

				<div class="controls">

					<vllabel errclass="error" validators="Email1,Email2" for="Email" cerrclass="controlerror" errtext="<span class='label label-important'>Hmm, that does not look like an email</span>">
						Email
					</vllabel>

					<input name="Email" type="TEXT" class="control" id="Email" size="15">
					<vlvalidator type="required" format="email" name="Email1" control="Email">
						<vlvalidator type="format" format="email" name="Email2" control="Email">

				</div>

				<div class="controls">
					<vllabel errclass="error" validators="Password" for="Password" cerrclass="controlerror" errtext="<span class='label label-important'>You must type a password</span>">
						Password
					</vllabel>
					<input name="Password" type="password" class="control" id="Password" size="15">
					<vlvalidator type="required" name="Password" control="Password">

				</div>

				<div class="controls">
					<vllabel validators="Password,PassCmp" errclass="error" for="Password2" cerrclass="controlerror" errtext="<span class='label label-important'>Both Password fields must be equal</span>">
						Confirm Password
					</vllabel>
					<input name="Password2" type="PASSWORD" class="control" id="Password2" size="15">
					<vlvalidator name="PassCmp" type="compare" control="Password" comparecontrol="Password2"
					operator="e" validtype="string">
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
				<div class="control-group">
					<div class="controls">
						<input type="submit" class="btn btn-info" value="Sign up">
						<input type="reset" class="btn" value="Reset">
					</div>
				</div>
				<div class="controls">
					<label class="checkbox">
						<input name="Agreement" type="checkbox" id="Agreement" value="1">
						<vllabel errclass="error" validators="Agreement" for="Agreement" errtext="<span class='label label-important'>Agreement must be checked</span>">
							I agree with the terms of service
						</vllabel> </label>

					<vlvalidator type="required" name="Agreement" control="Agreement">

				</div>

			</div>
			<div style="height: 2em; float: right">
				<vlsummary class="error" headertext="" displaymode="bulletlist">
			</div>
		</form>
	</div>
</div>
</body>
</html>
<?php VDEnd(); ?>