<?php
include '../../../conf/main.php';
include("inc/controller.php");

$parts = Controller::getUrlParts();
switch ($parts[4]) {
    case "search":
        include 'search_c.php';
        echo Search_c::search($_GET['q'],$_GET['jsonp_callback']);
        break;
    case "bulk":
        include 'bulk_c.php';
        new SqlToEs_c();
        break;

}