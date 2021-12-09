<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

ini_set('max_execution_time', "0");

use app\inc\Model;
use app\inc\Util;
use PDOException;


/**
 * Class Job
 * @package app\models
 */
class Job extends Model
{
    /**
     * @param string|null $db
     * @return array<mixed>
     */
    public function getAll(?string $db): array
    {
        $arr = array();
        if ($db) {
            $sql = "SELECT * FROM jobs WHERE db=:db ORDER BY id";
            $args = array(":db" => $db);
        } else {
            $sql = "SELECT * FROM jobs ORDER BY id";
            $args = array();
        }
        $res = $this->prepare($sql);
        try {
            $res->execute($args);
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        while ($row = $this->fetchRow($res)) {
            $arr[] = $row;
        }
        $response['success'] = true;
        $response['message'] = "Jobs fetched";
        $response['data'] = (sizeof($arr) > 0) ? $arr : null;
        return $response;
    }

    /**
     * @param object $data
     * @param string $db
     * @return array<bool|string|int>
     */
    public function newJob(object $data, string $db): array
    {
        $sql = "INSERT INTO jobs (db, name, schema, url, cron, epsg, type, min, hour, dayofmonth, month, dayofweek, encoding, extra, delete_append, download_schema, presql, postsql, active) VALUES(:db, :name, :schema, :url, :cron, :epsg, :type, :min, :hour, :dayofmonth, :month, :dayofweek, :encoding, :extra, :delete_append, :download_schema, :presql, :postsql, :active)";
        $res = $this->prepare($sql);
        try {
            $res->execute(array(":db" => $db, ":name" => Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min" => $data->min, ":hour" => $data->hour, ":dayofmonth" => $data->dayofmonth, ":month" => $data->month, ":dayofweek" => $data->dayofweek, ":encoding" => $data->encoding, ":extra" => $data->extra, ":delete_append" => $data->delete_append, ":download_schema" => $data->download_schema, ":presql" => $data->presql, ":postsql" => $data->postsql, ":active" => $data->active));
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Jobs created";
        return $response;
    }

    /**
     * @param object $data
     * @return array<bool|string|int>
     */
    public function updateJob(object $data): array
    {

        $sql = "UPDATE jobs SET name=:name, schema=:schema, url=:url, cron=:cron, epsg=:epsg, type=:type, min=:min, hour=:hour, dayofmonth=:dayofmonth, month=:month, dayofweek=:dayofweek, encoding=:encoding, extra=:extra, delete_append=:delete_append, download_schema=:download_schema, presql=:presql, postsql=:postsql, active=:active WHERE id=:id";
        $res = $this->prepare($sql);
        try {
            $res->execute(array(":name" => Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min" => $data->min, ":hour" => $data->hour, ":dayofmonth" => $data->dayofmonth, ":month" => $data->month, ":dayofweek" => $data->dayofweek, ":encoding" => $data->encoding, ":id" => $data->id, ":extra" => $data->extra, "delete_append" => $data->delete_append, "download_schema" => $data->download_schema, "presql" => $data->presql, "postsql" => $data->postsql, "active" => $data->active));
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Jobs updated";
        return $response;
    }

    /**
     * @param object $data
     * @return array<bool|string|int>
     */
    public function deleteJob(object $data): array
    {
        $sql = "DELETE FROM jobs WHERE id=:id";
        $res = $this->prepare($sql);
        try {
            $res->execute(array(":id" => $data->id));
        } catch (PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Job deleted";
        return $response;
    }

    /**
     * @param int $id
     * @param string $db
     * @param bool $flush
     * @return array<mixed>|null
     */
    public function runJob(int $id, string $db, bool $flush = false): ?array
    {
        $cmd = null;
        $jobs = $this->getAll($db);
        foreach ($jobs["data"] as $job) {
            if ($id == $job["id"]) {
                if (!$job["delete_append"]) $job["delete_append"] = "0";
                if (!$job["download_schema"]) $job["download_schema"] = "0";
                $cmd = "/usr/bin/timeout -s SIGINT 4h php " . __DIR__ . "/../scripts/get.php --db {$job["db"]} --schema {$job["schema"]} --safeName {$job["name"]} --url \"{$job["url"]}\" --srid {$job["epsg"]} --type {$job["type"]} --encoding {$job["encoding"]} --jobId {$job["id"]} --deleteAppend {$job["delete_append"]} --extra " . (base64_encode($job["extra"]) ?: "null") . " --preSql " . (base64_encode($job["presql"]) ?: "null") . " --postSql " . (base64_encode($job["postsql"]) ?: "null") . " --downloadSchema {$job["download_schema"]}";
                break;
            }
        }

        if (!$flush && isset($job)) {
            exec($cmd . " > " . __DIR__ . "/../../public/logs/{$job["id"]}_scheduler.log  2>&1", $out, $err);
            $response['cmd'] = $cmd;
            $response['success'] = true;
            $response['message'] = "Job completed";
            return $response;
        }

        Util::disableOb();
        header('Content-type: text/plain; charset=utf-8');

        $descriptorspec = array(
            0 => array("pipe", "r"),   // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),   // stdout is a pipe that the child will write to
            2 => array("pipe", "w")    // stderr is a pipe that the child will write to
        );

        $process = proc_open($cmd, $descriptorspec, $pipes, realpath('./'), array());
        if (is_resource($process)) {
            while ($s = fgets($pipes[1])) {
                print str_pad($s, 4096);
                flush();
            }
        }
        return null;
    }
}