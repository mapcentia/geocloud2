#!/usr/bin/php
<?php
use \app\conf\Connection;

header("Content-type: text/plain");
include_once("../App.php");
new \app\conf\App();
$database = new \app\models\Database();
$targetDir = "/backup";
putenv("PGPASSWORD= " . Connection::$param["postgispw"]);
if ($handle = opendir($targetDir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry !== "." && $entry !== "..") {
            $split = explode(".", $entry);
            $db = $split[0];
            $cmd = "createdb {$db} --host=postgis --username=gc2 --template=template0 --locale=da_DK.UTF-8 --encoding=UTF8";
            echo $cmd . "\n";
            /*exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION postgis;\" -U gc2";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION pgcrypto;\" -U gc2";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION hstore;\" -U gc2";
            echo $cmd."\n";
            exec($cmd);*/
            $cmd = "/usr/share/postgresql/9.5/contrib/postgis-2.2/postgis_restore.pl {$targetDir}/{$entry} | psql {$db} --host=postgis --username=gc2 ";
            echo $cmd . "\n";
            exec($cmd);
            echo "**************\n";
        }
    }
    closedir($handle);
}
