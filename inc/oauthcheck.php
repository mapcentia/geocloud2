<?php
$postgisdb = $parts[2];
if (!$_SESSION['auth'] || ($_SESSION['screen_name'] != $parts[2])) {
	$_SESSION['auth']=null;
	$_SESSION['screen_name']=null;
	die("<script>window.location='/user/login'</script>");
}