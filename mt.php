<?php
ini_set("display_errors", "On");
header("Content-type: text/plain");

error_reporting(3);
// Connection start
$servername = "postgis";
$username = "gc2";
$password = "1234";
$db = "mydb";

//$json = "[[\"219017684\",\"55.478630\",\"8.419262\",\"0\",\"59\",\"313\",\"0\",\"2016-04-28T09:08:22\"],[\"219615000\",\"54.047330\",\"-3.481500\",\"0\",\"132\",\"35\",\"1\",\"2016-04-28T09:09:13\"],[\"220520000\",\"55.478310\",\"8.417665\",\"0\",\"62\",\"355\",\"5\",\"2016-04-28T09:11:20\"]]";

$json = file_get_contents("http://services.marinetraffic.com/api/exportvessels/ba6c6651415c626a0404bb3623f51c248fe4bfd3/timespan:10/v:3/protocol:json");

$arr = json_decode($json);

try {
    $conn = new PDO("pgsql:host=$servername;dbname=$db", $username, $password);
    // set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
    exit();
}

foreach($arr as $p) {
    print_r($p);
    $insert= "INSERT INTO dbb.positions (mmsi,lat,lon,speed,heading,course,\"timestamp\",the_geom) VALUES('{$p[0]}',{$p[1]},{$p[2]},{$p[3]},{$p[4]},{$p[5]},'{$p[7]}',ST_geomfromtext('POINT({$p[2]} {$p[1]})',4326))";
    $inserts[] = $insert;
    $res = $conn->prepare($insert);
    try {
        $res->execute();
    } catch (\PDOException $e) {
        echo $e->getMessage();
        //exit();
    }

}

print_r($inserts);


