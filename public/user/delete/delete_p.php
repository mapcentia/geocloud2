<?php
use \app\inc\Model;

include '../header.php';
$postgisObject = new Model();
\app\models\Database::setDb("postgres");
// Check if user is logged in - and redirect if this is not the case
if (!$_SESSION['auth'] || !$_SESSION['screen_name'] || $_SESSION['subuser'] != false) {
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
    exit();
}

// Prevent sub-user in deleting users
if ($_SESSION['subuser']){
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
    exit();
}
$sQuery = "SELECT * FROM {$sTable} WHERE screenname = :sUserID";
$res = $postgisObject->prepare($sQuery);
$res->execute(array(":sUserID" => $_POST['user']));
$row = $postgisObject->fetchRow($res);

if ($row["parentdb"] == $_SESSION["screen_name"]){
    $sQuery = "DELETE FROM {$sTable} WHERE screenname = :sUserID AND parentdb = :sParentDb";
    $res = $postgisObject->prepare($sQuery);
    $res->execute(array(":sUserID" => $_POST['user'], ":sParentDb" => $_SESSION["screen_name"]));
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
}
else {
    header("location: " . \app\conf\App::$param['userHostName'] . "/user/login/p");
}
