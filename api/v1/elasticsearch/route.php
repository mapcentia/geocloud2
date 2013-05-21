<?php
include '../../../conf/main.php';
include("inc/controller.php");

$parts = Controller::getUrlParts();
switch ($parts[4]) {
    case "search":
        include 'search_c.php';
        echo Search_c::search($_GET['q'],$_GET['jsonp_callback'],$_GET['call_counter']);
        break;
    case "bulk":
        include 'bulk_c.php';
        new SqlToEs_c();
        break;
    case "map":
        include 'map_c.php';
        echo Map_c::map($_PUT['map']);
        break;
    case "delete":
        include 'delete_c.php';
        echo Delete_c::delete();
        break;
    case "count":
        include 'count_c.php';
        echo Count_c::count();
        break;

}