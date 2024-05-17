<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * @description Script for dropping old tmp tables created by scheduler. Should be run in a cronjob
 *
 */

include_once(__DIR__ . "/../conf/App.php");

use app\conf\App;
use app\inc\Model;
use app\models\Database;

new App();

const WORKING_DIR = '_gc2scheduler';
const LIMIT = 3600 * 24;

$model = new Model();
$time = time();

$database = new Database();
$arr = $database->listAllDbs();
foreach ($arr['data'] as $db) {
    if ($db != "rdsadmin" && $db != "template1" and $db != "template0" and $db != "postgres" and $db != "postgis_template") {
        Database::setDb($db);
        $drops = [];
        $model = new Model();
        $sql = "SELECT tablename as name FROM pg_tables WHERE schemaname = '" . WORKING_DIR . "'";
        $res = $model->prepare($sql);
        $res->execute();
        while ($row = $model->fetchRow($res)) {
            $tableTime = (int)explode('_' , $row["name"])[1];
            $diff = $time - $tableTime;
            if ($diff > LIMIT) {
                $drops[] = $row['name'];
            }
        }
        foreach ($drops as $drop) {
            $sql = "DROP TABLE " . WORKING_DIR .".".$drop;
            print $db . ": " . $sql . "\n";
            $res = $model->prepare($sql);
            $res->execute();
        }
    }
}