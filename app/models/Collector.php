<?php

namespace app\models;

/**
 * Class Collector
 * @package app\models
 */
class Collector extends \app\inc\Model
{
    /**
     * @param array $content
     * @return array
     */
    public function store(array $content) : array
    {
        $response = [];
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
