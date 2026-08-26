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
    public function createMapFromTable($table, bool $modern = false)
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
        $esTypes = $layer->getElasticsearchMapping($table, $modern);
        $arr = array();
        foreach ($esTypes["data"] as $value) {
            // Keep the whole per-column row so any modern mapping params (fields,
            // normalizer, doc_values, ...) set through the Meta API pass through.
            $arr[$value["column"]] = $value;
        }
        // Keys that describe the column rather than the ES mapping — never emitted.
        $control = array("id" => 1, "column" => 1, "elasticsearchtype" => 1, "type" => 1, "index_analyzer" => 1);
        foreach ($schema as $key => $value) {
            $pgType = $value["type"];
            $conf = $arr[$key] ?? array();
            $mapArr = array();
            $mapArr["type"] = $conf["elasticsearchtype"] ?? null;
            foreach ($conf as $confKey => $confVal) {
                if (isset($control[$confKey])) continue;
                // Strip index-time boost on the modern path (deprecated/removed in
                // current OpenSearch/ES); legacy keeps emitting it exactly as before.
                if ($modern && $confKey === "boost") continue;
                // Legacy skipped every falsy value (byte-identical); the modern path
                // keeps false/0 so settings like index:false can be expressed.
                if ($modern) {
                    if ($confVal === null || $confVal === "") continue;
                } elseif (!$confVal) {
                    continue;
                }
                $mapArr[$confKey] = $confVal;
            }
            // Modern default: give text fields a keyword multi-field for
            // exact-match/aggregation/sorting, unless the config defines its own.
            if ($modern && ($mapArr["type"] ?? null) === "text" && !isset($mapArr["fields"])) {
                $mapArr["fields"] = array("keyword" => array("type" => "keyword", "ignore_above" => 256));
            }
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
     * @param bool $modern When true, emit up-to-date OpenSearch/ES types (long,
     *                     double, keyword, yyyy date format). Legacy (v1/v2)
     *                     callers leave this false and get byte-identical output.
     * @return array
     */
    public function mapPg2EsType($pgType, $point = false, bool $modern = false)
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
        if ($modern && $pgType != "geometry") {
            // Modern (v4) type corrections. Note: GC2's getType() collapses
            // bigint into "int" and inet into "string", so `long` is used for all
            // integers (a safe superset that avoids overflow) and `ip` is out of
            // reach here. string/text keep "text"; createMapFromTable adds the
            // keyword multi-field for exact-match/aggregation.
            if ($pgType == "int") {
                $esType = array("type" => "long");
            } elseif ($pgType == "number" || $pgType == "decimal" || $pgType == "double") {
                $esType = array("type" => "double");
            } elseif ($pgType == "uuid") {
                $esType = array("type" => "keyword");
            } elseif ($pgType == "timestamptz") {
                $esType = array("type" => "date", "format" => "yyyy-MM-dd HH:mm:ss.SSSSSSZ");
            }
        }
        return $esType;
    }
}