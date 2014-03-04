<?php
include_once("../../../app/conf/App.php");
use \app\conf\App;
use \app\inc\Session;
new App();
Session::start();
\app\models\Database::setDb("mapcentia");
$sTable = 'users';