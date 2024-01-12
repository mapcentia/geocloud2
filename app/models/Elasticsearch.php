<?php
/**
 * @author     Martin Høgh <mh@mapcentia.com>
 * @copyright  2013-2018 MapCentia ApS
 * @license    http://www.gnu.org/licenses/#AGPL  GNU AFFERO GENERAL PUBLIC LICENSE 3
 *
 */

namespace app\models;

use app\conf\App;
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
    protected $port;

    /**
     * Elasticsearch constructor.
     */
    function __construct()
    {
        parent::__construct();

        $this->host = App::$param['esHost'] ?: "http://127.0.0.1";
        $split = explode(":", $this->host);
        if (!empty($split[2])) {
            $this->port = $split[2];
        } else {
            $this->port = "9200";
        }
        $this->host = $split[0] . ":" . $split[1] . ":" . $this->port;
    }

    /**
     * @param $index
     * @param $map
     * @return array
     */
    public function map($index, $map)
    {
        $response = [];
        $ch = curl_init($this->host . "/{$index}/_mapping");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
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
        $ch = curl_init($this->host . "/{$index}");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $map);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
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
    public function delete($index, $id = null): array
    {
        $response = [];
        if ($id) {
            $ch = curl_init($this->host . "/{$index}/{$id}");
        }
        else {
            $ch = curl_init($this->host . "/{$index}");
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
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
        $tableObj = new Table($table);
        $schema = $tableObj->getMapForEs();
        $map =
                array("properties" =>
                    array("properties" =>
                        array(
                            "type" => "object",
                            "properties" => array()
                        )
                    )

        );
        $layer = new Layer();
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
                    $map["mappings"]["properties"]["geometry"]["properties"]["coordinates"] = $mapArr;
                } else {
                    $map["mappings"]["properties"]["geometry"] = $mapArr;
                }
            } else {
                $map["mappings"]["properties"]["properties"]["properties"][$key] = $mapArr;
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
                "type" => "text"
            );
        } elseif ($pgType == "hstore") {
            $esType = array(
                "type" => "text"
            );
        } elseif ($pgType == "bytea") {
            $esType = array(
                "type" => "binary"
            );
        } elseif ($pgType == "json" || $pgType == "jsonb") {
            $esType = array(
                "type" => "object"
            );
        } else {
            $esType = array(
                "type" => "text"
            );
        }
        return $esType;
    }
}