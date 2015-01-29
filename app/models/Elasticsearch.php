<?php
namespace app\models;

use app\inc\Model;
use app\inc\log;
use \app\conf\Connection;

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
        $table = new \app\models\Table($table);
        $schema = $table->getMapForEs();
        $settings = json_decode('{
                                    "analysis" : {
                                      "analyzer" : {
                                        "str_search_analyzer" : {
                                          "type" : "custom",
                                          "tokenizer" : "whitespace",
                                          "filter" : ["lowercase"]
                                        },
                                        "str_index_analyzer" : {
                                          "type" : "custom",
                                          "tokenizer" : "whitespace",
                                          "filter" : ["lowercase", "substring"]
                                        }
                                      },
                                      "filter" : {
                                        "substring" : {
                                          "type" : "edgeNGram",
                                          "min_gram" : 1,
                                          "max_gram"  : 255
                                        }
                                      }
                                    }
                                  }'
        );
        $map = array("mappings" =>
            array($split[1] =>
                array("properties" =>
                    array("properties" =>
                        array(
                            "type" => "object",
                            "properties" => array()
                        ),
                        "geometry" => array()
                    )
                )
            )
        );
        foreach ($schema as $key => $value) {
            if ($value["type"] == "geometry") {
                $map["mappings"][$split[1]]["properties"]["geometry"] =
                    array("type" => "geo_shape");
            } elseif ($value["type"] == "string") {
                $map["mappings"][$split[1]]["properties"]["properties"]["properties"][$key] =
                    array(
                        "type" => "string",
                        "search_analyzer" => "str_search_analyzer",
                        "index_analyzer" => "str_index_analyzer"
                    );
            } elseif ($value["type"] == "timestamptz") {
                $map["mappings"][$split[1]]["properties"]["properties"]["properties"][$key] =
                    array(
                        "type" => "date",
                        "format" => "Y-MM-dd HH:mm:ss.SSSSSSZ"
                    );
            } elseif ($value["type"] == "date") {
                $map["mappings"][$split[1]]["properties"]["properties"]["properties"][$key] =
                    array(
                        "type" => "date"
                    );
            } elseif ($value["type"] == "int") {
                $map["mappings"][$split[1]]["properties"]["properties"]["properties"][$key] =
                    array(
                        "type" => "integer"
                    );
            } elseif ($value["type"] == "number") {
                $map["mappings"][$split[1]]["properties"]["properties"]["properties"][$key] =
                    array(
                        "type" => "float"
                    );
            } elseif ($value["type"] == "boolean") {
                $map["mappings"][$split[1]]["properties"]["properties"]["properties"][$key] =
                    array(
                        "type" => "boolean"
                    );
            }
        }
        $map["settings"] = $settings;
        $response = array("map" => $map);
        return $response["map"]["mappings"];
    }
}