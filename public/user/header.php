<?php
include_once("../../../app/conf/Autoload.php");
new \app\conf\Autoload();
new \app\conf\Path();

use \app\conf\App;

session_name(\app\conf\App::$param['sessionName']);
session_set_cookie_params(0, '/',".".\app\conf\App::$param['domain']);
session_start();

\app\conf\Connection::$param["postgisdb"] = 'mapcentia';
$sTable = 'users';