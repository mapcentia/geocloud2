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
use Exception;


/**
 * Class Job
 * @package app\models
 */
class Job extends Model
{
    /**
     * @param string|null $db
     * @return array
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
        $res->execute($args);
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
        $res->execute(array(":db" => $db, ":name" => Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min" => $data->min, ":hour" => $data->hour, ":dayofmonth" => $data->dayofmonth, ":month" => $data->month, ":dayofweek" => $data->dayofweek, ":encoding" => $data->encoding, ":extra" => $data->extra, ":delete_append" => $data->delete_append, ":download_schema" => $data->download_schema, ":presql" => $data->presql, ":postsql" => $data->postsql, ":active" => $data->active));
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
        $res->execute(array(":name" => Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min" => $data->min, ":hour" => $data->hour, ":dayofmonth" => $data->dayofmonth, ":month" => $data->month, ":dayofweek" => $data->dayofweek, ":encoding" => $data->encoding, ":id" => $data->id, ":extra" => $data->extra, "delete_append" => $data->delete_append, "download_schema" => $data->download_schema, "presql" => $data->presql, "postsql" => $data->postsql, "active" => $data->active));
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
        $res->execute(array(":id" => $data->id));
        $response['success'] = true;
        $response['message'] = "Job deleted";
        return $response;
    }

    /**
     * @param int $id
     * @param string $db
     * @param string|null $name
     * @param bool $force
     * @param array|null $include
     * @return true
     */
    public function runJob(int $id, string $db, ?string $name = null, bool $force = false, ?array $include = null): true
    {
        $cmd = null;
        $job = null;
        $jobs = $this->getAll($db);
        foreach ($jobs["data"] as $job) {
            if ($id == $job["id"]) {
                if ($include && !in_array($job['name'], $include)) {
                    continue;
                }
                if (!$job["delete_append"]) $job["delete_append"] = "0";
                if (!$job["download_schema"]) $job["download_schema"] = "0";
                if ($force) {
                    $job["delete_append"] = '0';
                }
                $cmd = "/usr/bin/nohup /usr/bin/timeout -s SIGINT 20h php " . __DIR__ . "/../scripts/get.php --db {$job["db"]} --schema {$job["schema"]} --safeName {$job["name"]} --url \"{$job["url"]}\" --srid {$job["epsg"]} --type {$job["type"]} --encoding {$job["encoding"]} --jobId {$job["id"]} --deleteAppend {$job["delete_append"]} --extra " . (!empty($job["extra"]) ? base64_encode($job["extra"]) : "null") . " --preSql " . (!empty($job["presql"]) ? base64_encode($job["presql"]) : "null") . " --postSql " . (!empty($job["postsql"]) ? base64_encode($job["postsql"]) : "null") . " --downloadSchema {$job["download_schema"]}";
                break;
            }
        }
        if ($cmd) {
            $pid = (int)exec($cmd . " > " . __DIR__ . "/../../public/logs/{$job["id"]}_scheduler.log  & echo $!");
            try {
                $this->insert($job['id'], $pid, $job['db'], $name);
            } catch (Exception) {
                $this->kill($pid); // If we can't insert the pid we kill the process if its running
            }
        }
        return true;
    }

    /**
     * @param int $id
     * @param int $pid
     * @param string $db
     * @param string|null $name
     * @return void
     */
    public function insert(int $id, int $pid, string $db, ?string $name): void
    {
        $sql = "INSERT INTO started_jobs (id, pid, db, name) VALUES (:id, :pid, :db, :name) RETURNING *";
        $res = $this->prepare($sql);
        $arr = ['id' => $id, 'pid' => $pid, 'db' => $db, 'name' => $name];
        $res->execute($arr);
    }

    /**
     * Kills the process with the given ID.
     *
     * @param int $pid The process ID to kill.
     * @return void
     */
    private function kill(int $pid): void
    {
        exec("/bin/kill -9 $pid");
    }

    /**
     * @param string $db
     * @return array
     */
    public function getAllStartedJobs(string $db): array
    {
        $sql = "SELECT * FROM started_jobs where db=:db";
        $res = $this->prepare($sql);
        $res->execute(['db' => $db]);
        return $this->fetchAll($res, 'assoc');
    }
}