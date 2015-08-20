<?php

namespace app\models;

use app\inc\Util;

class Collector extends \app\inc\Model
{
    public function store($content)
    {
        $arr = array();
        $split = explode(".", $content["table"]);
        $sql = "INSERT INTO \"{$split[0]}\".\"{$split[1]}\" (\"{$content["valueField"]}\",\"{$content["geomField"]}\") VALUES(:values::hstore, St_geomfromtext(:geom,4326))";
        foreach ($content["values"] as $key => $value) {
            $arr[] = "{$key}=>\"".pg_escape_string($value)."\"";
        }
        $hstore = implode(",", $arr);
        //die($sql);
        $geometry = "POINT({$content["coords"]["lng"]} {$content["coords"]["lat"]})";
        $res = $this->prepare($sql);
        try {
            $res->execute(array("values" => $hstore, "geom" => $geometry));
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $response['success'] = true;
        return $response;
    }
}
