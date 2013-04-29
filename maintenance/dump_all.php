<?php
include("../conf/main.php");
include("../libs/functions.php");
include("../model/users.php");
//echo "test";
$user = new User(null);
$all = $user->getAll();
//print_r($all);
$i = 0;
foreach ($all['data'] as $value) {
    $db= $value['screenname'];
    if ($db != "template1" AND $db != "template0" AND $db != "postgres" AND $db != "postgis_template" AND $db != "mhoegh" AND $db != "mygeocloud" /*AND $i<2*/) {
        $cmd = "pg_dump -h localhost -p 5432 -U postgres -Fc -b -f '/home/mh/backup/{$db}.backup' {$db}\n";
        exec($cmd);
        $cmd = "rsync -e 'ssh -i us1.pem' -avz /home/mh/backup/{$db}.backup ubuntu@us1.mapcentia.com:/home/mh/upload\n";
        echo $cmd;
        //exec($cmd);
        //unlink("/home/mh/backup/{$db}.backup");
        $i++;
    }
}