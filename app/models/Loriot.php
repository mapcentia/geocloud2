<?php
namespace app\models;

use app\inc\Model;

class Loriot extends Model
{
    function __construct()
    {
        parent::__construct();
    }

    public function insert($data, $table)
    {
        $response = array();
        $sql = "INSERT INTO {$table}(cmd, eui, ts, ack, fcnt, port, encdata, data) VALUES (:cmd, :eui, :ts, :ack, :fcnt, :port, :encdata, :data) RETURNING *";
        $res = $this->prepare($sql);
        $arr = array($data);
        try {
            $res->execute($arr);
        } catch (\PDOException $e) {
            $response["success"] = false;
            $response["message"] = $e->getMessage();
            $response["code"] = "401";
            return $response;

        }
        $response["success"] = true;
        $response["data"] = json_encode($data);
    }
}