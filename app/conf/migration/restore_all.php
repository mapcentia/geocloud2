#!/usr/bin/php
<?php
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
            $cmd = "createdb {$db} -U postgres  -T template0 -l en_US.UTF-8";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION postgis;\" -U gc2";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION pgcrypto;\" -U gc2";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION hstore;\" -U gc2";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "/usr/share/postgresql/9.5/contrib/postgis-2.2/postgis_restore.pl {$targetDir}/{$entry} | psql {$db} -U gc2";
            echo $cmd."\n";
            exec($cmd);
            echo "**************\n";
        }
    }
    closedir($handle);
}
