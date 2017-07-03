<?php

namespace app\models;

use app\inc\Model;

/**
 * Class Elasticsearch
 * @package app\models
 */
class Elasticsearch extends Model
{
    /**
     * @var string
     */
    protected $host;

    /**
     * Elasticsearch constructor.
     */
    function __construct()
    {
        $this->host = \app\conf\App::$param['esHost'] ?: "http://127.0.0.1";
    }

    /**
     * @param $index
     * @param $type
     * @param $map
     * @return array
     */
    public function map($index, $type, $map)
    {
        $response = [];
        $ch = curl_init($this->host . ":9200/{$index}/_mapping/{$type}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ));
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    /**
     * @param $index
     * @param $map
     * @return array
     */
    public function createIndex($index, $map)
    {
        $response = [];
        $ch = curl_init($this->host . ":9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ));
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    /**
     * @param $index
     * @param null $type
     * @param null $id
     * @return array
     */
    public function delete($index, $type = null, $id = null)
    {
        $response = [];
        if ($id) {
            $ch = curl_init($this->host . ":9200/{$index}_{$type}/{$type}/{$id}");
        } elseif ($type) {
            $ch = curl_init($this->host . ":9200/{$index}_{$type}");
        } else {
            $ch = curl_init($this->host . ":9200/{$index}");
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ));
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    /**
     * @param $table
     * @return array
     */
    public function createMapFromTable($table)
    {
        $split = explode(".", $table);
        $type = $split[1];
        if (mb_substr($type, 0, 1, 'utf-8') == "_") {
            $type = "a" . $type;
        }
        $tableObj = new \app\models\Table($table);
        $schema = $tableObj->getMapForEs();
        $map = array("mappings" =>
            array($type =>
                array("properties" =>
                    array("properties" =>
                        array(
                            "type" => "object",
                            "properties" => array()
                        )
                    )
                )
            )
        );
        $layer = new \app\models\Layer();
        $esTypes = $layer->getElasticsearchMapping($table);
        $arr = array();
        foreach ($esTypes["data"] as $key => $value) {
            $arr[$value["column"]] = array(
                "elasticsearchtype" => $value["elasticsearchtype"],
                "format" => $value["format"],
                "index" => $value["index"],
                "analyzer" => $value["analyzer"],
                "search_analyzer" => $value["search_analyzer"],
                "type" => $value["type"],
                "boost" => $value["boost"],
                "null_value" => $value["null_value"],
                "fielddata" => $value["fielddata"],
            );
        }
        foreach ($schema as $key => $value) {
            $pgType = $value["type"];
            $mapArr = array();
            $mapArr["type"] = $arr[$key]["elasticsearchtype"];
            if (isset($arr[$key]["format"]) && ($arr[$key]["format"])) $mapArr["format"] = $arr[$key]["format"];
            if (isset($arr[$key]["index"]) && ($arr[$key]["index"])) $mapArr["index"] = $arr[$key]["index"];
            if (isset($arr[$key]["analyzer"]) && ($arr[$key]["analyzer"])) $mapArr["analyzer"] = $arr[$key]["analyzer"];
            if (isset($arr[$key]["search_analyzer"]) && ($arr[$key]["search_analyzer"])) $mapArr["search_analyzer"] = $arr[$key]["search_analyzer"];
            if (isset($arr[$key]["boost"]) && ($arr[$key]["boost"])) $mapArr["boost"] = $arr[$key]["boost"];
            if (isset($arr[$key]["null_value"]) && ($arr[$key]["null_value"])) $mapArr["null_value"] = $arr[$key]["null_value"];
            if (isset($arr[$key]["fielddata"]) && ($arr[$key]["fielddata"])) $mapArr["fielddata"] = $arr[$key]["fielddata"];
            if ($pgType == "geometry") {
                if ($mapArr["type"] == "geo_point") {
                    $map["mappings"][$type]["properties"]["geometry"]["properties"]["coordinates"] = $mapArr;
                } else {
                    $map["mappings"][$type]["properties"]["geometry"] = $mapArr;
                }
            } else {
                $map["mappings"][$type]["properties"]["properties"]["properties"][$key] = $mapArr;
            }
        }
        $response = array("map" => $map);
        return $response["map"]["mappings"];
    }

    /**
     * @param $pgType
     * @param bool $point
     * @return array
     */
    public function mapPg2EsType($pgType, $point = false)
    {
        if ($pgType == "geometry") {
            if ($point) {
                $esType = array("type" => "geo_point");
            } else {
                $esType = array("type" => "geo_shape");
            }
        } elseif ($pgType == "string" || $pgType == "text") {
            $esType = array(
                "type" => "text"
            );
        } elseif ($pgType == "timestamptz") {
            $esType = array(
                "type" => "date",
                "format" => "Y-MM-dd HH:mm:ss.SSSSSSZ"
            );
        } elseif ($pgType == "date") {
            $esType = array(
                "type" => "date"
            );
        } elseif ($pgType == "int") {
            $esType = array(
                "type" => "integer"
            );
        } elseif ($pgType == "number") {
            $esType = array(
                "type" => "float"
            );
        } elseif ($pgType == "boolean") {
            $esType = array(
                "type" => "boolean"
            );
        } elseif ($pgType == "uuid") {
            $esType = array(
                "type" => "string"
            );
        } elseif ($pgType == "hstore") {
            $esType = array(
                "type" => "string"
            );
        } elseif ($pgType == "bytea") {
            $esType = array(
                "type" => "binary"
            );
        } else {
            $esType = array(
                "type" => "string"
            );
        }
        return $esType;
    }
}