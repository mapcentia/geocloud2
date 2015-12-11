<?php
namespace app\models;

use app\inc\Model;

class Elasticsearch extends Model
{
    protected $host;

    function __construct()
    {
        $this->host = \app\conf\App::$param['esHost'] ?: "http://127.0.0.1";
    }

    public function map($index, $type, $map)
    {
        $ch = curl_init($this->host . ":9200/{$index}/_mapping/{$type}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    public function createIndex($index, $map)
    {
        $ch = curl_init($this->host . ":9200/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

    public function delete($index, $type = null, $id = null)
    {
        if ($id) {
            $ch = curl_init($this->host . ":9200/{$index}/{$type}/{$id}");
        } elseif ($type) {
            $ch = curl_init($this->host . ":9200/{$index}/{$type}");
        } else {
            $ch = curl_init($this->host . ":9200/{$index}");
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        $buffer = curl_exec($ch);
        curl_close($ch);
        $response['json'] = $buffer;
        return $response;
    }

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
                "index_analyzer" => $value["index_analyzer"],
                "search_analyzer" => $value["search_analyzer"],
                "type" => $value["type"],
                "boost" => $value["boost"],
                "null_value" => $value["null_value"],
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
            if (isset($arr[$key]["index_analyzer"]) && ($arr[$key]["index_analyzer"])) $mapArr["index_analyzer"] = $arr[$key]["index_analyzer"];
            if (isset($arr[$key]["boost"]) && ($arr[$key]["boost"])) $mapArr["boost"] = $arr[$key]["boost"];
            if (isset($arr[$key]["null_value"]) && ($arr[$key]["null_value"])) $mapArr["null_value"] = $arr[$key]["null_value"];
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

    public function mapPg2EsType($pgType, $point = false)
    {
        $esType = null;
        if ($pgType == "geometry") {
            if ($point) {
                $esType = array("type" => "geo_point");
            } else {
                $esType = array("type" => "geo_shape");
            }
        } elseif ($pgType == "string" || $pgType == "text") {
            $esType = array(
                "type" => "string",
                "search_analyzer" => "str_search_analyzer",
                "index_analyzer" => "str_index_analyzer"
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