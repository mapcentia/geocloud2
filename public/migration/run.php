<?php
header("Content-type: text/plain");
include_once("../../app/conf/App.php");
include_once("Sql.php");
new \app\conf\App();

// Set the host names if they are not set in App.php
if (!\app\conf\App::$param['host']) {
    include_once("../../app/conf/hosts.php");
}

$database = new \app\models\Database();
$arr = $database->listAllDbs();

foreach ($arr['data'] as $db) {
    if ($db != "template1" AND $db != "template0" AND $db != "postgres" AND $db != "postgis_template" AND $db != "mapcentia") {
        if (1 === 1) {
            \app\models\Database::setDb($db);
            $conn = new \app\inc\Model();
            foreach (Sql::get() as $sql) {
                $result = $conn->execQuery($sql, "PDO", "transaction");
                if ($conn->PDOerror[0]) {
                    echo "An SQL did NOT run in {$db}:\n";
                    echo $conn->PDOerror[0]."\n";
                } else {
                    echo "SQL ran without errors in {$db}\n";
                }
                $conn->PDOerror = NULL;
            }
            echo "---------------------\n";
            $conn->db = NULL;
            $conn = NULL;
        }
    }
}
