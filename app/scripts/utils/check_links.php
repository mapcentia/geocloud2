#!/usr/bin/php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 * This script can check link in a relation. It will follow redirects.
 *
 */

set_time_limit(0);

use app\conf\App;
use app\inc\Model;
use app\models\Database;

header("Content-type: text/plain");
include_once(__DIR__ . "/../../conf/App.php");

new App();

$longopts = array(
    "db:",
    "relation:",
    "field:",
    "help",
);
$options = getopt("", $longopts);

$db = $options["db"] ?? null;
$relation = $options["relation"] ?? null;
$field = $options["field"] ?? null;

if ($db == null || $relation == null || $field == null) {
    help();
}

if (isset($options["help"])) {
    help();
}

function help(): void
{
    print "HELP\n";
    exit();
}

Database::setDb($db);
$model = new Model();

$sql = "SELECT * FROM {$relation}";

$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e->getMessage());
    exit(1);
}

while ($row = $model->fetchRow($res)) {
    $url = trim($row[$field]);

    if (empty($url)) {
        continue;
    }

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
    curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CERTINFO, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($httpcode == 301 || $httpcode == 302) {
        $httpcode = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    }

    if ($httpcode != 200) {
        print $url . " " . $httpcode . "\n";
    }
    curl_close($ch);
}



