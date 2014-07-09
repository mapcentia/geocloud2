#!/usr/bin/php
<?php
header("Content-type: text/plain");
include_once("../App.php");
new \app\conf\App();
$database = new \app\models\Database();
$arr = $database->listAllDbs();
$targetDir = \app\conf\App::$param["path"]."app/tmp/backup";
if (!file_exists($targetDir)) {
    @mkdir($targetDir);
}
foreach ($arr['data'] as $db) {
    if ($db != "template1" AND $db != "template0" AND $db != "postgres" AND $db != "postgis_template" AND $db != "mapcentia") {
        echo $db."\n";
        $cmd = "pg_dump -h localhost -p 5432 -U postgres -Fc -b -f '{$targetDir}/{$db}.bak' {$db}\n";
        exec($cmd);
    }
}