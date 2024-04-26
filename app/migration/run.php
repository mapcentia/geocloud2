<?php

use app\conf\App;
use app\conf\Connection;
use app\migration\Sql;
use app\models\Database;

header("Content-type: text/plain");
include_once(__DIR__ ."/../conf/App.php");
include_once(__DIR__ ."/../conf/Connection.php");
include_once(__DIR__ ."/../inc/Model.php");
include_once(__DIR__ ."/../models/Database.php");
include_once(__DIR__ ."/Sql.php");
new App();
Connection::$param["postgisschema"] = "public";
$database = new Database();
$arr = $database->listAllDbs();
foreach ($arr['data'] as $db) {
    if ($db != "rdsadmin" && $db != "template1" AND $db != "template0" AND $db != "postgres" AND $db != "postgis_template") {
        if (1 === 1) {
            Database::setDb($db);
            $conn = new \app\inc\Model();
            $sqls = match ($db) {
                "mapcentia" => Sql::mapcentia(),
                "gc2scheduler" => Sql::gc2scheduler(),
                default => Sql::get(),
            };
            foreach ($sqls as $sql) {
                try {
                    $result = $conn->execQuery($sql, "PDO", "transaction");
                    echo "+";
                } catch (Exception) {
                    echo "-";
                }
            }
            echo " {$db}\n";
            $conn->db = NULL;
            $conn = NULL;
        }
    }
}
