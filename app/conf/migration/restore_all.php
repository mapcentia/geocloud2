#!/usr/bin/php
<?php
use \app\conf\Connection;

header("Content-type: text/plain");
include_once("../App.php");
new \app\conf\App();
$database = new \app\models\Database();
$targetDir = "/backup";
if ($handle = opendir($targetDir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry !== "." && $entry !== "..") {
            $split = explode(".", $entry);
            $db = $split[0];
            $cmd = "createdb {$db} --host=postgis --username=gc2 --template=template0 --locale=da_DK.UTF-8 --encoding=UTF8";
            echo $cmd . "\n";
            exec($cmd);
            $cmd = "psql -d {$db} --host=postgis --username=gc2 -c \"CREATE EXTENSION postgis;\"";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} --host=postgis --username=gc2 -c \"CREATE EXTENSION pgcrypto;\"";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} --host=postgis --username=gc2 -c \"CREATE EXTENSION hstore;\"";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "./postgis_restore.pl {$targetDir}/{$entry} | psql {$db} --host=postgis --username=gc2 ";
            echo $cmd . "\n";
            exec($cmd);
            echo "**************\n";
        }
    }
    closedir($handle);
}