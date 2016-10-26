<?php

namespace app\models;

/**
 * Class Sql
 * @package app\models
 */
class Sql extends \app\inc\Model
{
    /**
     * @var string
     */
    private $srs;

    /**
     * Sql constructor.
     * @param string $srs
     */
    function __construct($srs = "900913")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    /**
     * @param string $q
     * @param null $clientEncoding
     * @return mixed
     */
    public function sql($q, $clientEncoding = null)
    {
        $name = "_" . rand(1, 999999999) . microtime();
        $view = $this->toAscii($name, null, "_");
        $sqlView = "CREATE TEMPORARY VIEW {$view} as {$q}";
        $res = $this->prepare($sqlView);
        try {
            $res->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 400;
            return $response;
        }
        $arrayWithFields = $this->getMetaData($view, true); // Temp VIEW
        $postgisVersion = $this->postgisVersion();
        $bits = explode(".", $postgisVersion["version"]);
        if ((int)$bits[1] > 0) {
            $ST_Force2D = "ST_Force2D";
        } else {
            $ST_Force2D = "ST_Force_2D";
        }

        $fieldsArr = [];
        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                $fieldsArr[] = "ST_asGeoJson(ST_Transform({$ST_Force2D}(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
            } else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        $sql = implode(",", $fieldsArr);
        $sql = "SELECT {$sql} FROM {$view}";
        if ($clientEncoding) {
            $this->execQuery("set client_encoding='{$clientEncoding}'", "PDO");
        }
        $result = $this->prepare($sql);
        try {
            $result->execute();
        } catch (\PDOException $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 410;
            return $response;
        }
        $geometries = [];
        $fieldsForStore = [];
        $columnsForGrid = [];
        $features = [];
        try {
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr = array();
                foreach ($row as $key => $value) {
                    if ($arrayWithFields[$key]['type'] == "geometry") {
                        $geometries[] = json_decode($row[$key]);
                    } elseif ($arrayWithFields[$key]['type'] == "json") {
                        $arr = $this->array_push_assoc($arr, $key, json_decode($value));
                    } else {
                        $arr = $this->array_push_assoc($arr, $key, $value);
                    }
                }
                if (sizeof($geometries) > 1) {
                    $features[] = array("geometry" => array("type" => "GeometryCollection", "geometries" => $geometries), "type" => "Feature", "properties" => $arr);
                }
                if (sizeof($geometries) == 1) {
                    $features[] = array("geometry" => $geometries[0], "type" => "Feature", "properties" => $arr);
                }
                if (sizeof($geometries) == 0) {
                    $features[] = array("type" => "Feature", "properties" => $arr);
                }
                unset($geometries);
            }
        } catch (\Exception $e) {
            $response['success'] = false;
            $response['message'] = $e->getMessage();
            $response['code'] = 410;
            return $response;
        }
        foreach ($arrayWithFields as $key => $value) {
            $fieldsForStore[] = array("name" => $key, "type" => $value['type']);
            $columnsForGrid[] = array("header" => $key, "dataIndex" => $key, "type" => $value['type'], "typeObj" => $value['typeObj']);
        }
        $this->free($result);
        $sql = "DROP VIEW {$view}";
        $result = $this->execQuery($sql);
        $this->free($result);
        $response['success'] = true;
        $response['forStore'] = $fieldsForStore;
        $response['forGrid'] = $columnsForGrid;
        $response['type'] = "FeatureCollection";
        $response['features'] = $features;
        return $response;
    }

    /**
     * @param $q
     * @return mixed
     */
    public function transaction($q)
    {
        $result = $this->execQuery($q, "PDO", "transaction");
        if (!$this->PDOerror) {
            $response['success'] = true;
            $response['affected_rows'] = $result;
        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
        }
        $this->free($result);
        return $response;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }
}
