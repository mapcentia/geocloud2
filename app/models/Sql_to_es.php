<?php
namespace app\models;

use app\inc\Model;

class Sql_to_es extends Model
{
    var $srs;

    function __construct($srs = "900913")
    {
        parent::__construct();
        $this->srs = $srs;
    }

    private function checkForErrors($obj)
    {
        $res = array();
        foreach ($obj["items"] as $item) {
            if (isset($item["index"])){
                $key = "index";
            } else {
                $key = "create"; // If no key is given Elasticsearch will use this key
            }
            if ($item[$key]["status"] != "201") {
                $res[] = array(
                    "id" => $item[$key]["_id"],
                    "error" => $item[$key]["error"],
                );
            }
        }
        return $res;
    }

    public function sql($q, $index, $type, $id, $db)
    {
        // We create a unique index name
        $errors = false;
        $errors_in = array();
        $index = $db . "_" . $index . "_" . $type;
        $name = "_" . rand(1, 999999999) . microtime();
        $name = $this->toAscii($name, null, "_");
        $view = "sqlapi.{$name}";
        $sqlView = "CREATE VIEW {$view} as {$q}";
        $this->execQuery($sqlView);
        $arrayWithFields = $this->getMetaData($view);

        // First check. Is the SQL valid
        if ($this->PDOerror) {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
            $sql = "DROP VIEW {$view}";
            $result = $this->execQuery($sql);
            $this->free($result);
            return $response;
        }
        $postgisVersion = $this->postgisVersion();
        $bits = explode(".", $postgisVersion["version"]);
        if ((int)$bits[1] > 0) {
            $ST_Force2D = "ST_Force2D";
        } else {
            $ST_Force2D = "ST_Force_2D";
        }

        foreach ($arrayWithFields as $key => $arr) {
            if ($arr['type'] == "geometry") {
                $fieldsArr[] = "ST_asGeoJson(ST_Transform({$ST_Force2D}(\"" . $key . "\")," . $this->srs . ")) as \"" . $key . "\"";
            } else {
                $fieldsArr[] = "\"{$key}\"";
            }
        }
        $sql = implode(",", $fieldsArr);
        $sql = "SELECT {$sql} FROM {$view}";
        $result = $this->execQuery($sql);

        // Second check. Catched eg. transform errors
        if ($this->PDOerror) {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
            $sql = "DROP VIEW {$view}";
            $result = $this->execQuery($sql);
            $this->free($result);
            return $response;
        }

        $i = 0;
        $json = "";
        $ch = curl_init(\app\conf\App::$param['esHost'] . ":9200/_bulk");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Basic ZWxhc3RpYzpjaGFuZ2VtZQ==',
        ));
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
                $features = array("geometry" => array("type" => "GeometryCollection", "geometries" => $geometries), "type" => "Feature", "properties" => $arr);
            }
            if (sizeof($geometries) == 1) {
                $features = array("geometry" => $geometries[0], "type" => "Feature", "properties" => $arr);
            }
            if (sizeof($geometries) == 0) {
                $features = array("type" => "Feature", "properties" => $arr);
            }
            unset($geometries);
            $json .= json_encode(array("index" => array("_index" => $index, "_type" => $type, "_id" => $arr[$id])));
            $json .= "\n";
            $json .= json_encode($features);
            $json .= "\n";
            if (is_int($i / 1000)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                $buffer = curl_exec($ch);
                $obj = json_decode($buffer, true);
                if (isset($obj["errors"]) && $obj["errors"] == true) {
                    $errors = true;
                    $errors_in = array_merge($errors_in, $this->checkForErrors($obj));
                }
                $json = "";
            }
            $i++;
        }
        // Index the last bulk
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        $buffer = curl_exec($ch);
        $obj = json_decode($buffer, true);
        if (isset($obj["errors"]) && $obj["errors"] == true) {
            $errors = true;
            $errors_in = array_merge($errors_in, $this->checkForErrors($obj));

        }
        curl_close($ch);
        if ($errors){
            \app\inc\Session::createLogEs($errors_in);
        }
        $response['success'] = true;
        $response['errors'] = $errors;
        $response['errors_in'] = $errors_in;
        $response['message'] = "Indexed {$i} documents";

        $this->free($result);
        $sql = "DROP VIEW {$view}";
        $result = $this->execQuery($sql);
        $this->free($result);
        return $response;
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

}
