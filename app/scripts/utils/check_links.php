#!/usr/bin/php
<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2022 MapCentia ApS
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
    "fields:",
    "result:",
    "help",
);
$options = getopt("", $longopts);
$db = $options["db"] ?? null;
$relation = $options["relation"] ?? null;
$fields = $options["fields"] ?? null;
$result = $options["result"] ?? null;

if ($db == null || $relation == null || $fields == null || $result == null) {
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

$sql = "DROP TABLE IF EXISTS {$result}";
$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e->getMessage());
}

$sql = "CREATE TABLE {$result} (LIKE {$relation} INCLUDING ALL)";
$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e->getMessage());
    exit(1);
}

$sql = "SELECT * FROM {$relation}";
$res = $model->prepare($sql);
try {
    $res->execute();
} catch (PDOException $e) {
    print_r($e->getMessage());
    exit(1);
}
$useragent = 'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:47.0) Gecko/20100101 Firefox/47.0';

$fieldsArr = explode(",", $fields);
while ($row = $model->fetchRow($res)) {
    foreach ($fieldsArr as $field) {
        $url = $row[trim($field)];
        if (empty($url) || ctype_space($url)) {
            continue;
        }
        $orgUrl = $url;
        $url = fixUrlEncoding($url);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HEADER, true);    // we want headers
        curl_setopt($ch, CURLOPT_NOBODY, true);    // we don't need body
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CERTINFO, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, $useragent);
        curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpcode == 301 || $httpcode == 302) {
            $httpcode = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
        }

        if ($httpcode != 200) {
            print "ORG   " . $field . " " . $orgUrl . "\n";
            print "CLEAN " . $field . " " . $url . " CODE " . $httpcode . "\n\n";
            $sql = "INSERT INTO {$result} SELECT * FROM {$relation} WHERE objekt_id=:id";
            $res2 = $model->prepare($sql);
            try {
                $res2->execute(["id" => $row["objekt_id"]]);
            } catch (PDOException $e) {
                print_r($e->getMessage());
                exit(1);
            }
        }
        curl_close($ch);
    }
}

function fixUrlEncoding(string $url): string
{
    $url = trim($url);
    $scheme = parse_url($url, PHP_URL_SCHEME);
    $host   = parse_url($url, PHP_URL_HOST);
    $path   = parse_url($url, PHP_URL_PATH);
    $query  = parse_url($url, PHP_URL_QUERY);
    if (empty($scheme)) {
        return $url;
    }
    $path = str_replace(" ", "%20", $path);
    $path = str_replace("ø", "%C3%B8", $path);
    $path = str_replace("æ", "%C3%A6", $path);
    $path = str_replace("å", "%C3%A5", $path);

    $path = str_replace("Ø", "%C3%98", $path);
    $path = str_replace("Æ", "%C3%86", $path);
    $path = str_replace("Å", "%C3%85", $path);

    $query = str_replace(" ", "%20", $query);
    $query = str_replace("ø", "%C3%B8", $query);
    $query = str_replace("æ", "%C3%A6", $query);
    $query = str_replace("å", "%C3%A5", $query);

    $query = str_replace("Ø", "%C3%98", $query);
    $query = str_replace("Æ", "%C3%86", $query);
    $query = str_replace("Å", "%C3%85", $query);
    $ini   = $scheme . '://' . $host . $path . (!empty($query) ? "?" . str_replace(" ", "+", $query) : "") ;
    return $ini;
}


