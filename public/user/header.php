<?php
include_once("../../../app/conf/App.php");
use \app\conf\App;
use \app\inc\Session;
new App();
Session::start();
\app\models\Database::setDb("mapcentia");
$sTable = 'users';
$prefix = ($_SESSION['zone']) ? App::$param['domainPrefix'] . $_SESSION['zone'] . "." : "";
if (App::$param['domain']) {
    $host = "//" . $prefix . App::$param['domain'] . ":" . $_SERVER['SERVER_PORT'];
} else {
    $host = App::$param['host'];
}

if (App::$param['cdnSubDomain']) {
    $bits = explode("://", $host);
    $cdnHost = $bits[0] . "://" . App::$param['cdnSubDomain'] . "." . $bits[1];
} else {
    $cdnHost = $host;
}