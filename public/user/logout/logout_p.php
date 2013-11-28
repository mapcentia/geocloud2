<?php
include '../header.php';
session_unset();
?>
<script>window.location = '<?php echo \app\inc\App::$param['userHostName'] ?>/user/login'</script>
