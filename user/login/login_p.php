<?php
include '../header.php';
include '../html_header.php';
?>
<div class="container">
	<div class="row dashboard">
		<?php
		// Check if user is logged in - and redirect if this is not the case
		if (!$_SESSION['auth'] || !$_SESSION['screen_name']) {
			die("<script>window.location='http://{$domain}/user/login'</script>");
		}
		($_SESSION['zone']) ? $prefix = $_SESSION['zone'] . "." : $prefix = "";
		$checkDb = json_decode(file_get_contents("http://{$prefix}{$domain}/controller/databases/postgres/doesdbexist/{$_SESSION['screen_name']}"));
		if ($checkDb -> success) {
			echo "<a href='http://{$prefix}{$domain}/store/{$_SESSION['screen_name']}' id='btn-admin' class='btn btn-large btn-info' title='' data-content='Click here to start the geocloud administration.'>Start admin</a>";
		} else {
			echo "<a href='http://{$prefix}{$domain}/user/createstore' id='btn-admin' class='btn btn-large btn-info' title='' data-content='Here here to create your geocloud. It may take some secs, so stay on this page.'>Create new database</a>";
		}
	?>
	</div>
</div>
<script>$('#btn-admin').popover('show')</script>
</body>
</html>