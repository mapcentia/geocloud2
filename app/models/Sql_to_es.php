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

    function sql($q, $index, $type, $id, $db)
    {
        // We create a unique index name
        $index = $db."_".$index;
        $name = "_" . rand(1, 999999999) . microtime();
        $name = $this->toAscii($name, null, "_");
        $view = "sqlapi.{$name}";
        $sqlView = "CREATE VIEW {$view} as {$q}";
        $this->execQuery($sqlView);
        if (!$this->PDOerror) {
            $arrayWithFields = $this->getMetaData($view);
            foreach ($arrayWithFields as $key => $arr) {
                if ($arr['type'] == "geometry") {
                    $fieldsArr[] = "ST_asGeoJson(ST_Transform(" . $key . "," . $this->srs . ")) as " . $key;
                } else {
                    $fieldsArr[] = $key;
                }
            }
            $sql = implode(",", $fieldsArr);
            $sql = "SELECT {$sql} FROM {$view}";
            $result = $this->execQuery($sql);
            $i = 0;
            $json = "";
            $ch = curl_init(\app\conf\App::$param['esHost'].":9200/_bulk");
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt( $ch, CURLOPT_HEADER, 0);
            while ($row = $this->fetchRow($result, "assoc")) {
                $arr = array();
                foreach ($row as $key => $value) {

                    if ($arrayWithFields[$key]['type'] == "geometry") {
                        $geometries[] = json_decode($row[$key]);
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
                //echo $json;

                if (is_int($i / 1000)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
                    curl_exec($ch);
                    $json="";
                }
                $i++;
            }
            // Index the last bulk
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
            //echo $json;
            $buffer = curl_exec($ch);
            $obj = json_decode($buffer, true);
            if (isset($obj["errors"]) && $obj["errors"] == true) {
                return $obj;
            }
            curl_close($ch);
            $response['success'] = true;
            $response['message'] = "Indexed {$i} documents";
            return $response;

        } else {
            $response['success'] = false;
            $response['message'] = $this->PDOerror;
            $response['code'] = 400;
            return $response;
        }
        $sql = "DROP VIEW {$view}";
        $result = $this->execQuery($sql);
    }

    private function array_push_assoc($array, $key, $value)
    {
        $array[$key] = $value;
        return $array;
    }

}
