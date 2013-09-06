<?php
include '../../../app/conf/main.php';
include ('vdaemon/vdaemon.php');
session_name($sessionName);
session_set_cookie_params(0, '/',".".$domain);
session_start();

$postgisdb = 'mapcentia';
$sTable = 'users';