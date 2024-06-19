<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2024 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include_once(__DIR__ . "/../conf/App.php");
include_once(__DIR__ . "/../vendor/autoload.php");

use app\conf\App;
use app\inc\Model;
use app\models\Database;
use Postmark\PostmarkClient;

new App();

echo date(DATE_RFC822) . "\n\n";

Database::setDb("gc2scheduler");
$model = new Model();

$failedJobs = [];

$sql = "SELECT * FROM jobs WHERE lastcheck = FALSE AND active = TRUE AND lastrun > (now() - '24 hour' :: INTERVAL) ORDER BY id";

$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e);
    exit(1);
}
$header[] = "<table class=\"table table-striped\">\n";
$header[] = "<tr><th>Id</th><th>Database</th><th>Schema</th><th>Table</th><th>Log</th></tr>\n";
$rows = [];
while ($row = $model->fetchRow($res)) {
    $rows[$row["db"]][] = $row;
}
$databases = [];
foreach ($rows as $db => $jobs) {
    $databases[] = $db;
}
$footer = "</table>\n";

foreach ($databases as $db) {
    if (App::$param["notification"]["to"][$db]) {
        $tr = [];
        foreach ($rows[$db] as $row) {
            $tr[] = "<tr><td>{$row["id"]}</td><td>{$row["db"]}</td><td>{$row["schema"]}</td><td>{$row["name"]}</td><td><a target=\"_blank\" href=\"" . App::$param["notification"]["logUrl"] . "/logs/" . $row["id"] . "_scheduler.log\">{$row["id"]}_scheduler.log</a></td></tr>\n";
        }
        $html = "<p>Failed jobs in the last 24 hours for database $db</p>\n";
        try {
            $client = new PostmarkClient(App::$param["notification"]["key"]);
            $message = [
                'To' => implode(",", App::$param["notification"]["to"][$db]),
                'From' => App::$param["notification"]["from"],
                'TrackOpens' => false,
                'Subject' => "Errors in scheduler jobs for database $db",
                'HtmlBody' => "<html lang='en'><head><title>Errors in scheduler jobs</title><link rel=\"stylesheet\" href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css\"/></head><body style=\"padding: 20px\">" . $html . implode('', $header) . implode('', $tr) . $footer . "</body></html>",
            ];
            try {
                $sendResult = $client->sendEmailBatch([$message]);
                print_r($sendResult);
            } catch (Exception $generalException) {
                exit(1);
            }
        } catch (Exception $generalException) {
            exit(1);
        }
    }
}
exit(0);