<!DOCTYPE html>
<html lang="en">
<head>
    <title>Scheduler report</title>
<style>
    .datagrid table {
        border-collapse: collapse;
        text-align: left;
        width: 100%;
    }

    .datagrid {
        font: normal 12px/150% Arial, Helvetica, sans-serif;
        background: #fff;
        overflow: hidden;
        border: 1px solid #8C8C8C;
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
    }

    .datagrid table td, .datagrid table th {
        padding: 3px 10px;
    }

    .datagrid table thead th {
        background: -webkit-gradient(linear, left top, left bottom, color-stop(0.05, #8C8C8C), color-stop(1, #7D7D7D));
        background: -moz-linear-gradient(center top, #8C8C8C 5%, #7D7D7D 100%);
        filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#8C8C8C', endColorstr='#7D7D7D');
        background-color: #8C8C8C;
        color: #FFFFFF;
        font-size: 15px;
        font-weight: bold;
        border-left: 1px solid #A3A3A3;
    }

    .datagrid table thead th:first-child {
        border: none;
    }

    .datagrid table tbody td {
        color: #7D7D7D;
        border-left: 1px solid #DBDBDB;
        font-size: 12px;
        font-weight: normal;
    }

    .datagrid table tbody .alt td {
        background: #EBEBEB;
        color: #7D7D7D;
    }

    .datagrid table tbody td:first-child {
        border-left: none;
    }

    .datagrid table tbody tr:last-child td {
        border-bottom: none;
    }

    .datagrid table tfoot td div {
        border-top: 1px solid #8C8C8C;
        background: #EBEBEB;
    }

    .datagrid table tfoot td {
        padding: 0;
        font-size: 12px
    }

    .datagrid table tfoot td div {
        padding: 2px;
    }

    .datagrid table tfoot td ul {
        margin: 0;
        padding: 0;
        list-style: none;
        text-align: right;
    }

    .datagrid table tfoot li {
        display: inline;
    }

    .datagrid table tfoot li a {
        text-decoration: none;
        display: inline-block;
        padding: 2px 8px;
        margin: 1px;
        color: #F5F5F5;
        border: 1px solid #8C8C8C;
        -webkit-border-radius: 3px;
        -moz-border-radius: 3px;
        border-radius: 3px;
        background: -webkit-gradient(linear, left top, left bottom, color-stop(0.05, #8C8C8C), color-stop(1, #7D7D7D));
        background: -moz-linear-gradient(center top, #8C8C8C 5%, #7D7D7D 100%);
        filter: progid:DXImageTransform.Microsoft.gradient(startColorstr='#8C8C8C', endColorstr='#7D7D7D');
        background-color: #8C8C8C;
    }

    .datagrid table tfoot ul.active, .datagrid table tfoot ul a:hover {
        text-decoration: none;
        border-color: #7D7D7D;
        color: #F5F5F5;
        background: none;
        background-color: #8C8C8C;
    }

    div.dhtmlx_window_active, div.dhx_modal_cover_dv {
        position: fixed !important;
    }
</style>
</head>
<body>
<?php

ini_set("display_errors", "Off");
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 0);
error_reporting(3);

include_once(__DIR__ . "/../../app/conf/App.php");

new \app\conf\App();

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Util;

setlocale(LC_ALL, 'da_DK');
date_default_timezone_set("CET");

\app\models\Database::setDb("gc2scheduler");
$job = new \app\inc\Model();

$res = $job->prepare("SELECT * FROM jobs ORDER BY db, id");
try {
    $res->execute();
} catch (\PDOException $e) {
    print "Error: ";
    print_r($e->getMessage());
}

print "<div class=\"datagrid\">";
print "<table border=0>";
print "<thead><tr><th>Id</th><th>Url</th><th>Database</th><th>Schema</th><th>Table</th><th>Type</th><th>Last run</th><th>Since last success</th><th>Geom type</th><th>Total feature count</th><th>Max features in cell</th><th>Duplicates removed</th><th>Waited (seconds)</th><th>Success</th><th>Log</th></tr></thead>";

$count = 1;
while ($row = $job->fetchRow($res)) {
    //print_r($row);
    if (is_int($count/2)) {
        $alt = "class=\"alt\"";
    } else {
        $alt = "";
    }
    $count++;
    
    $report = json_decode($row["report"], true);

    $lastcheck = $row["lastcheck"] ? "<font color=\"green\">true</font>" : "<font color=\"red\">false</font>";
    $lastcheck = $row["lastcheck"] ? "<font color=\"green\">true</font>" : "<font color=\"red\">false</font>";
    $lastrun = date('l jS \of F Y h:i:s', strtotime($row["lastrun"]));

    $d1 = new DateTime($row["lasttimestamp"]);
    $d2 = new DateTime();
    $interval = $d2->diff($d1);
    $lasttimestamp = $interval->format('%d days, %H hours, %I minutes, %S seconds');

    $featureCount = is_int($report["featureCount"] / 1000) ? "<font color=\"orange\">{$report["featureCount"]}</font>" : $report["featureCount"];
    $maxCellCount = is_int($report["maxCellCount"] / 1000) ? "<font color=\"orange\">{$report["maxCellCount"]}</font>" : $report["maxCellCount"];
    $dupsCount = isset($report["dupsCount"]) && $report["dupsCount"] == 0 ? "<font color=\"orange\">{$report["dupsCount"]}</font>" : $report["dupsCount"];
    $waited = isset($report["sleep"]) ? $report["sleep"] : "0";


    print "\n<tr {$alt}><td>{$row["id"]}</td><td><input type='text' value='{$row["url"]}'></td><td>{$row["db"]}</td><td>{$row["schema"]}</td><td>{$row["name"]}</td><td>{$report["downloadType"]}</td><td>{$lastrun}</td><td>$lasttimestamp</td><td>{$row["type"]}</td><td>{$featureCount}</td><td>{$maxCellCount}</td><td>{$dupsCount}</td><td>{$waited}</td><td>{$lastcheck}</td><td><a target='_blank' href='/logs/{$row["id"]}_scheduler.log'>Link</a></td></tr>";

}

print "</table>";
print "</div>";
?>
</body>
</html>
