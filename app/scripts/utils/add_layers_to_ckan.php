#!/usr/bin/php
<?php
ini_set("display_errors", "On");

use \app\conf\App;
use \app\conf\Connection;
use \app\inc\Model;
use \app\models\Database;
use \app\models\Layer;

header("Content-type: text/plain");
include_once(__DIR__ . "/../../conf/App.php");

$db = $argv[1];
$apiKey = $argv[2];
$host = $argv[3];

new \app\conf\App();
$database = new Database();
Database::setDb($db);
$model = new Layer();
$layers = $model->getAll($schema = false, $layer = false, true, $includeExtent = false, $parse = false, $es = false);
foreach ($layers["data"] as $layer) {
    if ($layer["f_table_schema"] != "sqlapi") {
        $url = "http://gc2core/api/v1/ckan/" . $db . "?id=" . $layer["_key_"] . "&key=" . $apiKey . "&host=" . $host;
        echo $url . "\n";
        \app\inc\Util::wget($url);
    }
}