<?php

namespace app\models;

class Job extends \app\inc\Model
{
    public function getAll()
    {
        $arr = array();
        $sql = "SELECT * FROM jobs order by id";
        $res = $this->prepare($sql);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        while ($row = $this->fetchRow($res, "assoc")) {
            $arr[] = $row;
        }
        $response['success'] = true;
        $response['message'] = "Jobs fetched";
        $response['data'] = (sizeof($arr) > 0) ? $arr : null;
        return $response;
    }

    public function newJob($data)
    {
        $sql = "INSERT INTO jobs (name, schema, url, cron, epsg, type, min, hour, dayofmonth, month, dayofweek, encoding) VALUES(:name, :schema, :url, :cron, :epsg, :type, :min, :hour, :dayofmonth, :month, :dayofweek, :encoding)";
        $res = $this->prepare($sql);
        try {
            $res->execute(array(":name" => \app\inc\Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min"=>$data->min, ":hour"=>$data->hour, ":dayofmonth"=>$data->dayofmonth, ":month"=>$data->month, ":dayofweek"=>$data->dayofweek, ":encoding"=>$data->encoding));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $cronInstall = $this->createCronJobs();
        if ($cronInstall !== true){
            $response['success'] = false;
            $response['message'] = $cronInstall;
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Jobs created";
        return $response;
    }

    public function updateJob($data)
    {
        $sql = "UPDATE jobs SET name=:name, schema=:schema, url=:url, cron=:cron, epsg=:epsg, type=:type, min=:min, hour=:hour, dayofmonth=:dayofmonth, month=:month, dayofweek=:dayofweek, encoding=:encoding WHERE id=:id";
        $res = $this->prepare($sql);
        try {
            $res->execute(array(":name" => \app\inc\Model::toAscii($data->name, NULL, "_"), ":schema" => $data->schema, ":url" => $data->url, ":cron" => $data->cron, ":epsg" => $data->epsg, ":type" => $data->type, ":min"=>$data->min, ":hour"=>$data->hour, ":dayofmonth"=>$data->dayofmonth, ":month"=>$data->month, ":dayofweek"=>$data->dayofweek, ":encoding"=>$data->encoding, ":id" => $data->id));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $cronInstall = $this->createCronJobs();
        if ($cronInstall !== true){
            $response['success'] = false;
            $response['message'] = $cronInstall;
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Jobs updated";
        return $response;
    }

    public function deleteJob($data)
    {
        $sql = "DELETE FROM jobs WHERE id=:id";
        $res = $this->prepare($sql);
        try {
            $res->execute(array(":id" => $data->id));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $cronInstall = $this->createCronJobs();
        if ($cronInstall !== true){
            $response['success'] = false;
            $response['message'] = $cronInstall;
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        $response['message'] = "Job deleted";
        return $response;
    }

    public function createCronJobs()
    {
        $jobs = $this->getAll();
        exec("crontab -r");
        foreach ($jobs["data"] as $job) {
            $cmd = "crontab -l | { cat; echo '{$job["min"]} {$job["hour"]} {$job["dayofmonth"]} {$job["month"]} {$job["dayofweek"]} php ". __DIR__ . "/../scripts/get.php {$_SESSION['screen_name']} {$job["schema"]} {$job["name"]} \"{$job["url"]}\" {$job["epsg"]} {$job["type"]} {$job["encoding"]} {$job["id"]} >> ".__DIR__."/../../public/logs/{$job["id"]}_scheduler.log\n'; } | crontab - 2>&1";
            $out = exec($cmd);
            if ($out){
                return $out . " ({$job["id"]})";
            }
        }
        $cmd = "crontab -l | { cat; echo '*/1 * * * * echo \"Hello world\" >> ".__DIR__."/../../public/logs/test.log\n'; } | crontab - 2>&1";
        $out = exec($cmd);
        return true;

    }
}