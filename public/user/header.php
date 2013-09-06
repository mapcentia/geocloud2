<?php
include '../../../app/conf/main.php';

session_name($sessionName);
session_set_cookie_params(0, '/',".".$domain);
session_start();

\Connection::$param["postgisdb"] = 'mapcentia';
$sTable = 'users';