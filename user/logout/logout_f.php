<?php
include 'conf/main.php';
session_name($sessionName);
session_set_cookie_params(0, '/',".".$domain);
session_start();
$_SESSION['auth'] = false;
$_SESSION['screen_name'] = false;
$_SESSION['zone'] = false;
?>
<script>window.location = '/'</script>