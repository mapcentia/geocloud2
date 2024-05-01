<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

use app\migration\Sql;
use app\models\Database;

include("../../app/conf/App.php");
include("../../app/conf/Connection.php");
include("../../app/inc/Model.php");
include("../../app/models/Database.php");
include("../../app/migration/Sql.php");
include("sql.php");

Database::setDb($_GET['db']);
$conn = new \app\inc\Model();
try {
    $conn->connect();
} catch (Exception $e) {
    echo $e->getMessage() . "\n";
    die();
}

$conn->begin();

//Try to create schema
try {
    $result = $conn->execQuery($sql, "PDO", "transaction");
    echo "Schema created<br/>";
} catch (Exception $e) {
    echo "Something went wrong; {$e->getMessage()}";
    $conn->rollback();
    exit();
}

$conn->commit();

$sqls =  Sql::get();
foreach ($sqls as $sql) {
    try {
        $conn->execQuery($sql, "PDO", "transaction");
        echo "+";
    } catch (PDOException $e) {
        echo "+";
        //echo $e->getMessage() . "\n\n";
    }
}
