#!/usr/bin/php
<?php
use \app\conf\App;
use \app\models\Database;
use \app\models\Table;
use \app\inc\Model;

header("Content-type: text/plain");
include_once("../App.php");
new App();
Database::setDb("mydb");
$conn = new Model();
$sql = "SELECT * FROM settings.geometry_columns_view";
$result = $conn->execQuery($sql);
echo $conn->PDOerror[0];
$count = 0;

while ($row = $conn->fetchRow($result)) {
    $table = new Table($row["f_table_schema"] . "." . $row["f_table_name"]);
    print_r($table->purgeFieldConf($row["_key_"]));
}