<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\models\Database;
use app\inc\Model;
use GO\Scheduler;

new App();
Database::setDb("gc2scheduler");
$scheduler = new Scheduler([
    'tempDir' => '/var/www/geocloud2/app/tmp'
]);

$model = new Model();

$sql = "SELECT * FROM jobs ORDER BY id";

$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e);
    exit(1);
}

while ($row = $model->fetchRow($res)) {
    if (!empty($row["active"]) && ((isset(App::$param["gc2scheduler"][$row["db"]]) && App::$param["gc2scheduler"][$row["db"]] === true) || (isset(App::$param["gc2scheduler"]["*"]) && App::$param["gc2scheduler"]["*"] === true))) {
        $args = [
            "--id" => $row["id"],
            "--db" => $row["db"],
        ];
        // We run the job function through another script, so it runs async
        // If using $scheduler->call() jobs will run in sync
        $cmd = "/var/www/geocloud2/app/scripts/scheduler_run_job.php";
        try {
            $scheduler->php(
                $cmd,
                "/usr/bin/php",
                $args,
                $row["id"] . "_" . $row["name"]
            )->at("{$row["min"]} {$row["hour"]} {$row["dayofmonth"]} {$row["month"]} {$row["dayofweek"]}")->output([
                __DIR__ . "/../../public/logs/{$row["id"]}_scheduler.log"
            ])->onlyOne();
            $scheduler->run();
        } catch (Exception $e) {
            echo "Could not run job {$row['id']}. ERROR: " . $e->getMessage() . "\n";
        }
    }
}


