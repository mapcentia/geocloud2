<?php
session_name($sessionName);
session_set_cookie_params(0, '/', "." . $domain);
session_start();
include 'inc/user_name_from_uri.php';

if ($parts[1] == "store" || $parts[1] == "editor") {
    $db = new app\model\databases();
    if (!$parts[2]) {
        die("<script>window.location='/?db=false'</script>");
    }
    if ($db->doesDbExist(app\inc\postgis::toAscii($parts[2], NULL, "_"))) {
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
