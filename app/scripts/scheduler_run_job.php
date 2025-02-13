<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2025 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

include_once(__DIR__ . "/../conf/App.php");

use app\conf\App;
use app\models\Database;
use app\models\Job;

new App();
Database::setDb("gc2scheduler");

$longOpts = array(
    "id:",
    "db:",
);
$options = getopt("", $longOpts);

$id = $options["id"];
$db = $options["db"];

(new Job())->runJob($id, $db, 'Started by Scheduler');
