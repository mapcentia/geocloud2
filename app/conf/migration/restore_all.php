#!/usr/bin/php
<?php
header("Content-type: text/plain");
include_once("../App.php");
new \app\conf\App();
$database = new \app\models\Database();
$targetDir = \app\conf\App::$param["path"] . "app/tmp/backup";
if ($handle = opendir($targetDir)) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry !== "." && $entry !== "..") {
            $split = explode(".", $entry);
            $db = $split[0];
            $cmd = "createdb {$db} -U postgres  -T template0 -l en_US.UTF-8";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION postgis;\" -U postgres";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "psql -d {$db} -c \"CREATE EXTENSION pgcrypto;\" -U postgres";
            echo $cmd."\n";
            exec($cmd);
            $cmd = "/usr/share/postgresql/9.3/contrib/postgis-2.1/postgis_restore.pl {$entry} | psql {$db} -U postgres";
            echo $cmd."\n";
            exec($cmd);
            echo "**************\n";
        }
    }
    closedir($handle);
}
