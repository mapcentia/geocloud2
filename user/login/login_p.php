<?php
include '../header.php';
include '../html_header.php';
?>
<div class="container">
	<div class="row dashboard">
		<div class="span3">
		<?php
		// Check if user is logged in - and redirect if this is not the case
		if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
			die("<script>window.location='http://{$domain}/user/login'</script>");
		}
		($_SESSION['zone']) ? $prefix = $_SESSION['zone'] . "." : $prefix = "";
		$checkDb = json_decode(file_get_contents("http://{$prefix}{$domain}/controller/databases/postgres/doesdbexist/{$_SESSION['screen_name']}"));
		if ($checkDb -> success) {
			echo "<a href='http://{$prefix}{$domain}/store/{$_SESSION['screen_name']}' id='btn-admin' class='btn btn-large btn-info' title='' data-placement='bottom' data-content='Click here to start the geocloud administration.'>Start admin</a>";
		} else {
			echo "<a href='http://{$prefix}{$domain}/user/createstore' id='btn-admin' class='btn btn-large btn-info' title='' data-placement='bottom' data-content='Click here to create your geocloud. It may take some secs, so stay on this page.'>Create new database</a>";
		}
		?>
		</div>
			<div id="schema-list" class="span3">
				<div id="schema-list">
					<h2>Your maps <span><i>(Schemas)</i></span></h2>
					<table class="table" id="schema-table"></table>
				</div>
			</div>
		</div>
</div>
<script>$('#btn-admin').popover('show')</script>
<script type="text/html" id="template-schema-list">
	<tr><td><%= this.schema %></td></tr>	
</script>
<script>
	var metaDataKeys=[];
	var metaDataKeysTitle=[];
	var db = "<?php echo $_SESSION['screen_name'];?>";
	var hostName = "http://<?php echo "{$prefix}{$domain}";?>";
    $(window).load(function() {
        $.ajax({
            url : hostName + '/controller/geometry_columns/' + db + '/getall/',
            async : true,
            dataType : 'jsonp',
            jsonp : 'jsonp_callback',
            success : function(response) {
                var metaData = response;
                for (var i = 0; i < metaData.data.length; i++) {
                    metaDataKeys[metaData.data[i].f_table_name] = metaData.data[i];
                    (metaData.data[i].f_table_title) ? metaDataKeysTitle[metaData.data[i].f_table_title] = metaData.data[i] : null;
                }
               //console.log(metaData);
            }
        });
        $.ajax({
            url : hostName + '/controller/geometry_columns/' + db + '/getschemas',
            async : true,
            dataType : 'jsonp',
            jsonp : 'jsonp_callback',
            success : function(response) {
                //console.log(response);
                $('#schema-table').append($('#template-schema-list').jqote(response.data));
            }
        });
    });
</script>
<script id="IntercomSettingsScriptTag">
  window.intercomSettings = {
    // TODO: The current logged in user's email address.
    email: "<?php echo $_SESSION['email']; ?>",
    // TODO: The current logged in user's sign-up date as a Unix timestamp.
    created_at: <?php echo $_SESSION['created']; ?>,
    app_id: "154aef785c933674611dca1f823960ad5f523b92"
  };
</script>
<script>(function(){var w=window;var ic=w.Intercom;if(typeof ic==="function"){ic('reattach_activator');ic('update',intercomSettings);}else{var d=document;var i=function(){i.c(arguments)};i.q=[];i.c=function(args){i.q.push(args)};w.Intercom=i;function l(){var s=d.createElement('script');s.type='text/javascript';s.async=true;s.src='https://api.intercom.io/api/js/library.js';var x=d.getElementsByTagName('script')[0];x.parentNode.insertBefore(s,x);}if(w.attachEvent){w.attachEvent('onload',l);}else{w.addEventListener('load',l,false);}};})()</script>
</body>
</html>