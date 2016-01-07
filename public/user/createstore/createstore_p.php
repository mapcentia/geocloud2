<?php
use \app\inc\Model;
use \app\conf\App;

include("../header.php");
$postgisdb = $databaseTemplate;
\app\models\Database::setDb("postgres");
if (!$_SESSION['screen_name']) {
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
    die();
} else {
    $name = Model::toAscii($_SESSION['screen_name'], NULL, "_");
    $db = new \app\models\Database;
    $dbObj = $db->createdb($name, App::$param['databaseTemplate'], "UTF8");
    $res = file_get_contents("http://127.0.0.1:1337/add?db=" . $name);

    // databaseTemplate is set in conf/main.php
    if ($dbObj) {
        header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
    } else {
        echo "<h2>Sorry, something went wrong. Try again</h2>";
        echo "<div><a href='" . \app\conf\App::$param['userHostName'] . "/user/signup' class='btn btn-danger'>Go back</a></div>";
    }
}
