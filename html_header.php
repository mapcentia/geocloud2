<?php
include 'conf/main.php';
session_name($sessionName);
session_set_cookie_params(0, '/', "." . $domain);
session_start();
include 'libs/functions.php';
include 'inc/user_name_from_uri.php';
include 'model/users.php';
include 'model/databases.php';
include 'model/classes.php';
include 'model/wmslayers.php';
include 'model/settings_viewer.php'; // we need to get pw for http authentication


if ($parts[1] == "store" || $parts[1] == "editor") {
    $db = new databases();
    if (!$parts[2]) {
        die("<script>window.location='/?db=false'</script>");
    }
    if ($db->doesDbExist(postgis::toAscii($parts[2], NULL, "_"))) {
        $postgisdb = $parts[2];
    } else {
        die("<script>window.location='/?db=false'</script>");
    }
    include("inc/oauthcheck.php");
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>MapCentia GeoCloud</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <meta name="description"
          content="Analyze and visualize your data. Use a powerful API for adding maps to your own apps."/>
    <meta name="description"
          content="The core component of MyGeoCloud is the rock solid PostGIS database with endless possibilities."/>
    <meta name="description" content="With a powerful adminstration tool you can manage your data online."/>
    <meta name="keywords"
          content="map, visualize, geo, cloud, analyze, gis, geographical data, maps, web mapping, shape file, GPX, MapInfo, WMS, OGC"/>
    <meta name="author" content="Martin Hoegh"/>
