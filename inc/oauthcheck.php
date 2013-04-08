<?php
if (!$_SESSION['auth'] || ($_SESSION['screen_name'] != $postgisdb)) {
	//$_SESSION['auth']=null;
	//$_SESSION['screen_name']=null;
	die("<script>window.location='{$userHostName}/user/login'</script>");
}
