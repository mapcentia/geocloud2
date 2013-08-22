<?php
set_time_limit(0);
include '../../../conf/main.php';
include("inc/controller.php");
include 'model/settings_viewer.php';
$parts = Controller::getUrlParts();
switch ($parts[4]) {
    case "search":
        include 'search_c.php';
        $_GET['size'] = ($_GET['size']) ? $_GET['size'] : 10;
        $_GET['pretty'] = (($_GET['pretty']) || $_GET['pretty']=="true") ? $_GET['pretty'] : "false";
        echo Search_c::search($_GET['q'],$_GET['jsonp_callback'],$_GET['size'],$_GET['pretty']);
        break;
    case "bulk":
        include 'bulk_c.php';
        $api = new Bulk_c();
        $api->bulk();
        break;
    case "map":
        include 'map_c.php';
        $api = new Map_c();
        // Set up out PUT variables
        parse_str(file_get_contents('php://input'), $_PUT);
        echo $api->map($_PUT['map'],$_PUT['key']);
        break;
    case "delete":
        include 'delete_c.php';
        $api = new Delete_c();
        // Set up out PUT variables
        parse_str(file_get_contents('php://input'), $_DELETE);
        echo $api->delete($_DELETE['key']);
        break;
        break;
    case "count":
        include 'count_c.php';
        echo Count_c::count();
        break;
    default:
        $response['success'] = false;
        $response['message'] = "'{$parts[4]}' is not a valid operation.";
        echo json_encode($response);
        break;
}
echo  "\n";
