<?php
include '../header.php';
session_unset();
?>
<script>window.location = '<?php echo \app\conf\App::$param['userHostName'] ?>/user/login'</script>
