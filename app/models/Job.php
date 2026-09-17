<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2021 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

ini_set('max_execution_time', "0");

use app\exceptions\GC2Exception;
use app\inc\Model;
use app\inc\SchedulerLock;
use Cron\CronExpression;
use InvalidArgumentException;


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
     * The four BOOL columns (delete_append, download_schema, active, snapshot)
     * are bound as 0/1 through FILTER_VALIDATE_BOOLEAN, never as the raw value:
     *
     * - PDOStatement::execute(array) binds everything as PARAM_STR, so a PHP
     *   `false` reaches Postgres as '' and a BOOL column rejects it with 22P02.
     * - The ExtJS scheduler submits unchecked checkboxes as the *string*
     *   "false" (uncheckedValue in public/scheduler/app/view/MyWindow.js) and
     *   app/controllers/Job.php passes the body through unchanged, so a plain
     *   truthiness test would store an unchecked box as true.
     *
     * FILTER_VALIDATE_BOOLEAN accepts JSON true/false, 1/0, "true"/"false",
     * "on"/"off" and "yes"/"no"; anything else (and an absent property) is false.
     *
     * @param object $data
     * @param string $db
     * @return array<bool|string|int>
     * @throws GC2Exception
     */
    public function newJob(object $data, string $db): array
    {
        $this->validateCronExpression($data);
        $sql = "INSERT INTO jobs (db, name, schema, url, cron, epsg, type, min, hour, dayofmonth, month, dayofweek, encoding, extra, delete_append, download_schema, presql, postsql, active, snapshot) VALUES(:db, :name, :schema, :url, :cron, :epsg, :type, :min, :hour, :dayofmonth, :month, :dayofweek, :encoding, :extra, :delete_append, :download_schema, :presql, :postsql, :active, :snapshot)";
        $res = $this->prepare($sql);
        $res->execute(array(":db" => $db, ":name" => Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min" => $data->min, ":hour" => $data->hour, ":dayofmonth" => $data->dayofmonth, ":month" => $data->month, ":dayofweek" => $data->dayofweek, ":encoding" => $data->encoding, ":extra" => $data->extra, ":delete_append" => filter_var($data->delete_append ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, ":download_schema" => filter_var($data->download_schema ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, ":presql" => $data->presql, ":postsql" => $data->postsql, ":active" => filter_var($data->active ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, ":snapshot" => filter_var($data->snapshot ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0));
        $response['success'] = true;
        $response['message'] = "Jobs created";
        return $response;
    }

    /**
     * Booleans are bound the same way as in newJob(); see the note there.
     *
     * @param object $data
     * @return array<bool|string|int>
     * @throws GC2Exception
     */
    public function updateJob(object $data): array
    {
        $this->validateCronExpression($data);
        $sql = "UPDATE jobs SET name=:name, schema=:schema, url=:url, cron=:cron, epsg=:epsg, type=:type, min=:min, hour=:hour, dayofmonth=:dayofmonth, month=:month, dayofweek=:dayofweek, encoding=:encoding, extra=:extra, delete_append=:delete_append, download_schema=:download_schema, presql=:presql, postsql=:postsql, active=:active, snapshot=:snapshot WHERE id=:id";
        $res = $this->prepare($sql);
        $res->execute(array(":name" => Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min" => $data->min, ":hour" => $data->hour, ":dayofmonth" => $data->dayofmonth, ":month" => $data->month, ":dayofweek" => $data->dayofweek, ":encoding" => $data->encoding, ":id" => $data->id, ":extra" => $data->extra, "delete_append" => filter_var($data->delete_append ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, "download_schema" => filter_var($data->download_schema ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, "presql" => $data->presql, "postsql" => $data->postsql, "active" => filter_var($data->active ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0, "snapshot" => filter_var($data->snapshot ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0));
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
    public function runJob(int $id, string $db, ?string $name = null, bool $force = false, ?array $include = null, bool $async = false, bool $manual = false): true
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
                if (!$job["snapshot"]) $job["snapshot"] = "0";
                if ($force) {
                    $job["delete_append"] = '0';
                }
                $cmd = "/usr/bin/nohup /usr/bin/timeout -s SIGINT -k 60 20h php " . __DIR__ . "/../scripts/get.php --db {$job["db"]} --schema {$job["schema"]} --safeName {$job["name"]} --url \"{$job["url"]}\" --srid {$job["epsg"]} --type {$job["type"]} --encoding {$job["encoding"]} --jobId {$job["id"]} --deleteAppend {$job["delete_append"]} --extra " . (!empty($job["extra"]) ? base64_encode($job["extra"]) : "null") . " --preSql " . (!empty($job["presql"]) ? base64_encode($job["presql"]) : "null") . " --postSql " . (!empty($job["postsql"]) ? base64_encode($job["postsql"]) : "null") . " --downloadSchema {$job["download_schema"]} --snapshot {$job["snapshot"]}" . " --manual " . ($manual ? 1 : 0) . " --name " . base64_encode((string)$name);
                break;
            }
        }
        if ($cmd) {
            $pid = (int)exec($cmd . " > " . __DIR__ . "/../../public/logs/{$job["id"]}_scheduler.log  </dev/null & echo $!");
            if (!$async) {
                // get.php registers itself in started_jobs (see SchedulerLock); wait until that run is over.
                $lock = new SchedulerLock();
                $host = gethostname() ?: 'unknown';
                // Capture the child (php) pid promptly: a fast run (skipped path,
                // or a tiny import) can finish and take its `timeout` wrapper with
                // it within well under a second, so this must not wait a whole
                // second before the first pgrep -P attempt.
                $childPid = $this->childPidOf($pid);
                $start = microtime(true);
                while ($childPid === null && $this->isAlive($pid) && (microtime(true) - $start) < 2.0) {
                    usleep(100000);
                    $childPid = $this->childPidOf($pid);
                }
                $this->waitForRun($pid, $childPid, $host, $lock);
                $lock->release();
            }
        }
        return true;
    }

    /**
     * Waits until the run registered under $childPid (or, if it never
     * registered, $wrapperPid) leaves 'running'. Liveness of the wrapper
     * process is the stop condition: once it's gone, the child is gone too,
     * so one last lookup catches the final registry UPDATE.
     */
    public function waitForRun(int $wrapperPid, ?int $childPid, string $host, SchedulerLock $lock): ?array
    {
        $lookupPid = $childPid ?? $wrapperPid;
        while (true) {
            $run = $lock->latestRunForPid($lookupPid, $host);
            if ($run !== null && $run['status'] !== 'running') {
                break;
            }
            if (!$this->isAlive($wrapperPid)) {
                // wrapper gone: the child is gone too; one last lookup catches the final UPDATE
                $run = $lock->latestRunForPid($lookupPid, $host);
                break;
            }
            sleep(1);
        }
        return $run;
    }

    /** The pid of the php process under a `timeout` wrapper pid, or null. */
    private function childPidOf(int $wrapperPid): ?int
    {
        $out = [];
        exec("pgrep -P " . (int)$wrapperPid, $out);
        return isset($out[0]) && ctype_digit($out[0]) ? (int)$out[0] : null;
    }

    private function isAlive(int $pid): bool
    {
        return function_exists('posix_kill') ? posix_kill($pid, 0) : file_exists("/proc/$pid");
    }

    /**
     * Kills the process with the given ID: SIGINT first (so get.php records
     * "terminated"), SIGKILL after 30 s.
     *
     * @param int $pid The process ID to kill.
     * @return void
     */
    public function kill(int $pid): void
    {
        exec("/bin/kill -INT $pid");
        for ($i = 0; $i < 30 && $this->isAlive($pid); $i++) {
            sleep(1);
        }
        if ($this->isAlive($pid)) {
            exec("/bin/kill -9 $pid");
        }
    }

    /**
     * Runs of this database: running first, then the newest finished ones.
     */
    public function getAllStartedJobs(string $db): array
    {
        $lock = new SchedulerLock();
        $lock->reap();
        $rows = $lock->runsFor($db);
        $lock->release();
        return $rows;
    }

    /**
     * Validates a cron expression using the provided data fields.
     *
     * @param object $data Associative array containing the cron expression fields:
     *                    - min: Minute field of the cron expression.
     *                    - hour: Hour field of the cron expression.
     *                    - dayofmonth: Day of the month field of the cron expression.
     *                    - month: Month field of the cron expression.
     *                    - dayofweek: Day of the week field of the cron expression.
     * @return void
     * @throws GC2Exception If the cron expression is invalid.
     */
    private function validateCronExpression(object $data): void {
        $expression = "{$data->min} {$data->hour} {$data->dayofmonth} {$data->month} {$data->dayofweek}";
        try {
            new CronExpression($expression);
        } catch (InvalidArgumentException $e) {
            throw new GC2Exception($e->getMessage(), 400, null, 'INVALID_CRON_FIELD');
        }
    }

    private const array WRITABLE = ['name', 'schema', 'url', 'schedule', 'epsg', 'type', 'encoding', 'extra',
        'delete_append', 'download_schema', 'presql', 'postsql', 'active', 'snapshot'];
    private const array BOOLS = ['delete_append', 'download_schema', 'active', 'snapshot'];

    public function getById(int $id, string $db): ?array
    {
        $res = $this->prepare("SELECT * FROM jobs WHERE id = :id AND db = :db");
        $this->execute($res, ['id' => $id, 'db' => $db]);
        $row = $this->fetchRow($res);
        return $row ?: null;
    }

    /**
     * @param array<string,mixed> $fields resource keys (see SchedulerJob); schedule is "min hour dom mon dow"
     * @throws GC2Exception 400 on an invalid schedule
     */
    public function createJob(array $fields, string $db): int
    {
        $cols = $this->toColumns($fields + ['epsg' => 4326, 'type' => 'AUTO', 'encoding' => 'UTF8', 'delete_append' => false, 'download_schema' => true, 'active' => true, 'snapshot' => false]);
        $cols['db'] = $db;
        $names = array_keys($cols);
        $sql = "INSERT INTO jobs (" . implode(', ', $names) . ") VALUES (:" . implode(', :', $names) . ") RETURNING id";
        $res = $this->prepare($sql);
        $this->execute($res, $cols);
        return (int)$res->fetchColumn();
    }

    /** @throws GC2Exception 404 when the job is not in $db, 400 on an invalid schedule */
    public function patchJob(int $id, string $db, array $fields): void
    {
        if ($this->getById($id, $db) === null) {
            throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
        }
        $cols = $this->toColumns($fields);
        if ($cols === []) {
            return;
        }
        $sets = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($cols)));
        $res = $this->prepare("UPDATE jobs SET $sets WHERE id = :id AND db = :db");
        $this->execute($res, $cols + ['id' => $id, 'db' => $db]);
    }

    /** @throws GC2Exception 404 when the job is not in $db */
    public function deleteJobById(int $id, string $db): void
    {
        $res = $this->prepare("DELETE FROM jobs WHERE id = :id AND db = :db");
        $this->execute($res, ['id' => $id, 'db' => $db]);
        if ($res->rowCount() === 0) {
            throw new GC2Exception("Job $id not found", 404, null, "JOB_NOT_FOUND");
        }
    }

    /**
     * Resource keys -> jobs columns. Splits schedule into the five cron columns
     * (and mirrors it into the legacy cron column), normalises the name like
     * v2, and binds booleans as 0/1.
     */
    private function toColumns(array $fields): array
    {
        $cols = [];
        foreach ($fields as $k => $v) {
            if (!in_array($k, self::WRITABLE, true)) {
                continue;
            }
            if ($k === 'schedule') {
                $parts = preg_split('/\s+/', trim((string)$v));
                if (count($parts) !== 5) {
                    throw new GC2Exception("schedule must have five cron fields", 400, null, "INVALID_CRON_FIELD");
                }
                try {
                    new CronExpression(implode(' ', $parts));
                } catch (InvalidArgumentException $e) {
                    throw new GC2Exception($e->getMessage(), 400, null, "INVALID_CRON_FIELD");
                }
                [$cols['min'], $cols['hour'], $cols['dayofmonth'], $cols['month'], $cols['dayofweek']] = $parts;
                $cols['cron'] = implode(' ', $parts);
            } elseif ($k === 'name') {
                $cols['name'] = Model::toAscii((string)$v, null, "_");
            } elseif (in_array($k, self::BOOLS, true)) {
                $cols[$k] = filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            } elseif ($k === 'epsg') {
                $cols['epsg'] = (string)(int)$v;
            } else {
                $cols[$k] = $v;
            }
        }
        return $cols;
    }
}